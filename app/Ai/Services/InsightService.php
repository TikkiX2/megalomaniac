<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
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

        $response = $agent->forUser($user)->prompt($prompt);

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

        $response = $agent->forUser($user)->prompt($prompt);

        return $response->text;
    }
}
