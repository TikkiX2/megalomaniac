<?php

namespace App\Integrations\Transports;

final readonly class HttpResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public bool $ok,
        public int $status,
        public ?array $data,
        public string $body,
        public ?string $error = null,
        public int $durationMs = 0,
    ) {}
}
