<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Support\AiProviderResolver;
use App\Models\User;

class InsightService
{
    public function generateWorkoutInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $agent = new MegalomaniacAgent($user);
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

        [$provider, $model] = AiProviderResolver::for($user);

        $response = $agent->forUser($user)->prompt($prompt, provider: $provider, model: $model);

        return $response->text;
    }

    public function generateFinanceInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $agent = new MegalomaniacAgent($user);
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

        [$provider, $model] = AiProviderResolver::for($user);

        $response = $agent->forUser($user)->prompt($prompt, provider: $provider, model: $model);

        return $response->text;
    }

    public function generateGroceryInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $agent = new MegalomaniacAgent($user);
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

        [$provider, $model] = AiProviderResolver::for($user);

        $response = $agent->forUser($user)->prompt($prompt, provider: $provider, model: $model);

        return $response->text;
    }

    public function generateTaskInsights(User $user): ?string
    {
        if (! $user->ai_enabled) {
            return null;
        }

        $agent = new MegalomaniacAgent($user);
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

        [$provider, $model] = AiProviderResolver::for($user);

        $response = $agent->forUser($user)->prompt($prompt, provider: $provider, model: $model);

        return $response->text;
    }
}
