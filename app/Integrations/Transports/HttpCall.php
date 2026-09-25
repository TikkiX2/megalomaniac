<?php

namespace App\Integrations\Transports;

final readonly class HttpCall
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $json
     */
    public function __construct(
        public string $method,
        public string $path = '',
        public array $query = [],
        public array $headers = [],
        public ?array $json = null,
        public ?string $body = null,
        public ?string $contentType = null,
        public ?int $timeout = null,
    ) {}
}
