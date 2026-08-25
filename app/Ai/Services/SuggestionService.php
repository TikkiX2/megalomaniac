<?php

namespace App\Ai\Services;

use App\Models\AgentSuggestion;
use App\Models\User;

class SuggestionService
{
    public function generateSuggestions(User $user): array
    {
        $suggestions = [];

        $suggestions = array_merge($suggestions, $this->checkWorkoutFrequency($user));
        $suggestions = array_merge($suggestions, $this->checkLowStock($user));
        $suggestions = array_merge($suggestions, $this->checkBudgetAlerts($user));

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
            ->where('date', '>=', now()->copy()->startOfMonth())
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
}
