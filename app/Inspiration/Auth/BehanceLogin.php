<?php

declare(strict_types=1);

namespace App\Inspiration\Auth;

use App\Inspiration\Exceptions\SourceException;
use App\Inspiration\Scraping\ScraperClient;

/**
 * Behance simulated login.
 *
 * Behance's web session endpoint accepts a JSON POST and answers with the
 * session cookie on success. The flow is best-effort: Adobe may redirect to
 * auth/2FA or CAPTCHA at any time, in which case login() throws and the UI
 * tells the user to paste their cookie instead.
 */
final class BehanceLogin implements LoginFlow
{
    private const LOGIN_URL = 'https://www.behance.net/api/v3/authentication/session';

    public function __construct(
        private readonly ScraperClient $client = new ScraperClient,
    ) {}

    public function login(string $email, string $password): string
    {
        try {
            $cookies = $this->client->postJson(self::LOGIN_URL, [
                'username' => $email,
                'password' => $password,
            ]);

            if ($cookies === '') {
                throw new SourceException('behance: no session cookie en la respuesta (¿CAPTCHA o 2FA?)');
            }

            return $cookies;
        } catch (SourceException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new SourceException('behance: no se pudo iniciar sesión ('.$exception->getMessage().')', previous: $exception);
        }
    }
}
