<?php

namespace App\Feed;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class FeedDigestAgent implements Agent
{
    use Promptable;

    /**
     * @param  array<int, array{title: string, url: string, source: string, summary: string}>  $items
     */
    public function __construct(public array $items) {}

    public function instructions(): string
    {
        $items = json_encode($this->items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<TEXT
        Sos el curador del feed de noticias del usuario. Con los items de hoy (JSON):
        {$items}

        Escribí un digest breve en markdown (máximo 200 palabras):
        - Un párrafo de introducción con el tema del día.
        - Una lista con los 3-5 items más relevantes: **título** y una línea de por qué importa.
        - No inventes items ni datos que no estén en la lista.
        TEXT;
    }
}
