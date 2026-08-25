<?php

namespace App\Ai\Tools;

use App\Models\MealLog;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class NutritionQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s nutrition data: meal logs, macros, calories. Use this to answer questions about diet, nutrition, and meal tracking.';
    }

    public function handle(Request $request): Stringable|string
    {
        $days = $request['days'] ?? 7;

        $logs = MealLog::with('items.food')
            ->where('user_id', $this->user->id)
            ->where('date', '>=', now()->subDays($days))
            ->latest('date')
            ->limit(14)
            ->get();

        if ($logs->isEmpty()) {
            return 'No meal logs found for the specified period.';
        }

        $summary = [
            'total_days' => $logs->pluck('date')->unique()->count(),
            'total_calories' => $logs->sum('total_calories'),
            'avg_calories_per_day' => round($logs->sum('total_calories') / max($logs->pluck('date')->unique()->count(), 1)),
            'logs' => $logs->toArray(),
        ];

        return json_encode($summary, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Number of days to look back (default: 7)')
                ->default(7),
        ];
    }
}
