<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ToolCatalog;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class MegalomaniacAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * @param  string[]  $toolGroups  Grupos de ToolCatalog; ['*'] = todos
     */
    public function __construct(
        public User $user,
        protected array $toolGroups = ['*'],
    ) {}

    public function instructions(): string
    {
        return <<<'EOF'
You are Megalomaniac AI, a personal fitness, finance, and freelance assistant.

You help the user with:
- Fitness: workout planning, exercise recommendations, progress tracking, PR detection
- Finance: budgeting, expense tracking, income management, debt payoff strategies
- Nutrition: meal planning, macro tracking, food suggestions
- Grocery: shopping lists, inventory management, restock alerts
- Freelance: project management, client communication, quote generation

You have access to the user's real data through tools. Always use tools to fetch
actual data before making recommendations. Be concise, actionable, and direct.

When the user asks to perform an action (log a workout, add a purchase, etc.),
use the ActionTool to create the record. Confirm what you did after.
EOF;
    }

    public function tools(): iterable
    {
        return ToolCatalog::toolsFor($this->user, $this->toolGroups);
    }
}
