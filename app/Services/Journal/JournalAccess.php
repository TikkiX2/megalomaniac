<?php

declare(strict_types=1);

namespace App\Services\Journal;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JournalAccess
{
    public const string SESSION_KEY = 'journal_unlocked_until';

    public const int UNLOCK_SECONDS = 900;

    public function unlocked(Request $request): bool
    {
        return $this->expiresAt($request) !== null;
    }

    public function unlock(Request $request): void
    {
        $request->session()->put(
            self::SESSION_KEY,
            now()->addSeconds(self::UNLOCK_SECONDS)->timestamp,
        );
    }

    public function lock(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function expiresAt(Request $request): ?CarbonImmutable
    {
        $timestamp = $request->session()->get(self::SESSION_KEY);

        if (! is_numeric($timestamp)) {
            return null;
        }

        if ((int) $timestamp <= now()->timestamp) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $timestamp);
    }

    /**
     * Reject mutations over existing private content while the journal is locked.
     */
    public function assertUnlockedForPrivate(Request $request, bool $isPrivate): void
    {
        if (! $isPrivate || $this->unlocked($request)) {
            return;
        }

        if ($request->expectsJson()) {
            abort(423);
        }

        throw ValidationException::withMessages([
            'password' => 'Desbloqueá el diario para editar esta memoria.',
        ]);
    }
}
