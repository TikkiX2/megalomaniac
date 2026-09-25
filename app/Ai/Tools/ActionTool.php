<?php

namespace App\Ai\Tools;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\GroceryItem;
use App\Models\Income;
use App\Models\MealLog;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Purchase;
use App\Models\SupplementLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\TaskBoardColumnService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActionTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Perform actions on behalf of the user: create projects, create workouts, log sets, log meals, add purchases, add income, add debts, create/complete/update tasks (including moving them between projects), log supplements, add grocery items. Use this when the user asks to record, create or update something.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request['action'] ?? '';

        return match ($action) {
            'create_project' => $this->createProject($request),
            'create_workout' => $this->createWorkout($request),
            'log_set' => $this->logSet($request),
            'log_meal' => $this->logMeal($request),
            'add_purchase' => $this->addPurchase($request),
            'add_income' => $this->addIncome($request),
            'add_debt' => $this->addDebt($request),
            'create_task' => $this->createTask($request),
            'complete_task' => $this->completeTask($request),
            'update_task' => $this->updateTask($request),
            'log_supplement' => $this->logSupplement($request),
            'add_grocery_item' => $this->addGroceryItem($request),
            default => json_encode(['error' => 'Invalid action. Use: create_project, create_workout, log_set, log_meal, add_purchase, add_income, add_debt, create_task, complete_task, update_task, log_supplement, add_grocery_item']),
        };
    }

    private function createProject(Request $request): string
    {
        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            return json_encode(['success' => false, 'error' => 'Project name is required']);
        }

        $type = in_array($request['type'] ?? null, ['personal', 'freelance'], true)
            ? $request['type']
            : 'personal';

        $clientId = $request['client_id'] ?? null;

        if ($clientId) {
            $client = Client::where('id', $clientId)->where('user_id', $this->user->id)->first();

            if (! $client) {
                return json_encode(['success' => false, 'error' => 'Client not found']);
            }

            $clientId = $client->id;
        }

        $currencyId = $request['currency_id'] ?? null;

        if ($currencyId && ! Currency::whereKey($currencyId)->exists()) {
            return json_encode(['success' => false, 'error' => 'Currency not found']);
        }

        $project = Project::create([
            'user_id' => $this->user->id,
            'client_id' => $clientId,
            'currency_id' => $currencyId,
            'name' => $name,
            'description' => $this->yooptaDescription($request['description'] ?? null),
            'status' => $request['status'] ?? 'pending',
            'type' => $type,
            'deadline' => $request['deadline'] ?? null,
            'priority' => $request['priority'] ?? null,
            'total_amount' => $request['total_amount'] ?? 0,
            'notes' => $request['notes'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Project created',
            'project' => $project->only(['id', 'name', 'type', 'status', 'client_id', 'deadline']),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Wrap a plain-text description into the Yoopta block shape the UI renders.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function yooptaDescription(mixed $description): ?array
    {
        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        return [[
            'id' => (string) Str::uuid(),
            'type' => 'paragraph',
            'children' => [['text' => trim($description)]],
        ]];
    }

    private function createWorkout(Request $request): string
    {
        $workout = Workout::create([
            'user_id' => $this->user->id,
            'started_at' => $request['started_at'] ?? now()->toIso8601String(),
            'notes' => $request['notes'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Workout created',
            'workout' => $workout->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function logSet(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($q) => $q->where('user_id', $this->user->id))
            ->findOrFail($request['workout_exercise_id']);

        $set = WorkoutSet::create([
            'workout_exercise_id' => $workoutExercise->id,
            'weight' => $request['weight'] ?? 0,
            'reps' => $request['reps'] ?? 0,
            'rpe' => $request['rpe'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Set logged',
            'set' => $set->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function logMeal(Request $request): string
    {
        $log = MealLog::create([
            'user_id' => $this->user->id,
            'date' => $request['date'] ?? now()->toDateString(),
            'meal_type' => $request['meal_type'] ?? 'snack',
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Meal log created',
            'meal_log' => $log->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function addPurchase(Request $request): string
    {
        $purchase = Purchase::create([
            'user_id' => $this->user->id,
            'description' => $request['description'] ?? $request['name'] ?? 'Purchase',
            'amount' => $request['amount'] ?? 0,
            'purchase_date' => $request['purchase_date'] ?? $request['date'] ?? now()->toDateString(),
            'category_id' => $request['category_id'] ?? null,
            'currency_id' => $request['currency_id'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Purchase added',
            'purchase' => $purchase->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function addIncome(Request $request): string
    {
        $income = Income::create([
            'user_id' => $this->user->id,
            'amount' => $request['amount'] ?? 0,
            'description' => $request['description'] ?? $request['name'] ?? 'Income',
            'received_date' => $request['received_date'] ?? $request['date'] ?? now()->toDateString(),
            'income_source_id' => $request['income_source_id'] ?? $request['source_id'] ?? null,
            'currency_id' => $request['currency_id'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Income added',
            'income' => $income->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function addDebt(Request $request): string
    {
        $totalAmount = $request['amount'] ?? 0;
        $debt = Debt::create([
            'user_id' => $this->user->id,
            'original_amount' => $totalAmount,
            'remaining_amount' => $totalAmount,
            'total_amount' => $totalAmount,
            'due_date' => $request['due_date'] ?? null,
            'status' => 'pending',
            'notes' => $request['description'] ?? $request['name'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Debt added',
            'debt' => $debt->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function createTask(Request $request): string
    {
        $projectId = $request['project_id'] ?? null;
        $project = $projectId
            ? Project::where('id', $projectId)->where('user_id', $this->user->id)->first()
            : null;

        if ($projectId && ! $project) {
            return json_encode(['success' => false, 'error' => 'Project not found']);
        }

        $statusKey = TaskBoardColumnService::firstStatusKey($project, $this->user);
        $column = TaskBoardColumnService::columnsFor($project, $this->user)->firstWhere('key', $statusKey);

        $task = ProjectTask::create([
            'user_id' => $this->user->id,
            'project_id' => $project?->id,
            'title' => $request['title'] ?? 'Task',
            'description' => $request['description'] ?? null,
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
            'status' => $statusKey,
            'is_done' => (bool) $column?->is_done,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Task created',
            'task' => $task->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function logSupplement(Request $request): string
    {
        $log = SupplementLog::create([
            'user_id' => $this->user->id,
            'supplement_id' => $request['supplement_id'],
            'taken_at' => $request['taken_at'] ?? now()->toIso8601String(),
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Supplement logged',
            'supplement_log' => $log->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function addGroceryItem(Request $request): string
    {
        $item = GroceryItem::create([
            'user_id' => $this->user->id,
            'name' => $request['name'] ?? 'Grocery item',
            'current_stock' => $request['quantity'] ?? $request['current_stock'] ?? 1,
            'target_stock' => $request['target_stock'] ?? 1,
            'unit' => $request['unit'] ?? null,
            'price' => $request['price'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Grocery item added',
            'grocery_item' => $item->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function completeTask(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return json_encode(['success' => false, 'error' => 'Task not found']);
        }

        $doneKey = TaskBoardColumnService::columnsFor($task->project, $this->user)
            ->firstWhere('is_done', true)?->key ?? 'Done';

        $task->update(['status' => $doneKey, 'is_done' => true]);

        return json_encode([
            'success' => true,
            'message' => 'Task completed',
            'task' => ['id' => $task->id, 'title' => $task->title, 'status' => $task->status],
        ], JSON_PRETTY_PRINT);
    }

    private function updateTask(Request $request): string
    {
        $task = $this->findTask($request['task_id'] ?? null);

        if (! $task) {
            return json_encode(['success' => false, 'error' => 'Task not found']);
        }

        $data = array_filter([
            'title' => $request['title'] ?? null,
            'description' => $request['description'] ?? null,
            'priority' => $request['priority'] ?? null,
            'due_date' => $request['due_date'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $movesProject = $request->offsetExists('project_id');
        $targetProject = $task->project;

        if ($movesProject) {
            $targetId = $request['project_id'];

            if ($targetId) {
                $targetProject = Project::where('id', $targetId)->where('user_id', $this->user->id)->first();

                if (! $targetProject) {
                    return json_encode(['success' => false, 'error' => 'Project not found']);
                }
            } else {
                $targetProject = null;
            }
        }

        $status = $request['status'] ?? null;

        if ($status || $movesProject) {
            $status ??= $task->status;

            $columns = TaskBoardColumnService::columnsFor($targetProject, $this->user);

            $column = $columns->firstWhere('key', $status) ?? $columns->first();

            $data['status'] = $column?->key ?? $status;
            $data['is_done'] = (bool) $column?->is_done;
        }

        if ($movesProject) {
            $data['project_id'] = $targetProject?->id;
            $data['sort_order'] = $this->nextSortOrder($targetProject);
        }

        $task->update($data);

        return json_encode([
            'success' => true,
            'message' => 'Task updated',
            'task' => $task->only(['id', 'title', 'status', 'priority', 'due_date', 'is_done', 'project_id']),
        ], JSON_PRETTY_PRINT);
    }

    private function nextSortOrder(?Project $project): int
    {
        return (int) ProjectTask::query()
            ->where('user_id', $this->user->id)
            ->when(
                $project,
                fn ($query) => $query->where('project_id', $project->id),
                fn ($query) => $query->whereNull('project_id'),
            )
            ->max('sort_order') + 1;
    }

    private function findTask(mixed $taskId): ?ProjectTask
    {
        if (! $taskId) {
            return null;
        }

        return ProjectTask::where('user_id', $this->user->id)->find((int) $taskId);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum([
                    'create_project', 'create_workout', 'log_set', 'log_meal', 'add_purchase',
                    'add_income', 'add_debt', 'create_task', 'complete_task', 'update_task',
                    'log_supplement', 'add_grocery_item',
                ])
                ->description('Action to perform')
                ->required(),
            // Project params
            'type' => $schema->string()->description('Project type: personal or freelance (for create_project)')->enum(['personal', 'freelance']),
            'client_id' => $schema->integer()->description('Client ID (for create_project, freelance projects only)'),
            'total_amount' => $schema->number()->description('Total amount (for create_project)'),
            'deadline' => $schema->string()->description('Deadline YYYY-MM-DD (for create_project)'),
            // Workout params
            'started_at' => $schema->string()->description('ISO 8601 datetime (for create_workout)'),
            'notes' => $schema->string()->description('Notes (for create_project, create_workout, add_debt)'),
            // Log set params
            'workout_exercise_id' => $schema->integer()->description('Workout Exercise ID (for log_set)'),
            'weight' => $schema->number()->description('Weight in kg/lbs (for log_set)'),
            'reps' => $schema->integer()->description('Number of reps (for log_set)'),
            'rpe' => $schema->number()->description('Rate of perceived exertion 1-10 (for log_set)'),
            // Meal params
            'date' => $schema->string()->description('Date YYYY-MM-DD (for log_meal, add_purchase, add_income)'),
            'food_name' => $schema->string()->description('Food name (for log_meal)'),
            'calories' => $schema->number()->description('Calories (for log_meal)'),
            'protein' => $schema->number()->description('Protein in grams (for log_meal)'),
            'carbs' => $schema->number()->description('Carbs in grams (for log_meal)'),
            'fats' => $schema->number()->description('Fats in grams (for log_meal)'),
            'quantity' => $schema->number()->description('Quantity (for log_meal, add_grocery_item)'),
            'meal_type' => $schema->string()->description('Meal type: breakfast, lunch, dinner, snack (for log_meal)'),
            // Purchase params
            'name' => $schema->string()->description('Name (for create_project, add_purchase, add_income, add_debt, add_grocery_item)'),
            'amount' => $schema->number()->description('Amount (for add_purchase, add_income, add_debt)'),
            'category_id' => $schema->integer()->description('Category ID (for add_purchase)'),
            'currency_id' => $schema->integer()->description('Currency ID (for add_purchase, add_income)'),
            // Income params
            'income_source_id' => $schema->integer()->description('Income source ID (for add_income)'),
            'source_id' => $schema->integer()->description('Alias for income_source_id (for add_income)'),
            'received_date' => $schema->string()->description('Date YYYY-MM-DD (for add_income)'),
            // Debt params
            'due_date' => $schema->string()->description('Due date YYYY-MM-DD (for add_debt, create_task, update_task)'),
            'description' => $schema->string()->description('Description (for create_project, add_purchase, add_debt, create_task)'),
            // Task params
            'project_id' => $schema->integer()->description('Project ID (for create_task, update_task; use 0 to detach the task from its project)'),
            'title' => $schema->string()->description('Title (for create_task, update_task)'),
            'priority' => $schema->string()->description('Priority (for create_project, create_task, update_task)'),
            'task_id' => $schema->integer()->description('Task ID (for complete_task, update_task)'),
            'status' => $schema->string()->description('Status (for create_project, update_task; must match a board column)'),
            // Supplement params
            'supplement_id' => $schema->integer()->description('Supplement ID (for log_supplement)'),
            'taken_at' => $schema->string()->description('ISO 8601 datetime (for log_supplement)'),
            // Grocery params
            'target_stock' => $schema->number()->description('Target stock (for add_grocery_item)'),
            'unit' => $schema->string()->description('Unit (for add_grocery_item)'),
            'price' => $schema->number()->description('Price (for add_grocery_item)'),
        ];
    }
}
