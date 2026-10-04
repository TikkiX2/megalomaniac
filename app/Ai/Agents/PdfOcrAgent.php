<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Transcribe el texto visible en las páginas rasterizadas de un PDF sin capa
 * de texto (escaneado o cifrado). El texto transcrito se vuelve a indexar
 * para que el asesor pueda responder preguntas sobre el documento.
 */
class PdfOcrAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string $fileName,
    ) {}

    public function instructions(): string
    {
        return <<<TEXT
Sos un motor de OCR. Te envío las páginas rasterizadas del documento "{$this->fileName}".

Transcribí TODO el texto visible de manera literal y completa, respetando el orden de las páginas.
Incluí números, tablas y rótulos. No resumas ni interpretes: solo transcribí.

Contestá SOLO con el texto transcrito, sin comentarios ni encabezados.
Si las imágenes no contienen texto legible, respondé vacío.
TEXT;
    }
}
