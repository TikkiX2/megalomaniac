<?php

declare(strict_types=1);

namespace App\Inspiration\Auth;

use App\Inspiration\Exceptions\SourceException;

/**
 * Simulated login flows, one class per platform.
 *
 * Each flow performs the platform's own login POST with the user's email and
 * password, captures the session cookies the response sets, and returns them
 * as a raw `Cookie:` header string. Flows are fragile by nature (the platform
 * may introduce CAPTCHA, 2FA or change its auth endpoints), so a flow that
 * cannot complete must throw a SourceException with a human-readable message
 * — the caller then tells the user to paste the session cookie instead.
 */
interface LoginFlow
{
    /**
     * @throws SourceException when the platform rejected or changed the login
     */
    public function login(string $email, string $password): string;
}
