<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Support\AiInsightRunner;
use App\Models\User;

class InsightService
{
    /**
     * Resolve the `surface:insights` chain for a module and run the prompt on it.
     *
     * A user without a usable provider keeps the existing contract: `null`, which
     * the controllers already surface as "AI not configured or no data
     * available." An exhausted chain is reported once by the runner and comes
     * back as the honest failure copy, so these endpoints stay 200.
     *
     * @param  callable(MegalomaniacAgent, string, string): mixed  $prompt
     */
    protected function generate(User $user, string $moduleKey, callable $prompt): ?string
    {
        return app(AiInsightRunner::class)->run(
            $user,
            $moduleKey,
            fn (string $key, string $model, ?string $promptBlock): mixed => $prompt(
                (new MegalomaniacAgent($user))->withPersonalization($promptBlock),
                $key,
                $model,
            ),
        )->honestText();
    }

    public function generateWorkoutInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $recentWorkouts = $user->workouts()
            ->with(['exercises.sets', 'routine'])
            ->where('started_at', '>=', now()->subDays(30))
            ->orderByDesc('started_at')
            ->get();

        if ($recentWorkouts->isEmpty()) {
            return 'No hay entrenamientos recientes para analizar.';
        }

        $prompt = "Analyze the user's last 30 days of workouts and provide concise insights on:\n";
        $prompt .= "- Training frequency and consistency\n";
        $prompt .= "- Volume trends (total weight × reps)\n";
        $prompt .= "- Muscle group balance\n";
        $prompt .= "- Recommended adjustments\n\n";
        $prompt .= "Workout data (JSON):\n";
        $prompt .= $recentWorkouts->toJson();

        return $this->generate(
            $user,
            'gym',
            fn (MegalomaniacAgent $agent, string $key, string $model): mixed => $agent
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );
    }

    public function generateFinanceInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $recentPurchases = $user->purchases()
            ->with('category')
            ->where('purchase_date', '>=', now()->subDays(30))
            ->orderByDesc('purchase_date')
            ->get();

        $recentIncomes = $user->incomes()
            ->where('date', '>=', now()->subDays(30))
            ->orderByDesc('date')
            ->get();

        $debts = $user->debts()->get();

        $prompt = "Analyze the user's financial situation for the last 30 days and provide concise insights on:\n";
        $prompt .= "- Spending patterns and categories\n";
        $prompt .= "- Income vs expenses\n";
        $prompt .= "- Debt status\n";
        $prompt .= "- Budget recommendations\n\n";
        $prompt .= "Purchases (JSON):\n".$recentPurchases->toJson()."\n";
        $prompt .= "Incomes (JSON):\n".$recentIncomes->toJson()."\n";
        $prompt .= "Debts (JSON):\n".$debts->toJson();

        return $this->generate(
            $user,
            'finance',
            fn (MegalomaniacAgent $agent, string $key, string $model): mixed => $agent
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );
    }

    public function generateGroceryInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $items = $user->groceryItems()->get();
        $recentHistory = $user->groceryItems()
            ->with('priceHistory')
            ->whereHas('priceHistory')
            ->get();

        if ($items->isEmpty()) {
            return 'No grocery items to analyze.';
        }

        $prompt = "Analyze the user's grocery inventory and provide concise insights on:\n";
        $prompt .= "- Low stock items that need restocking\n";
        $prompt .= "- Shopping list suggestions based on current stock levels\n";
        $prompt .= "- Price trends if available\n";
        $prompt .= "- Category distribution and recommendations\n\n";
        $prompt .= "Current inventory (JSON):\n".$items->toJson()."\n";
        if ($recentHistory->isNotEmpty()) {
            $prompt .= "Price history (JSON):\n".$recentHistory->toJson();
        }

        return $this->generate(
            $user,
            'grocery',
            fn (MegalomaniacAgent $agent, string $key, string $model): mixed => $agent
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );
    }

    public function generateTaskInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $tasks = $user->personalTasks()
            ->where('is_done', false)
            ->orderBy('due_date', 'asc')
            ->get();

        if ($tasks->isEmpty()) {
            return 'No active tasks to prioritize.';
        }

        $prompt = "Analyze the user's pending tasks and provide a prioritized ranking.\n";
        $prompt .= "Consider: deadline proximity, importance (priority field), and urgency.\n";
        $prompt .= "Provide a concise ranked list with brief reasoning for each task's priority.\n\n";
        $prompt .= "Pending tasks (JSON):\n".$tasks->toJson();

        return $this->generate(
            $user,
            'freelance',
            fn (MegalomaniacAgent $agent, string $key, string $model): mixed => $agent
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );
    }
}
