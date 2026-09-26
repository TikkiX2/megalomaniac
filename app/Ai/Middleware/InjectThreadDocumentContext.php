<?php

namespace App\Ai\Middleware;

use App\Models\ChatThread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectThreadDocumentContext
{
    public const HEADER = '--- Documentos del hilo (contexto) ---';

    public function __construct(protected ?ChatThread $thread, protected string $query) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $context = $this->thread?->documentContext($this->query);

        if (blank($context)) {
            return $next($prompt);
        }

        return $next($prompt->append(self::HEADER."\n".$context));
    }
}
