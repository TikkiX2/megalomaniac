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
                    'description' => $data['description'],
                    'action_label' => $data['action_label'] ?? null,
                    'action_url' => $data['action_url'] ?? null,
                    'priority' => $data['priority'] ?? 'normal',
                    'dismissed' => false,
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
                'description' => 'Llevas toda la semana sin entrenar. Un entrenamiento ligero puede ayudarte a mantener el ritmo.',
                'action_label' => 'Ver rutinas',
                'action_url' => '/fitness/routines',
                'priority' => 'high',
            ];
        } elseif ($workoutsThisWeek <= 2) {
            $suggestions[] = [
                'type' => 'workout_frequency',
                'title' => 'Puedes entrenar más esta semana',
                'description' => "Has entrenado {$workoutsThisWeek} veces esta semana. Considera agregar otra sesión para alcanzar tus objetivos.",
                'action_label' => 'Iniciar entrenamiento',
                'action_url' => '/fitness/gym',
                'priority' => 'normal',
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
                'description' => "Los siguientes suplementos necesitan reabastecimiento: {$names}.",
                'action_label' => 'Ver suplementos',
                'action_url' => '/fitness/supplements',
                'priority' => 'high',
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
                    'description' => 'Has gastado $'.number_format($purchasesThisMonth, 2).' este mes sin ingresos registrados.',
                    'action_label' => 'Ver finanzas',
                    'action_url' => '/finance/dashboard',
                    'priority' => 'normal',
                ],
            ];
        }

        $ratio = $purchasesThisMonth / $incomeThisMonth;

        if ($ratio > 0.8) {
            return [
                [
                    'type' => 'budget_alert',
                    'title' => 'Alerta de presupuesto',
                    'description' => 'Ya has gastado el '.round($ratio * 100).'% de tus ingresos este mes.',
                    'action_label' => 'Ver finanzas',
                    'action_url' => '/finance/dashboard',
                    'priority' => 'high',
                ],
            ];
        }

        return [];
    }
}
