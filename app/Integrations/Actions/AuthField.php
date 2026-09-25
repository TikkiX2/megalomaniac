<?php

namespace App\Integrations\Actions;

final readonly class AuthField
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public bool $required = true,
        public ?string $help = null,
        public array $options = [],
    ) {}
}
