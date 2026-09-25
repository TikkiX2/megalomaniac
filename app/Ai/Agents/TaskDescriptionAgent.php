<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class TaskDescriptionAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Eres un redactor técnico. Escribes descripciones de tareas claras y accionables en español.

        Reglas:
        - Devuelve SOLO el contenido de la descripción, sin saludos ni explicaciones.
        - Usa Markdown simple: párrafos, títulos con # y listas con -.
        - Máximo 200 palabras.
        - Incluye contexto, alcance y criterios de aceptación si el prompt lo permite.
        PROMPT;
    }
}
