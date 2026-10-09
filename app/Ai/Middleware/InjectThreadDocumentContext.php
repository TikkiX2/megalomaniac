<?php

namespace App\Ai\Middleware;

use App\Models\ChatThread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectThreadDocumentContext
{
    public const HEADER = '--- Documentos del hilo (contexto) ---';

    public const HEADER_DEEP = '--- Documentos del hilo, contexto extendido ---';

    public function __construct(protected ?ChatThread $thread, protected string $query) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $deep = (bool) ($this->thread?->deep_context ?? false);

        $context = $deep
            ? $this->thread?->documentContext($this->query, 30, true)
            : $this->thread?->documentContext($this->query);

        if (blank($context)) {
            return $next($prompt);
        }

        return $next($prompt->append(($deep ? self::HEADER_DEEP : self::HEADER)."\n".$context));
    }
}
