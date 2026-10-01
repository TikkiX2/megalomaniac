<?php

namespace App\Http\Controllers;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\TaskDescriptionAgent;
use App\Ai\Services\InsightService;
use App\Ai\Support\AiInsightOutcome;
use App\Ai\Support\AiInsightRunner;
use App\Ai\Support\MarkdownToYoopta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    public function __construct(
        protected InsightService $insightService,
    ) {}

    /**
     * Resolve the `surface:insights` chain for a module and run one prompt on it.
     *
     * These endpoints answered 200 with an honest message when the user had no
     * provider, so an empty chain keeps returning "nothing to show" here. An
     * exhausted chain is reported once by the runner and comes back flagged as
     * exhausted, so the copy tells the truth without turning into a 500.
     *
     * @param  callable(string $providerKey, string $model, string|null $promptBlock): mixed  $prompt
     */
    protected function onInsightScope(User $user, string $moduleKey, callable $prompt): AiInsightOutcome
    {
        return app(AiInsightRunner::class)->run($user, $moduleKey, $prompt);
    }

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

        $prompt = "Analyze the user's nutrition for the last 7 days and provide concise insights on:\n";
        $prompt .= "- Calorie intake trends\n";
        $prompt .= "- Macro balance (protein, carbs, fats)\n";
        $prompt .= "- Meal timing patterns\n";
        $prompt .= "- Recommendations to reach goals\n\n";
        $prompt .= "Meal data (JSON):\n".$recentMeals->toJson();

        $response = $this->onInsightScope(
            $user,
            'nutrition',
            fn (string $key, string $model, ?string $promptBlock): mixed => (new MegalomaniacAgent($user))
                ->withPersonalization($promptBlock)
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );

        return response()->json([
            'insight' => $response->text(),
            'message' => $response->isEmpty() ? $response->message('AI not configured.') : null,
        ]);
    }

    public function grocery(Request $request): JsonResponse
    {
        $insight = $this->insightService->generateGroceryInsights($request->user());

        return response()->json([
            'insight' => $insight,
            'message' => $insight ? null : 'AI not configured or no data available.',
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $insight = $this->insightService->generateTaskInsights($request->user());

        return response()->json([
            'insight' => $insight,
            'message' => $insight ? null : 'AI not configured or no data available.',
        ]);
    }

    public function generateQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'project_description' => 'required|string|max:2000',
            'project_type' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (! $user->ai_enabled) {
            return response()->json([
                'suggestion' => null,
                'message' => 'AI not configured.',
            ]);
        }

        $prompt = "Generate a professional quote/proposal for a freelance project.\n\n";
        $prompt .= "Client: {$validated['client_name']}\n";
        $prompt .= "Project Description: {$validated['project_description']}\n";
        if (! empty($validated['project_type'])) {
            $prompt .= "Project Type: {$validated['project_type']}\n";
        }
        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. A list of 3-6 line items with descriptions, estimated hours, and hourly rates\n";
        $prompt .= "2. A brief project summary/description\n";
        $prompt .= "3. A suggested timeline\n\n";
        $prompt .= "Return as JSON with this structure:\n";
        $prompt .= '{"items": [{"description": "...", "hours": N, "hourly_rate": N}], "summary": "...", "timeline": "..."}';

        $response = $this->onInsightScope(
            $user,
            'freelance',
            fn (string $key, string $model, ?string $promptBlock): mixed => (new MegalomaniacAgent($user))
                ->withPersonalization($promptBlock)
                ->forUser($user)
                ->prompt($prompt, provider: $key, model: $model),
        );

        if ($response->isEmpty()) {
            return response()->json([
                'suggestion' => null,
                'message' => $response->message('AI not configured.'),
            ]);
        }

        try {
            $json = json_decode($response->text(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'suggestion' => null,
                    'message' => 'Could not parse AI response.',
                ]);
            }

            return response()->json([
                'suggestion' => $json,
                'message' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'suggestion' => null,
                'message' => 'Error processing AI response.',
            ]);
        }
    }

    public function generateTaskDescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'title' => 'nullable|string|max:255',
            'context' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (! $user->ai_enabled) {
            return response()->json([
                'description' => null,
                'message' => 'AI not configured.',
            ]);
        }

        $prompt = '';
        if (! empty($validated['title'])) {
            $prompt .= "Tarea: {$validated['title']}\n";
        }
        if (! empty($validated['context'])) {
            $prompt .= "Contexto: {$validated['context']}\n";
        }
        $prompt .= "Escribe la descripción con este pedido: {$validated['prompt']}";

        $response = $this->onInsightScope(
            $user,
            'freelance',
            fn (string $key, string $model): mixed => (new TaskDescriptionAgent)
                ->prompt($prompt, provider: $key, model: $model),
        );

        if ($response->isEmpty()) {
            return response()->json([
                'description' => null,
                'message' => $response->message('AI not configured.'),
            ]);
        }

        $blocks = MarkdownToYoopta::convert((string) $response->text());

        if ($blocks === []) {
            return response()->json([
                'description' => null,
                'message' => 'Could not generate a description.',
            ]);
        }

        return response()->json([
            'description' => $blocks,
            'message' => null,
        ]);
    }
}
