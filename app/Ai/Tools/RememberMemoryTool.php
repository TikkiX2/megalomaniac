<?php

namespace App\Ai\Tools;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
        protected ?ChatThread $thread = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Guarda un hecho o preferencia en la memoria del usuario (scope "global") o de esta conversación (scope "thread"). Usala cuando el usuario pida recordar algo o cuando aparezca un dato estable que convenga retener. No guardes credenciales, secretos ni datos efímeros.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->required()
                ->description('La memoria, en una sola idea y en el idioma del usuario (máx. 500 caracteres)'),
            'scope' => $schema->string()
                ->enum(['global', 'thread'])
                ->required()
                ->description('global = preferencias/objetivos/hechos duraderos del usuario; thread = detalles situacionales de esta conversación'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $scope = MemoryScope::tryFrom((string) ($request['scope'] ?? 'global')) ?? MemoryScope::Global;

        try {
            $memory = $this->catalog->remember(
                $this->user,
                (string) ($request['content'] ?? ''),
                $scope,
                $this->thread,
            );
        } catch (ValidationException $exception) {
            return 'No se pudo guardar: '.implode(' ', array_merge(...array_values($exception->errors())));
        }

        return json_encode([
            'success' => true,
            'id' => $memory->id,
            'scope' => $memory->scope->value,
            'message' => 'Memoria guardada.',
        ]);
    }
}
