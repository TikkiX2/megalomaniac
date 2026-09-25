<?php

namespace App\Integrations\Actions;

final readonly class ConnectionTestResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ok(string $message, array $meta = []): self
    {
        return new self(ok: true, message: $message, meta: $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function fail(string $message, array $meta = []): self
    {
        return new self(ok: false, message: $message, meta: $meta);
    }
}
