<?php

namespace App\Integrations\Actions;

use App\Models\User;

final readonly class ExecutionContext
{
    public function __construct(
        public ?User $actor,
        public string $source,
        public bool $preApproved = false,
        public ?string $rationale = null,
    ) {}

    public static function forUi(User $user): self
    {
        return new self(actor: $user, source: 'ui', preApproved: true);
    }

    public static function forAgent(User $user, ?string $rationale = null): self
    {
        return new self(actor: $user, source: 'chat', rationale: $rationale);
    }

    public static function forSchedule(User $user, ?string $rationale = null): self
    {
        return new self(actor: $user, source: 'schedule', rationale: $rationale);
    }
}
