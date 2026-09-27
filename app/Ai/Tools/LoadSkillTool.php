<?php

namespace App\Ai\Tools;

use App\Ai\Skills\SkillCatalog;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LoadSkillTool implements Tool
{
    public function __construct(
        protected User $user,
        protected SkillCatalog $catalog,
    ) {}

    public function description(): Stringable|string
    {
        return 'Carga las instrucciones completas de una skill del usuario (procedimientos y guías reutilizables). Usala cuando la tarea encaje con la descripción de una skill disponible, antes de responder.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string()
                ->required()
                ->description('Clave (key) de la skill a cargar, tal como figura en la lista de skills disponibles'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $key = trim((string) ($request['skill'] ?? ''));

        $instructions = $key === '' ? null : $this->catalog->instructionsFor($this->user, $key);

        if ($instructions !== null) {
            return $instructions;
        }

        $available = collect($this->catalog->summariesFor($this->user))
            ->map(fn (array $skill): string => $skill['key'])
            ->implode(', ');

        return 'No existe la skill "'.$key.'" o está desactivada.'
            .($available === '' ? '' : ' Skills disponibles: '.$available.'.');
    }
}
