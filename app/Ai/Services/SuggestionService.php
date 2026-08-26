<?php

namespace App\Ai\Services;

use App\Models\AgentSuggestion;
use App\Models\ProjectTask;
use App\Models\User;

class SuggestionService
{
    public function generateSuggestions(User $user): array
    {
        $suggestions = [];

        $suggestions = array_merge($suggestions, $this->checkWorkoutFrequency($user));
        $suggestions = array_merge($suggestions, $this->checkLowStock($user));
        $suggestions = array_merge($suggestions, $this->checkBudgetAlerts($user));
        $suggestions = array_merge($suggestions, $this->checkNutritionMacros($user));
        $suggestions = array_merge($suggestions, $this->checkOverdueTasks($user));
        $suggestions = array_merge($suggestions, $this->checkGroceryStock($user));
        $suggestions = array_merge($suggestions, $this->checkCreditCardDueDates($user));
        $suggestions = array_merge($suggestions, $this->checkDebtPaymentStrategy($user));

        foreach ($suggestions as $data) {
            AgentSuggestion::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $data['type'],
                    'title' => $data['title'],
                ],
                [
                    'content' => $data['content'],
                    'data' => $data['data'] ?? null,
                    'dismissed_at' => null,
                ]
            );
        }

        return $suggestions;
    }

    private function checkWorkoutFrequency(User $user): array
    {
        $workoutsThisWeek = $user->workouts()
            ->where('started_at', '>=', now()->copy()->startOfWeek())
            ->count();

        $suggestions = [];

        if ($workoutsThisWeek === 0) {
            $suggestions[] = [
                'type' => 'workout_reminder',
                'title' => 'No has entrenado esta semana',
                'content' => 'Llevas toda la semana sin entrenar. Un entrenamiento ligero puede ayudarte a mantener el ritmo.',
                'data' => ['action_url' => '/fitness/routines'],
            ];
        } elseif ($workoutsThisWeek <= 2) {
            $suggestions[] = [
                'type' => 'workout_frequency',
                'title' => 'Puedes entrenar más esta semana',
                'content' => "Has entrenado {$workoutsThisWeek} veces esta semana. Considera agregar otra sesión para alcanzar tus objetivos.",
                'data' => ['action_url' => '/fitness/gym'],
            ];
        }

        return $suggestions;
    }

    private function checkLowStock(User $user): array
    {
        $lowStock = $user->supplements()
            ->get()
            ->filter->is_low_stock;

        if ($lowStock->isEmpty()) {
            return [];
        }

        $names = $lowStock->pluck('name')->implode(', ');

        return [
            [
                'type' => 'low_stock',
                'title' => 'Suplementos con stock bajo',
                'content' => "Los siguientes suplementos necesitan reabastecimiento: {$names}.",
                'data' => ['action_url' => '/fitness/supplements'],
            ],
        ];
    }

    private function checkBudgetAlerts(User $user): array
    {
        $purchasesThisMonth = $user->purchases()
            ->where('purchase_date', '>=', now()->copy()->startOfMonth())
            ->sum('amount');

        if ($purchasesThisMonth <= 0) {
            return [];
        }

        $incomeThisMonth = $user->incomes()
            ->where('received_date', '>=', now()->copy()->startOfMonth())
            ->sum('amount');

        if ($incomeThisMonth <= 0) {
            return [
                [
                    'type' => 'budget_alert',
                    'title' => 'Gastos este mes',
                    'content' => 'Has gastado $'.number_format($purchasesThisMonth, 2).' este mes sin ingresos registrados.',
                    'data' => ['action_url' => '/finance/dashboard'],
                ],
            ];
        }

        $ratio = $purchasesThisMonth / $incomeThisMonth;

        if ($ratio > 0.8) {
            return [
                [
                    'type' => 'budget_alert',
                    'title' => 'Alerta de presupuesto',
                    'content' => 'Ya has gastado el '.round($ratio * 100).'% de tus ingresos este mes.',
                    'data' => ['action_url' => '/finance/dashboard'],
                ],
            ];
        }

        return [];
    }

    private function checkNutritionMacros(User $user): array
    {
        $todayLogs = $user->mealLogs()
            ->where('date', now()->toDateString())
            ->get();

        if ($todayLogs->isEmpty()) {
            $hour = (int) now()->format('H');
            if ($hour >= 14) {
                return [
                    [
                        'type' => 'nutrition_reminder',
                        'title' => 'Sin comidas registradas hoy',
                        'content' => 'Es tarde y no has registrado ninguna comida. Llevar un registro ayuda a alcanzar tus objetivos.',
                        'data' => ['action_url' => '/fitness/nutrition'],
                    ],
                ];
            }

            return [];
        }

        $totalCalories = $todayLogs->sum('calories');
        $totalProtein = $todayLogs->sum('protein');
        $suggestions = [];

        // Estimate target calories from weight (rough: 30kcal per kg)
        $estimatedCalories = $user->weight ? (float) $user->weight * 30 : 2000;

        if ($totalCalories > 0 && abs($totalCalories - $estimatedCalories) / $estimatedCalories > 0.3) {
            $direction = $totalCalories < $estimatedCalories ? 'por debajo' : 'por encima';
            $suggestions[] = [
                'type' => 'nutrition_macros',
                'title' => 'Calorías fuera de rango',
                'content' => "Tus calorías de hoy ($totalCalories kcal) están {$direction} del estimado (~{$estimatedCalories} kcal).",
                'data' => ['action_url' => '/fitness/nutrition'],
            ];
        }

        // Estimate target protein from weight (rough: 1.6g per kg)
        $estimatedProtein = $user->weight ? (float) $user->weight * 1.6 : 120;

        if ($totalProtein > 0 && abs($totalProtein - $estimatedProtein) / $estimatedProtein > 0.3) {
            $direction = $totalProtein < $estimatedProtein ? 'por debajo' : 'por encima';
            $suggestions[] = [
                'type' => 'nutrition_macros',
                'title' => 'Proteína fuera de rango',
                'content' => "Tu proteína de hoy ({$totalProtein}g) está {$direction} del estimado (~{$estimatedProtein}g).",
                'data' => ['action_url' => '/fitness/nutrition'],
            ];
        }

        return $suggestions;
    }

    private function checkOverdueTasks(User $user): array
    {
        $overdueTasks = ProjectTask::where('user_id', $user->id)
            ->where('status', '!=', 'Done')
            ->where('status', '!=', 'Completed')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        if ($overdueTasks === 0) {
            return [];
        }

        return [
            [
                'type' => 'overdue_task',
                'title' => 'Tareas vencidas',
                'content' => "Tienes {$overdueTasks} tarea(s) con fecha límite vencida.",
                'data' => ['action_url' => '/personal/tasks'],
            ],
        ];
    }

    private function checkGroceryStock(User $user): array
    {
        $lowItems = $user->groceryItems()
            ->whereColumn('current_stock', '<', 'target_stock')
            ->get();

        if ($lowItems->isEmpty()) {
            return [];
        }

        $names = $lowItems->pluck('name')->implode(', ');

        return [
            [
                'type' => 'grocery_low_stock',
                'title' => 'Compras con stock bajo',
                'content' => "Los siguientes artículos están por debajo del stock objetivo: {$names}.",
                'data' => ['action_url' => '/fitness/groceries'],
            ],
        ];
    }

    private function checkCreditCardDueDates(User $user): array
    {
        $upcomingDebts = $user->debts()
            ->whereNotNull('due_date')
            ->where('status', '!=', 'paid')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->with('creditCard')
            ->get()
            ->filter(fn ($debt) => $debt->creditCard !== null);

        if ($upcomingDebts->isEmpty()) {
            return [];
        }

        $cardNames = $upcomingDebts->pluck('creditCard.name')->unique()->implode(', ');

        return [
            [
                'type' => 'credit_card_due',
                'title' => 'Pago de tarjeta próximo',
                'content' => "Tienes deudas de tarjetas con vencimiento en los próximos 7 días: {$cardNames}.",
                'data' => ['action_url' => '/finance/dashboard'],
            ],
        ];
    }

    private function checkDebtPaymentStrategy(User $user): array
    {
        $pendingDebts = $user->debts()
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get();

        if ($pendingDebts->isEmpty()) {
            return [];
        }

        $totalPending = $pendingDebts->sum('remaining_amount');
        $overdueCount = $pendingDebts->filter->isOverdue()->count();

        $suggestions = [];

        if ($overdueCount > 0) {
            $suggestions[] = [
                'type' => 'debt_overdue',
                'title' => 'Deudas vencidas',
                'content' => "Tienes {$overdueCount} deuda(s) vencida(s) con un total pendiente de $".number_format($totalPending, 2).'. Prioriza el pago.',
                'data' => ['action_url' => '/finance/dashboard'],
            ];
        } elseif ($pendingDebts->count() > 1) {
            $suggestions[] = [
                'type' => 'debt_strategy',
                'title' => 'Estrategia de pago de deudas',
                'content' => "Tienes {$pendingDebts->count()} deudas pendientes por $".number_format($totalPending, 2).'. Considera usar el método bola de nieve o avalanche para optimizar pagos.',
                'data' => ['action_url' => '/finance/dashboard'],
            ];
        }

        return $suggestions;
    }
}
