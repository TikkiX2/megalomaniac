<?php

namespace App\Ai\Agents;

use App\Ai\Memory\MemoryCatalog;
use App\Ai\Middleware\InjectThreadDocumentContext;
use App\Ai\Middleware\InjectWebSearchContext;
use App\Ai\Skills\SkillCatalog;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\ForgetMemoryTool;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\ManageAgentsTool;
use App\Ai\Tools\PromoteMemoryTool;
use App\Ai\Tools\RememberMemoryTool;
use App\Ai\Tools\ToolCatalog;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;

#[RepairToolCalls]
class MegalomaniacAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations {
        messages as conversationMessages;
    }

    /**
     * Mutating tools that do not implement the Approvable contract and would
     * otherwise be mistaken for read-only.
     *
     * @var array<int, class-string<Tool>>
     */
    private const WRITE_TOOLS = [
        RememberMemoryTool::class,
        ForgetMemoryTool::class,
        PromoteMemoryTool::class,
        ManageAgentsTool::class,
        IntegrationCallTool::class,
    ];

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
     * Editable prompt layers (global → module → surface) built by
     * AiPromptComposer, injected right after the base instructions.
     */
    protected ?string $personalizationBlock = null;

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
     * Attach the composed personalization layers (global → module → surface).
     * Passing null keeps the instructions untouched.
     */
    public function withPersonalization(?string $block): static
    {
        $this->personalizationBlock = $block;

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
        return $this->hydrateReasoning(
            $this->trimHistoricalImages(array_values((array) $this->conversationMessages()))
        );
    }

    /**
     * DeepSeek's thinking mode requires the raw `reasoning_content` of the
     * assistant turns with tool calls to be passed back on the next request.
     * The SDK only persists the paused turn's provider blocks; the display
     * reasoning accumulated by the app (meta.reasoning.text) is re-attached
     * here for every matching message.
     *
     * @param  array<int, object>  $messages
     * @return array<int, object>
     */
    protected function hydrateReasoning(array $messages): array
    {
        if ($this->conversationId === null) {
            return $messages;
        }

        $reasoningByContent = ChatMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get(['content', 'meta'])
            ->mapWithKeys(function (ChatMessage $message): array {
                $text = $message->meta['reasoning']['text'] ?? null;

                return [$message->content => is_string($text) && $text !== '' ? $text : null];
            })
            ->filter()
            ->all();

        if ($reasoningByContent === []) {
            return $messages;
        }

        foreach ($messages as $message) {
            if (! $message instanceof AssistantMessage || $message->toolCalls->isEmpty()) {
                continue;
            }

            if (filled($message->providerContentBlocks['reasoning_content'] ?? null)) {
                continue;
            }

            $text = $reasoningByContent[$message->content] ?? null;

            if (is_string($text) && $text !== '') {
                $message->providerContentBlocks['reasoning_content'] = $text;
            }
        }

        return $messages;
    }

    /**
     * Only the newest user message keeps its photos; older turns get a text
     * placeholder so the payload does not regrow without bound.
     *
     * @param  array<int, object>  $messages
     * @return array<int, object>
     */
    protected function trimHistoricalImages(array $messages): array
    {
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
EOF;

        if ($this->personalizationBlock !== null && trim($this->personalizationBlock) !== '') {
            $instructions .= "\n\n### Personalización del usuario\n".trim($this->personalizationBlock);
        }

        $instructions .= $this->writeInstructions();

        $instructions .= "\n\nCuando necesites una decisión o un dato que no podés inferir, usá AskUserTool: una sola pregunta por turno, con las opciones viables si las hay. La conversación se pausa hasta que el usuario responda, así que no mandes varias preguntas juntas. AskUserTool es exclusivo: cuando lo uses, llamalo SOLO, sin ningún otro tool_call en el mismo bloque. Si necesitás un dato antes de actuar, preguntá primero y recién después de la respuesta ejecutá la acción en el turno siguiente; nunca combines una pregunta con una escritura en la misma pausa.";

        if ($this->workoutEnabled()) {
            $instructions .= "\n\nFor training: start a workout (optionally from a routine so the template is copied), add exercises by name (new ones are created automatically), log sets with weight/reps/rpe, and finish the workout. To create a routine with several exercises, send all of them in the \"exercises\" array in a single create_routine call.";
        }

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

    protected function actionsEnabled(): bool
    {
        return in_array('*', $this->toolGroups, true) || in_array('actions', $this->toolGroups, true);
    }

    protected function workoutEnabled(): bool
    {
        return in_array('*', $this->toolGroups, true) || in_array('workout', $this->toolGroups, true);
    }

    /**
     * Write instructions matching the tool groups advertised for this turn, so
     * the model never mentions a tool that is not in its tool list.
     */
    protected function writeInstructions(): string
    {
        if ($this->actionsEnabled()) {
            return "\n\nWhen the user asks to perform an action, use the matching write tool: ".$this->writeToolNames($this->writeTools()).'. Confirm what you did after.';
        }

        $writeTools = $this->writeTools();

        if ($writeTools === []) {
            return "\n\nOnly read tools are available for this turn. If the user asks to modify data, explain that you can only read in this turn and suggest enabling the write tools.";
        }

        return "\n\nWhen the user asks to perform an action, use the matching write tool: ".$this->writeToolNames($writeTools).'. Confirm what you did after.';
    }

    /**
     * Write tool classes advertised by the selected groups: Approvable tools
     * (which pause for approval) plus the explicit non-Approvable mutators.
     * The module groups ship their own action tool, so a turn is only
     * read-only when none of the selected groups exposes a write tool.
     *
     * @return array<int, class-string<Tool>>
     */
    protected function writeTools(): array
    {
        $catalog = ToolCatalog::groups();
        $selected = in_array('*', $this->toolGroups, true) ? array_keys($catalog) : $this->toolGroups;

        $writeTools = [];

        foreach ($selected as $group) {
            foreach ($catalog[$group]['tools'] ?? [] as $class) {
                if (in_array($class, self::WRITE_TOOLS, true) || is_subclass_of($class, Approvable::class)) {
                    $writeTools[$class] ??= $class;
                }
            }
        }

        return array_values($writeTools);
    }

    /**
     * @param  array<int, class-string<Tool>>  $writeTools
     */
    protected function writeToolNames(array $writeTools): string
    {
        return implode(', ', array_map(
            fn (string $class): string => class_basename($class),
            $writeTools,
        ));
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
