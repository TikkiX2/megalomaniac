<?php

namespace App\Integrations\Mcp;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\Param;
use App\Integrations\Enums\ActionAccess;
use Laravel\Mcp\Client\Primitives\Tool;

class McpToolMapper
{
    public function toAction(Tool $tool): Action
    {
        $annotations = $tool->annotations;

        $access = match (true) {
            ($annotations['destructiveHint'] ?? false) === true => ActionAccess::Destructive,
            ($annotations['readOnlyHint'] ?? false) === true => ActionAccess::Read,
            default => ActionAccess::Write,
        };

        return new Action(
            key: 'tools.'.substr($tool->name, 0, 80),
            label: $tool->title ?? $tool->name,
            description: $tool->description ?? 'Herramienta MCP',
            access: $access,
            params: $this->params($tool->inputSchema),
            returns: 'Resultado de la herramienta MCP.',
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return Param[]
     */
    public function params(array $schema): array
    {
        $properties = (array) ($schema['properties'] ?? []);
        $required = (array) ($schema['required'] ?? []);
        $params = [];

        foreach ($properties as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $type = match ($definition['type'] ?? 'string') {
                'integer' => 'integer',
                'number' => 'number',
                'boolean' => 'boolean',
                'array', 'object' => 'array',
                default => 'string',
            };

            $params[] = new Param(
                name: (string) $name,
                type: $type,
                required: in_array($name, $required, true),
                description: (string) ($definition['description'] ?? ''),
                enum: isset($definition['enum']) && is_array($definition['enum'])
                    ? array_values(array_map('strval', $definition['enum']))
                    : null,
                default: $definition['default'] ?? null,
            );
        }

        return $params;
    }
}
