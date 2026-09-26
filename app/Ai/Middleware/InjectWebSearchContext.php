<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectWebSearchContext
{
    public const HEADER = '--- Resultados web (contexto) ---';

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    public function __construct(protected array $results) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        if ($this->results === []) {
            return $next($prompt);
        }

        $block = collect($this->results)
            ->map(function (array $result): string {
                $number = (string) ($result['n'] ?? '');
                $title = (string) ($result['title'] ?? '');
                $url = (string) ($result['url'] ?? '');
                $content = mb_substr((string) ($result['content'] ?? ''), 0, 1000);

                return '['.$number.'] '.$title.' — '.$url."\n".$content;
            })
            ->implode("\n\n");

        return $next($prompt->append(self::HEADER."\n".$block));
    }
}
