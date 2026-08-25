<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\MealLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class NutritionReadTool extends Tool
{
    protected string $name = 'nutrition-read';

    protected string $description = 'Read the authenticated user\'s meal log history with optional date range filter.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Number of past days to include (default: 7)')->minimum(1)->maximum(90),
            'limit' => $schema->integer()->description('Maximum number of meal logs to return (default: 20)')->minimum(1)->maximum(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $days = (int) $request->get('days', 7);
        $limit = (int) $request->get('limit', 20);

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $mealLogs = MealLog::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays($days))
            ->with(['items.food'])
            ->latest('date')
            ->limit($limit)
            ->get()
            ->map(fn (MealLog $log) => [
                'id' => $log->id,
                'date' => $log->date->toDateString(),
                'meal_type' => $log->meal_type,
                'total_calories' => $log->total_calories,
                'total_macros' => $log->total_macros,
                'items' => $log->items->map(fn ($item) => [
                    'food_name' => $item->food?->name,
                    'quantity' => $item->quantity,
                    'calories' => $item->calories_snapshot,
                    'protein' => $item->protein_snapshot,
                    'carbs' => $item->carbs_snapshot,
                    'fats' => $item->fats_snapshot,
                ])->all(),
            ]);

        return Response::structured([
            'meal_logs' => $mealLogs->all(),
            'count' => $mealLogs->count(),
            'days' => $days,
            'limit' => $limit,
        ]);
    }
}
