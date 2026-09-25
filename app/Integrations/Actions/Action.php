<?php

namespace App\Integrations\Actions;

use App\Integrations\Enums\ActionAccess;

final readonly class Action
{
    /**
     * @param  Param[]  $params
     * @param  array<int, array<string, mixed>>  $examples
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public ActionAccess $access,
        public array $params = [],
        public array $examples = [],
        public ?string $returns = null,
    ) {}
}
