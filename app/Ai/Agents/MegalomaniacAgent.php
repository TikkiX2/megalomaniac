<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\InjectThreadDocumentContext;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\ToolCatalog;
use App\Models\ChatThread;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class MegalomaniacAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    protected ?string $documentQuery = null;

    protected ?string $resumeDocumentContext = null;

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
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if (! $this->thread instanceof ChatThread || blank($this->documentQuery)) {
            return [];
        }

        return [new InjectThreadDocumentContext($this->thread, $this->documentQuery)];
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

        return $instructions;
    }

    public function tools(): iterable
    {
        // AskUserTool is always available: the model must be able to pause and
        // ask a question even when the resolved policy filters out DB tools.
        return [
            ...ToolCatalog::toolsFor($this->user, $this->toolGroups),
            new AskUserTool,
        ];
    }
}
