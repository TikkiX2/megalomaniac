<?php

declare(strict_types=1);

namespace App\Inspiration\Concerns;

/**
 * Adds the optional user session cookie to adapters that receive it through
 * the reserved `session_cookie` credential (hydrated by SourceManager from
 * the encrypted auth store). Sources without a session send no extra header.
 */
trait SendsSessionCookie
{
    protected function sessionCookie(): ?string
    {
        $value = $this->credentials['session_cookie'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    protected function sessionHeaders(): array
    {
        $cookie = $this->sessionCookie();

        return $cookie === null ? [] : ['Cookie' => $cookie];
    }
}
