<?php

namespace App\Integrations\Actions;

final readonly class Param
{
    /**
     * @param  string[]|null  $enum
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public string $description,
        public ?array $enum = null,
        public mixed $default = null,
        public bool $sensitive = false,
    ) {}
}
