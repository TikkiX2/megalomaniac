<?php

namespace App\Ai\Tools;

use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ForgetMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
        protected ?ChatThread $thread = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Borra una memoria del usuario por id (preferido) o por texto (query). Si la búsqueda devuelve varias coincidencias, repetí la llamada con el id correcto.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id de la memoria a borrar (preferido)'),
            'query' => $schema->string()->description('Texto para encontrar la memoria cuando no tenés el id'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $id = trim((string) ($request['id'] ?? ''));

        if ($id !== '') {
            $memory = Memory::query()->forUser($this->user)->whereKey($id)->first();

            if ($memory === null) {
                return 'No encontré esa memoria.';
            }

            $this->catalog->forget($memory);

            return 'Memoria borrada.';
        }

        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'Indicá un id o un texto para buscar la memoria.';
        }

        $candidates = $this->catalog->candidatesFor($this->user, $this->thread, $query);

        if ($candidates->isEmpty()) {
            return 'No encontré memorias que coincidan con «'.$query.'».';
        }

        if ($candidates->count() === 1) {
            $memory = $candidates->first();

            $this->catalog->forget($memory);

            return 'Memoria borrada: «'.$memory->content.'».';
        }

        return 'Varias memorias coinciden; repetí con id: '.$candidates
            ->map(fn (Memory $memory): string => $memory->id.' — '.$memory->content)
            ->implode(' | ');
    }
}
