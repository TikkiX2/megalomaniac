<?php

namespace App\Http\Controllers;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    public function __construct(
        protected InsightService $insightService,
    ) {}

    public function workout(Request $request): JsonResponse
    {
        $insight = $this->insightService->generateWorkoutInsights($request->user());

        return response()->json([
            'insight' => $insight,
            'message' => $insight ? null : 'AI not configured or no data available.',
        ]);
    }

    public function finance(Request $request): JsonResponse
    {
        $insight = $this->insightService->generateFinanceInsights($request->user());

        return response()->json([
            'insight' => $insight,
            'message' => $insight ? null : 'AI not configured or no data available.',
        ]);
    }

    public function nutrition(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->ai_enabled) {
            return response()->json([
                'insight' => null,
                'message' => 'AI not configured.',
            ]);
        }

        $recentMeals = $user->mealLogs()
            ->with('items.food')
            ->where('date', '>=', now()->subDays(7))
            ->orderByDesc('date')
            ->get();

        if ($recentMeals->isEmpty()) {
            return response()->json([
                'insight' => null,
                'message' => 'No meal data available for analysis.',
            ]);
        }

        $agent = new MegalomaniacAgent($user);
        $prompt = "Analyze the user's nutrition for the last 7 days and provide concise insights on:\n";
        $prompt .= "- Calorie intake trends\n";
        $prompt .= "- Macro balance (protein, carbs, fats)\n";
        $prompt .= "- Meal timing patterns\n";
        $prompt .= "- Recommendations to reach goals\n\n";
        $prompt .= "Meal data (JSON):\n".$recentMeals->toJson();

        $response = $agent->forUser($user)->prompt($prompt);

        return response()->json([
            'insight' => $response->text,
            'message' => null,
        ]);
    }
}
