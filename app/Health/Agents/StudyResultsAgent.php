<?php

declare(strict_types=1);

namespace App\Health\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class StudyResultsAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string $studyTitle,
        public ?string $studyType = null,
        public ?string $performedAt = null,
    ) {}

    public function instructions(): string
    {
        return <<<TEXT
Sos un asistente médico que extrae resultados estructurados de estudios de laboratorio o imágenes de informes.

Estudio: {$this->studyTitle}
Tipo: {$this->studyType}
Fecha: {$this->performedAt}

Analizá las imágenes proporcionadas (páginas del PDF) y listá cada resultado detectado.

Respondé SOLO con un JSON array de objetos con las siguientes claves:
- analyte (string) nombre del analito o parámetro
- value (string) valor medido tal como aparece
- unit (string|null) unidad
- reference_range (string|null) rango de referencia
- flag (string|null) uno de: low, normal, high, unknown

No agregues texto fuera del JSON. Si no se detectan resultados, devolvé [].
TEXT;
    }
}
