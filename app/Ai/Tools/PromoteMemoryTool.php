<?php

namespace App\Ai\Tools;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PromoteMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
    ) {}

    public function description(): Stringable|string
    {
        return 'Promueve una memoria del hilo a la memoria general del usuario, para que valga en todas las conversaciones. Solo aplica a memorias con scope "thread".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->required()
                ->description('Id de la memoria del hilo a promover'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $memory = Memory::query()
            ->forUser($this->user)
            ->whereKey(trim((string) ($request['id'] ?? '')))
            ->first();

        if ($memory === null) {
            return 'No encontré esa memoria.';
        }

        if ($memory->scope !== MemoryScope::Thread) {
            return 'Solo se pueden promover memorias de un hilo.';
        }

        try {
            $this->catalog->promote($memory);
        } catch (ValidationException $exception) {
            return 'No se pudo promover: '.implode(' ', array_merge(...array_values($exception->errors())));
        }

        return json_encode(['success' => true, 'message' => 'Memoria promovida a general.']);
    }
}
