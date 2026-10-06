<?php

declare(strict_types=1);

namespace App\Inspiration\Auth;

use App\Inspiration\Exceptions\SourceException;
use App\Models\InspirationAuth;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Encrypted per-source session credentials (cookies as the primary mechanism,
 * simulated login as the alternative).
 *
 * Everything written here is encrypted at rest (`data` column, Crypt) and
 * never leaves the server: the settings payload exposes only presence flags
 * (`has_auth`, `auth_type`, `auth_invalid`).
 */
class InspirationAuthStore
{
    public function isSupported(string $source): bool
    {
        return in_array($source, $this->supportedSources(), true);
    }

    /**
     * @return array<int, string>
     */
    public function supportedSources(): array
    {
        $sources = config('inspiration.auth_sources', []);

        return is_array($sources) ? array_values(array_filter($sources, 'is_string')) : [];
    }

    public function saveCookie(User $user, string $source, string $cookie): void
    {
        $this->store($user, $source, 'cookie', json_encode([
            'cookies' => trim($cookie),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @throws SourceException when the platform rejected the login or the
     *                         source has no implemented simulated flow
     */
    public function saveLogin(User $user, string $source, string $email, string $password): void
    {
        $flow = $this->flowFor($source);

        $cookies = $flow->login($email, $password);

        $this->store($user, $source, 'login', json_encode([
            'email' => $email,
            'password' => $password,
            'cookies' => $cookies,
        ], JSON_THROW_ON_ERROR));
    }

    public function disconnect(User $user, string $source): void
    {
        InspirationAuth::query()
            ->where('user_id', $user->id)
            ->where('source', $source)
            ->delete();
    }

    public function hasCredential(User $user, string $source): bool
    {
        return $this->row($user, $source) !== null;
    }

    /**
     * @return array{type: string, invalid: bool}|null
     */
    public function status(User $user, string $source): ?array
    {
        $row = $this->row($user, $source);

        return $row === null ? null : [
            'type' => $row->type,
            'invalid' => (bool) $row->invalid,
        ];
    }

    /**
     * Decrypted Cookie: header value for the source, or null when there is no
     * credential or the stored session is marked invalid.
     */
    public function cookiesFor(User $user, string $source): ?string
    {
        $row = $this->row($user, $source);

        if ($row === null) {
            return null;
        }

        $payload = $this->decrypt($row);

        if ($row->type === 'cookie') {
            return $row->invalid ? null : ($payload['cookies'] ?? null);
        }

        // Login type: one automatic re-login per hour (max 3 attempts) when the
        // stored session went stale; otherwise the UI asks the user to
        // reconectar.
        if ($row->invalid) {
            $this->attemptRelogin($user, $source);
            $row = $this->row($user, $source);
            $payload = $row === null ? [] : $this->decrypt($row);
        }

        if ($row === null || $row->invalid) {
            return null;
        }

        return $payload['cookies'] ?? null;
    }

    public function markInvalid(User $user, string $source): void
    {
        $row = $this->row($user, $source);

        if ($row === null) {
            return;
        }

        $row->update(['invalid' => true]);
    }

    public function clearInvalid(User $user, string $source): void
    {
        $row = $this->row($user, $source);

        if ($row === null) {
            return;
        }

        $row->update(['invalid' => false]);
    }

    private function store(User $user, string $source, string $type, string $payloadJson): void
    {
        if (! $this->isSupported($source)) {
            throw new SourceException("inspiration: la fuente {$source} no acepta credenciales de sesión");
        }

        InspirationAuth::updateOrCreate(
            ['user_id' => $user->id, 'source' => $source],
            [
                'type' => $type,
                'data' => Crypt::encryptString($payloadJson),
                'invalid' => false,
                'updated_at' => now(),
            ],
        );
    }

    private function attemptRelogin(User $user, string $source): void
    {
        $row = $this->row($user, $source);

        if ($row === null || $row->type !== 'login') {
            return;
        }

        RateLimiter::attempt('inspiration:relogin:'.$source.':'.$user->id, 3, function () use ($source, $row): void {
            try {
                $payload = $this->decrypt($row);
                $flow = $this->flowFor($source);
                $cookies = $flow->login($payload['email'] ?? '', $payload['password'] ?? '');

                if ($cookies === '') {
                    return;
                }

                $row->update([
                    'data' => Crypt::encryptString(json_encode([
                        'email' => $payload['email'] ?? '',
                        'password' => $payload['password'] ?? '',
                        'cookies' => $cookies,
                    ], JSON_THROW_ON_ERROR)),
                    'invalid' => false,
                    'updated_at' => now(),
                ]);
            } catch (Throwable) {
                // Keep invalid; the UI shows "reconectar".
            }
        });
    }

    private function flowFor(string $source): LoginFlow
    {
        return match ($source) {
            'behance' => app(BehanceLogin::class),
            default => throw new SourceException("inspiration: login simulado no disponible para {$source} — pegá la cookie de sesión"),
        };
    }

    private function row(User $user, string $source): ?InspirationAuth
    {
        return InspirationAuth::query()
            ->where('user_id', $user->id)
            ->where('source', $source)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function decrypt(InspirationAuth $row): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($row->data), true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }
}
