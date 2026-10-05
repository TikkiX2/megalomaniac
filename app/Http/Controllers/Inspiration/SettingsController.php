<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inspiration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspiration\UpdateInspirationSettingsRequest;
use App\Inspiration\Contracts\Source;
use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\InspirationSettings;
use App\Inspiration\SourceManager;
use App\Inspiration\Support\CredentialFields;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Per-user inspiration settings: source enablement, credentials, maturity and
 * the Tier 3 acknowledgement.
 *
 * Raw credential values never leave the server. `index()` exposes the
 * per-source credential field names plus a `has_key` boolean, and `update()`
 * merges the payload over the current bag so any field the client omits keeps
 * its stored value (including credentials).
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SourceManager $sources,
        private readonly InspirationSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $bag = $this->settings->for($user);

        $sources = $this->sources->all()
            ->map(function (Source $source) use ($bag): array {
                $key = $source->key();

                $source->setCredentials(array_merge(
                    $bag->keys[$key] ?? [],
                    ['user_agent' => $bag->zerochanUa],
                ));

                return [
                    'key' => $key,
                    'label' => $source->label(),
                    'needs_key' => $source->capabilities()->needsKey,
                    'credential_fields' => $source->capabilities()->needsKey
                        ? CredentialFields::for($key)
                        : [],
                    'has_key' => $bag->hasKey($key),
                    'configured' => $source->isConfigured(),
                    'enabled' => $bag->isEnabled($key),
                    'has_tier3_notice' => in_array($key, config('inspiration.tier3', []), true),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('inspiration/settings', [
            'sources' => $sources,
            'settings' => [
                'enabled_sources' => $bag->enabledSources,
                'maturity' => $bag->maturity,
                'zerochan_ua' => $bag->zerochanUa,
                'acknowledged_tier3' => $bag->acknowledgedTier3,
            ],
        ]);
    }

    public function update(UpdateInspirationSettingsRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $bag = $this->settings->for($user);
        $data = $request->validated();

        $this->settings->update($user, [
            'enabled_sources' => $data['enabled_sources'] ?? $bag->enabledSources,
            'keys' => $this->mergeKeys($bag->keys, (array) ($data['keys'] ?? [])),
            'maturity' => $data['maturity'] ?? $bag->maturity,
            'zerochan_ua' => $request->exists('zerochan_ua')
                ? $this->normalizeZerochanUa($data['zerochan_ua'] ?? null)
                : $bag->zerochanUa,
            'acknowledged_tier3' => $data['acknowledged_tier3'] ?? $bag->acknowledgedTier3,
        ]);

        return back()->with('success', 'Ajustes de inspiración guardados.');
    }

    public function test(Request $request, string $source): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $bag = $this->settings->for($user);
        $adapter = $this->sources->get($source);

        if ($adapter === null) {
            return $this->failure('fuente desconocida');
        }

        if ($adapter->capabilities()->needsKey && ! $bag->hasKey($source)) {
            return $this->failure('falta la key de esta fuente');
        }

        $adapter->setCredentials(array_merge(
            $bag->keys[$source] ?? [],
            ['user_agent' => $bag->zerochanUa],
        ));

        try {
            $ok = $adapter->test();
        } catch (SourceException $exception) {
            Log::warning('Inspiration source test failed', [
                'source' => $source,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('no se pudo conectar con esta fuente: '.$exception->getMessage());
        } catch (Throwable $exception) {
            Log::warning('Inspiration source test errored', [
                'source' => $source,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('no se pudo conectar con esta fuente');
        }

        return $ok
            ? response()->json(['ok' => true])
            : $this->failure('no se pudo conectar con esta fuente');
    }

    /**
     * Replace only the key sources present in the payload, normalizing empty
     * values to null and dropping sources whose credentials are now all empty.
     * Sources the client did not send keep their stored credentials.
     *
     * @param  array<string, array<string, string|null>>  $existing
     * @param  array<array-key, mixed>  $incoming
     * @return array<string, array<string, string|null>>
     */
    private function mergeKeys(array $existing, array $incoming): array
    {
        foreach ($incoming as $source => $fields) {
            $source = (string) $source;

            if ($this->keySource($source) === null) {
                continue;
            }

            $normalized = [];

            foreach (CredentialFields::for($source) as $field) {
                $value = is_array($fields) ? ($fields[$field] ?? null) : null;
                $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }

            if (array_filter($normalized, static fn (?string $value): bool => $value !== null) === []) {
                unset($existing[$source]);

                continue;
            }

            $existing[$source] = $normalized;
        }

        return $existing;
    }

    private function keySource(string $key): ?Source
    {
        $source = $this->sources->get($key);

        return $source !== null && $source->capabilities()->needsKey ? $source : null;
    }

    /**
     * Normalize the per-user Zerochan User-Agent: blank means "unset".
     */
    private function normalizeZerochanUa(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function failure(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
