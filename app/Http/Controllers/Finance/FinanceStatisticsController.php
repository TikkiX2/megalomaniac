<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinanceStatisticsController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $dateFrom = $request->input('date_from', Carbon::now()->startOfYear()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $currencyId = $request->input('currency_id');

        // Default to first active currency if not specified
        if (! $currencyId) {
            $currencyId = Currency::active()->first()->id ?? null;
        }

        // 1. Expenses by Category
        $expensesByCategory = Purchase::where('purchases.user_id', $userId)
            ->where('purchases.currency_id', $currencyId)
            ->whereBetween('purchases.purchase_date', [$dateFrom, $dateTo])
            ->join('purchase_categories', 'purchases.category_id', '=', 'purchase_categories.id')
            ->select('purchase_categories.name', 'purchase_categories.color', DB::raw('SUM(purchases.amount) as total'))
            ->groupBy('purchase_categories.name', 'purchase_categories.color')
            ->orderByDesc('total')
            ->get();

        // 2. Income vs Expenses (Monthly)
        $months = [];
        $start = Carbon::parse($dateFrom)->startOfMonth();
        $end = Carbon::parse($dateTo)->endOfMonth();

        while ($start <= $end) {
            $months[] = $start->format('Y-m');
            $start->addMonth();
        }

        $monthExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => fn (string $column): string => "to_char($column, 'YYYY-MM')",
            'sqlite' => fn (string $column): string => "strftime('%Y-%m', $column)",
            default => fn (string $column): string => "DATE_FORMAT($column, '%Y-%m')",
        };

        $incomeByMonth = Income::where('user_id', $userId)
            ->where('currency_id', $currencyId)
            ->whereBetween('received_date', [$dateFrom, $dateTo])
            ->select(DB::raw($monthExpression('received_date').' as month'), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $expensesByMonth = Purchase::where('user_id', $userId)
            ->where('currency_id', $currencyId)
            ->whereBetween('purchase_date', [$dateFrom, $dateTo])
            ->select(DB::raw($monthExpression('purchase_date').' as month'), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyStats = [];
        foreach ($months as $month) {
            $monthlyStats[] = [
                'month' => $month,
                'income' => $incomeByMonth[$month] ?? 0,
                'expenses' => $expensesByMonth[$month] ?? 0,
            ];
        }

        // 3. Debt Status
        $debtStats = Debt::where('user_id', $userId)
            ->where('currency_id', $currencyId)
            ->select(
                DB::raw('SUM(remaining_amount) as remaining'),
                DB::raw('SUM(total_amount - remaining_amount) as paid'),
                DB::raw('SUM(total_amount) as total')
            )
            ->first();

        return Inertia::render('finance/statistics/index', [
            'currencies' => Currency::active()->get(),
            'currentCurrencyId' => (int) $currencyId,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'currency_id' => $currencyId,
            ],
            'stats' => [
                'expenses_by_category' => $expensesByCategory,
                'monthly_trend' => $monthlyStats,
                'debt_summary' => $debtStats,
            ],
        ]);
    }
}
