<?php

namespace App\Integrations\Actions;

final readonly class ActionResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public bool $ok,
        public string $summary,
        public ?array $data = null,
        public ?string $error = null,
        public ?int $approvalId = null,
        public bool $pending = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function success(string $summary, ?array $data = null): self
    {
        return new self(ok: true, summary: $summary, data: $data);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function failure(string $error, ?array $data = null): self
    {
        return new self(ok: false, summary: $error, data: $data, error: $error);
    }

    public static function pending(int $approvalId, string $summary): self
    {
        return new self(ok: true, summary: $summary, approvalId: $approvalId, pending: true);
    }
}
