<?php

declare(strict_types=1);

namespace App\Rules;

use App\Inspiration\Support\UrlSafety;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only public `http`/`https` URLs.
 *
 * Data/file/ftp schemes, protocol-relative URLs and hostnames that are (or
 * resolve to) loopback, RFC1918, CGNAT, link-local, multicast or reserved
 * addresses are rejected, so the queued download jobs cannot be turned into an
 * SSRF primitive.
 */
class SecureHttpUrl implements ValidationRule
{
    /**
     * @param  Closure(string): array<int, string>|null  $resolver
     */
    public function __construct(private readonly ?Closure $resolver = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! UrlSafety::hasHttpScheme($value)) {
            $fail('URL inválida');

            return;
        }

        if (! UrlSafety::isPublicHttpUrl($value, $this->resolver)) {
            $fail('URL interna no permitida');
        }
    }
}
