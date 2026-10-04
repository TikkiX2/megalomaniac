<?php

namespace App\Http\Controllers\Settings;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Enums\AiScope;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiPromptComposer;
use App\Ai\Support\AiProviderConfigurator;
use App\Ai\Support\AiScopeResolver;
use App\Ai\Support\ByoProviderMigrator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAiProviderRequest;
use App\Http\Requests\Settings\UpdateAiProviderRequest;
use App\Http\Requests\Settings\UpdateAiScopeRequest;
use App\Models\AiProvider;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiSettingsController extends Controller
{
    /** Seconds a "Probar conexión" probe may take before it gives up. */
    private const TEST_TIMEOUT_SECONDS = 15;

    /** Width of the error surfaced by the connection probe. */
    private const TEST_ERROR_LENGTH = 300;

    /** Placeholder shown in the Tavily field when a key is already stored. */
    private const TAVILY_STORED_PLACEHOLDER = 'tvly-••••••••';

    /**
     * Show the AI settings page.
     *
     * Props (consumed by the three tabs of the page):
     *
     * - `ai`: the master switch plus the Tavily key state; the legacy BYO
     *   columns (`ai_provider_url`, `ai_provider_key`, `ai_model`,
     *   `ai_embeddings_model`) are no longer part of the form: they live in
     *   `ai_providers` since the registry landed.
     * - `providers`: the user's own providers ordered by `sort_order`, never
     *   carrying the key itself, plus the circuit breaker row per provider.
     * - `scopes`: the 13 `AiScope` cases in declaration order, each with the
     *   persisted chain (`[]` = inherits) and the chain that would be used
     *   today for that context, health filter included.
     * - `module_labels`: module key => label, so the assignments tab can label
     *   rows without duplicating the enum copy in the frontend.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/ai', [
            'ai' => [
                'ai_enabled' => (bool) $user->ai_enabled,
                'has_tavily_key' => filled($user->tavily_api_key),
                'tavily_placeholder' => $user->tavily_api_key
                    ? self::TAVILY_STORED_PLACEHOLDER
                    : 'tvly-...',
            ],
            'providers' => $this->providersFor($user),
            'scopes' => $this->scopesFor($user),
            'module_labels' => $this->moduleLabels(),
        ]);
    }

    /**
     * Update the user's AI settings.
     *
     * Only the master switch and the Tavily key are editable here: the BYO
     * columns left the form when providers became their own registry rows.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tavily_api_key' => ['nullable', 'string', 'max:500'],
            'ai_enabled' => ['required', 'boolean'],
        ]);

        if (blank($validated['tavily_api_key'] ?? null)) {
            unset($validated['tavily_api_key']);
        }

        $request->user()->update($validated);

        Cache::forget("ai.models.{$request->user()->getKey()}");

        return to_route('settings.ai.edit');
    }

    /**
     * Store a new provider. `sort_order` defaults to the end of the list so a
     * fresh provider never jumps ahead of the existing chain.
     */
    public function storeProvider(StoreAiProviderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('enabled', 'sort_order');

        $data['user_id'] = $user->getKey();
        $data['enabled'] = $request->boolean('enabled', true);
        $data['sort_order'] = $request->has('sort_order')
            ? (int) $request->input('sort_order')
            : $this->nextSortOrder($user);

        AiProvider::query()->create($data);

        return to_route('settings.ai.edit')->with('success', 'Proveedor creado.');
    }

    /**
     * Patch one provider. A blank `key` keeps the stored one, so the edit sheet
     * never has to render the real credential.
     */
    public function updateProvider(UpdateAiProviderRequest $request, int $provider): RedirectResponse
    {
        $model = $this->ownedProvider($request, $provider);
        $data = $request->safe()->except(['enabled', 'key']);

        // A blank key means "keep the stored one": it is simply not part of
        // the update, so the edit sheet never has to render the credential.
        if (filled($request->input('key'))) {
            $data['key'] = $request->input('key');
        }

        if ($request->hasEnabled()) {
            $data['enabled'] = $request->enabled();
        }

        $model->update($data);

        return to_route('settings.ai.edit')->with('success', 'Proveedor actualizado.');
    }

    public function destroyProvider(Request $request, int $provider): RedirectResponse
    {
        $this->ownedProvider($request, $provider)->delete();

        return to_route('settings.ai.edit')->with('success', 'Proveedor eliminado.');
    }

    /**
     * Probe one provider with a minimal prompt and report the latency.
     *
     * The probe is not routed through `AiInsightRunner` (that runner exists for
     * insights): it wires the provider, runs a one-shot prompt on it and feeds
     * the circuit breaker. Failures — including timeouts and connection
     * errors — answer 200 with `ok: false` plus the error message, never a 500,
     * so the settings page can render the outcome inline.
     */
    public function testConnection(Request $request, int $provider): JsonResponse
    {
        $model = $this->ownedProvider($request, $provider);
        $health = app(AiHealthService::class);
        $startedAt = microtime(true);

        try {
            (new MegalomaniacAgent($request->user(), []))
                ->prompt(
                    'Respondé solo con OK.',
                    provider: AiProviderConfigurator::wire($model),
                    model: $model->model ?: ByoProviderMigrator::DEFAULT_MODEL,
                    timeout: self::TEST_TIMEOUT_SECONDS,
                );

            $health->markSuccess($model);

            return response()->json($this->testResult($model, $startedAt));
        } catch (Throwable $e) {
            $health->recordFailure($model, $e->getMessage());

            return response()->json($this->testResult($model, $startedAt, $e->getMessage()));
        }
    }

    /**
     * Persist the chain and/or the prompt layer of one scope.
     *
     * An empty `provider_chain` is stored as `[]` (the row is kept, not deleted):
     * an empty chain means "inherit", and keeping the row lets the prompt layer
     * of that scope live in the same place.
     */
    public function updateScope(UpdateAiScopeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $scope = $validated['scope'];

        unset($validated['scope']);

        if (array_key_exists('prompt', $validated) && blank($validated['prompt'])) {
            $validated['prompt'] = null;
        }

        UserAiScope::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'scope' => $scope],
            $validated,
        );

        return to_route('settings.ai.edit')->with('success', 'Asignación actualizada.');
    }

    /**
     * The prompt a scope would actually send: the agent base instructions plus
     * the composed layers for that scope.
     *
     * The base is built with an agent without tool groups, so the preview shows
     * the neutral instructions and never the runtime context of a turn (skills,
     * memory, thread documents).
     */
    public function previewPrompt(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'scope' => ['required', Rule::in(AiScope::values())],
        ]);

        $scope = AiScope::from($validated['scope']);
        $moduleKey = $scope->moduleKey();
        $surface = $scope->section() === 'surface' ? $scope->value : null;

        $composer = app(AiPromptComposer::class);

        return response()->json([
            'preview' => $composer->preview(
                (new MegalomaniacAgent($user, []))->instructions(),
                $composer->personalizationBlock($user, $moduleKey, $surface),
            ),
        ]);
    }

    /**
     * The user's providers, ordered by `sort_order`, never with the key.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function providersFor(User $user): array
    {
        $health = app(AiHealthService::class);

        return AiProvider::query()
            ->where('user_id', $user->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (AiProvider $provider) use ($health): array {
                $status = $health->statusFor($provider);

                return [
                    'id' => $provider->getKey(),
                    'name' => $provider->name,
                    'protocol' => $provider->protocol,
                    'url' => $provider->url,
                    'model' => $provider->model,
                    'embeddings_model' => $provider->embeddings_model,
                    'enabled' => (bool) $provider->enabled,
                    'sort_order' => (int) $provider->sort_order,
                    'has_key' => filled($provider->key),
                    'health' => $status === null ? null : [
                        'consecutive_failures' => (int) $status->consecutive_failures,
                        'broken_until' => $status->broken_until?->toIso8601String(),
                        'last_error' => $status->last_error,
                    ],
                ];
            })
            ->all();
    }

    /**
     * The 14 scopes, always materialized, in `AiScope::cases()` order.
     *
     * `chain` is what the user stored (`[]` = inherits) and `effective` is what
     * the resolver would pick today for that context, health filter included.
     * The resolver takes a (surface, module) pair, so a scope maps onto it with
     * this convention:
     *
     * - `surface:*` → that surface with no module;
     * - `module:*` → `surface:chat` plus the module, the canonical
     *   representation of a module assistant;
     * - `global` → the `global` scope alone, which resolves the global row (or
     *   the implicit chain) without borrowing another surface's chain.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function scopesFor(User $user): array
    {
        $rows = UserAiScope::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('scope');

        $resolver = app(AiScopeResolver::class);

        return array_map(function (AiScope $scope) use ($user, $rows, $resolver): array {
            $row = $rows->get($scope->value);

            $resolution = $resolver->resolve(
                $user,
                $scope->section() === 'module' ? AiScope::SurfaceChat : $scope,
                $scope->moduleKey(),
            );

            return [
                'scope' => $scope->value,
                'label' => $scope->label(),
                'section' => $scope->section(),
                'chain' => array_values($row?->provider_chain ?? []),
                'effective' => $resolution->chain
                    ->map(fn (AiProvider $provider): int => $provider->getKey())
                    ->all(),
                'prompt' => $row?->prompt,
                // Filled by the prompts tab through `settings.ai.prompts.preview`.
                'prompt_preview' => null,
            ];
        }, AiScope::cases());
    }

    /**
     * Module key => label for the module rows of the assignments tab.
     *
     * @return array<string, string>
     */
    protected function moduleLabels(): array
    {
        $labels = [];

        foreach (AiScope::moduleKeys() as $key) {
            $labels[$key] = AiScope::fromModuleKey($key)->label();
        }

        return $labels;
    }

    /**
     * JSON body of the connection probe.
     *
     * @return array{ok: bool, ms: int, error: string|null, provider: string}
     */
    protected function testResult(AiProvider $provider, float $startedAt, ?string $error = null): array
    {
        return [
            'ok' => $error === null,
            'ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $error === null ? null : Str::limit($error, self::TEST_ERROR_LENGTH),
            'provider' => $provider->name,
        ];
    }

    protected function nextSortOrder(User $user): int
    {
        return (int) AiProvider::query()
            ->where('user_id', $user->getKey())
            ->max('sort_order') + 1;
    }

    /**
     * Scope the provider lookup to the user so another user's provider 404s
     * instead of being editable.
     */
    protected function ownedProvider(Request $request, int $providerId): AiProvider
    {
        return AiProvider::query()
            ->where('user_id', $request->user()->getKey())
            ->findOrFail($providerId);
    }
}
