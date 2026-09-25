<?php

namespace App\Feed;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class FeedScoringAgent implements Agent
{
    use Promptable;

    /**
     * @param  array<int, array{id: int, title: string, summary: string}>  $candidates
     * @param  array<string, float>  $topicWeights
     */
    public function __construct(
        public array $candidates,
        public array $topicWeights = [],
    ) {}

    public function instructions(): string
    {
        $candidates = json_encode($this->candidates, JSON_UNESCAPED_UNICODE);
        $weights = json_encode($this->topicWeights, JSON_UNESCAPED_UNICODE);

        return <<<TEXT
        Puntuá qué tan relevante es cada item del feed para este usuario, de 0 a 1.
        Intereses inferidos (palabra → peso): {$weights}
        Candidatos (JSON): {$candidates}

        Respondé SOLO con un JSON array de objetos {"id": <id>, "score": <0..1>}, sin texto extra.
        TEXT;
    }
}
