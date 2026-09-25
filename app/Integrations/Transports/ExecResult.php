<?php

namespace App\Integrations\Transports;

final readonly class ExecResult
{
    public function __construct(
        public bool $ok,
        public int $exitCode,
        public string $output,
        public ?string $error = null,
    ) {}
}
