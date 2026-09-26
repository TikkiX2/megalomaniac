<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskUserTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Pregunta al usuario cuando necesites una decisión o dato que no puedes inferir. La conversación se pausa hasta que responda.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->required(),
            'options' => $schema->array()->items($schema->string()),
        ];
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required((string) ($request['question'] ?? 'Necesito una decisión tuya.'));
    }

    public function handle(Request $request): Stringable|string
    {
        // La respuesta del usuario llega como resultado del rechazo (Decision::reject($texto));
        // este handle no se ejecuta en el flujo normal.
        return 'El usuario no respondió.';
    }
}
