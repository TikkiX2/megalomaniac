<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Support\AiInsightOutcome;
use App\Ai\Support\AiInsightRunner;
use App\Http\Controllers\Controller;
use App\Models\MealLog;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiFitnessController extends Controller
{
    /**
     * Resolve the `surface:insights` chain for a module and run one prompt on it.
     *
     * These endpoints already answer 200 with an honest "not configured" message
     * when AI is off, so an empty chain mirrors that instead of throwing from
     * the executor. An exhausted chain is reported once by the runner and comes
     * back flagged as exhausted, so the copy tells the truth without a 500.
     *
     * @param  callable(string $providerKey, string $model, string|null $promptBlock): mixed  $prompt
     */
    private function onInsightScope(User $user, string $moduleKey, callable $prompt): AiInsightOutcome
    {
        return app(AiInsightRunner::class)->run($user, $moduleKey, $prompt);
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $char = $text[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($text, $start, $i - $start + 1);

                    return json_decode($json, true);
                }
            }
        }

        return null;
    }

    public function suggestMeal(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->ai_enabled) {
            return response()->json([
                'suggestion' => null,
                'message' => 'AI not configured. Please enable AI in Settings.',
            ]);
        }

        $date = $request->query('date', now()->toDateString());

        $todayLogs = MealLog::with('items.food')
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->get();

        $currentCalories = 0;
        $currentProtein = 0;
        $currentCarbs = 0;
        $currentFats = 0;

        foreach ($todayLogs as $log) {
            foreach ($log->items as $item) {
                $currentCalories += (float) ($item->calories_snapshot ?? 0);
                $currentProtein += (float) ($item->protein_snapshot ?? 0);
                $currentCarbs += (float) ($item->carbs_snapshot ?? 0);
                $currentFats += (float) ($item->fats_snapshot ?? 0);
            }
        }

        $goals = ['calories' => 2400, 'protein' => 180, 'carbs' => 250, 'fats' => 70];

        $remaining = [
            'calories' => max(0, $goals['calories'] - $currentCalories),
            'protein' => max(0, $goals['protein'] - $currentProtein),
            'carbs' => max(0, $goals['carbs'] - $currentCarbs),
            'fats' => max(0, $goals['fats'] - $currentFats),
        ];

        $prompt = "Suggest a single meal that would help the user reach their remaining macro goals for today.\n\n";
        $prompt .= "Current macros consumed:\n";
        $prompt .= "- Calories: {$currentCalories} / {$goals['calories']}\n";
        $prompt .= "- Protein: {$currentProtein}g / {$goals['protein']}g\n";
        $prompt .= "- Carbs: {$currentCarbs}g / {$goals['carbs']}g\n";
        $prompt .= "- Fats: {$currentFats}g / {$goals['fats']}g\n\n";
        $prompt .= "Remaining to reach goals:\n";
        $prompt .= "- Calories: {$remaining['calories']} kcal\n";
        $prompt .= "- Protein: {$remaining['protein']}g\n";
        $prompt .= "- Carbs: {$remaining['carbs']}g\n";
        $prompt .= "- Fats: {$remaining['fats']}g\n\n";
        $prompt .= "Respond in JSON format with this exact structure:\n";
        $prompt .= '{"name": "Meal Name", "calories": 500, "protein": 40, "carbs": 50, "fats": 15, "reason": "Why this meal fits your remaining macros"}\n';
        $prompt .= 'Only return the JSON object, no other text.';

        $response = $this->onInsightScope(
            $user,
            'nutrition',
            fn (string $key, string $model, ?string $promptBlock): mixed => (new MegalomaniacAgent($user))
                ->withPersonalization($promptBlock)
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );

        if ($response->isEmpty()) {
            return response()->json([
                'suggestion' => null,
                'message' => $response->message('AI not configured. Please enable AI in Settings.'),
            ]);
        }

        $text = trim((string) $response->text());

        $json = $this->extractJson($text);

        if (! is_array($json) || ! isset($json['name'])) {
            return response()->json([
                'suggestion' => null,
                'message' => 'Could not generate a meal suggestion. Please try again.',
            ]);
        }

        return response()->json([
            'suggestion' => $json,
            'remaining' => $remaining,
        ]);
    }

    public function generateRoutine(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->ai_enabled) {
            return response()->json([
                'routine' => null,
                'message' => 'AI not configured. Please enable AI in Settings.',
            ]);
        }

        $recentWorkouts = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $user->id)
            ->where('started_at', '>=', now()->subDays(30))
            ->orderByDesc('started_at')
            ->limit(15)
            ->get();

        $existingRoutines = Routine::with('exercises')
            ->where('user_id', $user->id)
            ->get();

        $prompt = "Generate a weekly workout routine based on the user's recent training history.\n\n";
        $prompt .= "Recent workouts (JSON):\n";
        $prompt .= $recentWorkouts->toJson()."\n\n";
        $prompt .= "Existing routines (JSON):\n";
        $prompt .= $existingRoutines->toJson()."\n\n";
        $prompt .= "Respond in JSON format with this exact structure:\n";
        $prompt .= '{"name": "Routine Name", "focus": "Strength/Hypertrophy/etc", "exercises": [{"name": "Exercise Name", "sets": 3, "reps": "8-12", "notes": "Optional notes"}]}\n';
        $prompt .= 'Create a balanced routine that complements their recent training. Only return the JSON object, no other text.';

        $response = $this->onInsightScope(
            $user,
            'gym',
            fn (string $key, string $model, ?string $promptBlock): mixed => (new MegalomaniacAgent($user))
                ->withPersonalization($promptBlock)
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );

        if ($response->isEmpty()) {
            return response()->json([
                'routine' => null,
                'message' => $response->message('AI not configured. Please enable AI in Settings.'),
            ]);
        }

        $text = trim((string) $response->text());

        $json = $this->extractJson($text);

        if (! is_array($json) || ! isset($json['name']) || ! isset($json['exercises'])) {
            return response()->json([
                'routine' => null,
                'message' => 'Could not generate a routine. Please try again.',
            ]);
        }

        return response()->json([
            'routine' => $json,
        ]);
    }
}
