<?php

namespace App\Ai\Agents;

use App\Ai\Memory\MemoryCatalog;
use App\Ai\Middleware\InjectThreadDocumentContext;
use App\Ai\Middleware\InjectWebSearchContext;
use App\Ai\Skills\SkillCatalog;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\ToolCatalog;
use App\Models\ChatThread;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

class MegalomaniacAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations {
        messages as conversationMessages;
    }

    protected ?string $documentQuery = null;

    protected ?string $resumeDocumentContext = null;

    /**
     * Results of the forced "search always" pre-search for this turn.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $webSearchResults = [];

    /**
     * Skills explicitly selected for this turn (composer picker).
     *
     * @var array<int, array{key: string, name: string, instructions: string}>
     */
    protected array $explicitSkills = [];

    /**
     * @param  string[]  $toolGroups  Grupos de ToolCatalog; ['*'] = todos
     */
    public function __construct(
        public User $user,
        protected array $toolGroups = ['*'],
        public ?ChatThread $thread = null,
    ) {}

    /**
     * Set the user message used to retrieve thread document context.
     */
    public function withDocumentContext(string $message): static
    {
        $this->documentQuery = $message;

        return $this;
    }

    /**
     * Attach resolved thread documents to the system instructions. Approval
     * resumes cannot use the middleware path because AgentPrompt::append()
     * returns the prompt untouched when it carries approval decisions, so the
     * block is delivered through instructions() instead.
     */
    public function withResumeDocumentContext(?string $context): static
    {
        $this->resumeDocumentContext = $context;

        return $this;
    }

    /**
     * Attach forced web search results to the prompt as read-only context.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public function withWebSearchContext(array $results): static
    {
        $this->webSearchResults = $results;

        return $this;
    }

    /**
     * Follow the given skills for this turn. The composer picker resolves them
     * in ChatService and hands the full instructions over.
     *
     * @param  array<int, array{key: string, name: string, instructions: string}>  $skills
     */
    public function withSkills(array $skills): static
    {
        $this->explicitSkills = $skills;

        return $this;
    }

    /**
     * Conversation history with the photos of all but the newest user message
     * replaced by a text placeholder. The provider re-embeds every attachment
     * as base64 on each request, so keeping every historical photo would grow
     * the payload (and PHP's memory) without bound.
     *
     * @return array<int, object>
     */
    public function messages(): array
    {
        $messages = array_values((array) $this->conversationMessages());

        $lastUserIndex = null;

        foreach ($messages as $index => $message) {
            if ($message instanceof UserMessage && $message->attachments->isNotEmpty()) {
                $lastUserIndex = $index;
            }
        }

        if ($lastUserIndex === null) {
            return $messages;
        }

        foreach ($messages as $index => $message) {
            if ($index === $lastUserIndex || ! $message instanceof UserMessage || $message->attachments->isEmpty()) {
                continue;
            }

            $images = $message->attachments->filter(fn (mixed $attachment): bool => $attachment instanceof Image);
            $message->attachments = $message->attachments->reject(fn (mixed $attachment): bool => $attachment instanceof Image)->values();

            if ($images->isEmpty()) {
                continue;
            }

            $message->content = trim(sprintf(
                '%s [%d %s omitidas del historial]',
                (string) $message->content,
                $images->count(),
                $images->count() === 1 ? 'imagen' : 'imágenes',
            ));
        }

        return $messages;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $middleware = [];

        if ($this->thread instanceof ChatThread && filled($this->documentQuery)) {
            $middleware[] = new InjectThreadDocumentContext($this->thread, $this->documentQuery);
        }

        if ($this->webSearchResults !== []) {
            $middleware[] = new InjectWebSearchContext($this->webSearchResults);
        }

        return $middleware;
    }

    public function instructions(): string
    {
        $instructions = <<<'EOF'
You are Megalomaniac AI, a personal fitness, finance, and freelance assistant.

You help the user with:
- Fitness: workout planning, exercise recommendations, progress tracking, PR detection
- Finance: budgeting, expense tracking, income management, debt payoff strategies
- Nutrition: meal planning, macro tracking, food suggestions
- Grocery: shopping lists, inventory management, restock alerts
- Freelance: project management, client communication, quote generation

You have access to the user's real data through tools. Always use tools to fetch
actual data before making recommendations. Be concise, actionable, and direct.

When the user asks to perform an action (log a workout, add a purchase, create
a project or task, move a task to a project, etc.), use the ActionTool to
create or update the record. Confirm what you did after.
EOF;

        if (filled($this->resumeDocumentContext)) {
            $instructions .= "\n\n".InjectThreadDocumentContext::HEADER."\n".$this->resumeDocumentContext;
        }

        if ($this->explicitSkills !== []) {
            $instructions .= "\n\nEl usuario pidió seguir estas skills en este turno. Aplicalas como guía principal:\n";

            foreach ($this->explicitSkills as $skill) {
                $instructions .= "\n### Skill: {$skill['name']} ({$skill['key']})\n{$skill['instructions']}\n";
            }
        }

        if ($this->skillsToolEnabled()) {
            $available = app(SkillCatalog::class)->summariesFor($this->user);

            if ($available !== []) {
                $instructions .= "\n\nSkills disponibles (cargá las instrucciones con load_skill cuando la tarea encaje):\n";

                foreach ($available as $skill) {
                    $instructions .= sprintf(
                        "- %s — %s\n",
                        $skill['key'],
                        $skill['description'] ?? $skill['name'],
                    );
                }
            }
        }

        if ($this->memoryEnabled()) {
            $memory = app(MemoryCatalog::class)->blockFor($this->user, $this->thread);

            if ($memory !== null) {
                $instructions .= "\n\n".$memory;
            }

            $instructions .= "\n\nSobre tu memoria: guardá hechos y preferencias duraderas del usuario en la memoria general (scope \"global\") y detalles situacionales de esta conversación en la del hilo (scope \"thread\"). Usá entradas cortas de una sola idea, preferí actualizar o borrar antes que duplicar, y nunca guardes credenciales, secretos ni datos de pago.";
        }

        return $instructions;
    }

    protected function memoryEnabled(): bool
    {
        return in_array('*', $this->toolGroups, true) || in_array('memory', $this->toolGroups, true);
    }

    protected function skillsToolEnabled(): bool
    {
        return in_array('*', $this->toolGroups, true) || in_array('skills', $this->toolGroups, true);
    }

    public function tools(): iterable
    {
        // AskUserTool is always available: the model must be able to pause and
        // ask a question even when the resolved policy filters out DB tools.
        return [
            ...ToolCatalog::toolsFor($this->user, $this->toolGroups, $this->thread),
            new AskUserTool,
        ];
    }
}
