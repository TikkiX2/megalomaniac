<?php

namespace App\Ai\Middleware;

use App\Models\ChatThread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectThreadDocumentContext
{
    public function __construct(protected ?ChatThread $thread, protected string $query) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $context = $this->thread?->documentContext($this->query);

        if (blank($context)) {
            return $next($prompt);
        }

        return $next($prompt->append("--- Documentos del hilo (contexto) ---\n".$context));
    }
}
