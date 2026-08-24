<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\Income;
use App\Models\Purchase;
use App\Models\ReserveTransaction;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        // Get all active currencies
        $currencies = Currency::active()->get();

        // Calculate balances by currency
        $balances = [];
        foreach ($currencies as $currency) {
            $totalIncome = Income::where('user_id', $userId)
                ->where('currency_id', $currency->id)
                ->sum('amount');

            $totalExpenses = Purchase::where('user_id', $userId)
                ->where('currency_id', $currency->id)
                ->sum('amount');

            $totalWithdrawals = Withdrawal::where('user_id', $userId)
                ->where('currency_id', $currency->id)
                ->sum('amount');

            // Total spent is purchases + withdrawals
            $totalSpent = $totalExpenses + $totalWithdrawals;

            $balance = $totalIncome - $totalSpent;

            if ($balance != 0 || $totalIncome > 0 || $totalSpent > 0) {
                $balances[] = [
                    'currency' => $currency,
                    'balance' => $balance,
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalSpent,
                ];
            }
        }

        // Get pending debts summary
        $pendingDebts = Debt::where('user_id', $userId)
            ->where('status', '!=', 'paid')
            ->selectRaw('currency_id, SUM(remaining_amount) as total, COUNT(*) as count')
            ->groupBy('currency_id')
            ->with('currency')
            ->get();

        // Get recent transactions (last 10)
        $recentIncomes = Income::where('user_id', $userId)
            ->with(['currency', 'incomeSource'])
            ->latest('received_date')
            ->take(5)
            ->get()
            ->map(fn ($income) => [
                'id' => $income->id,
                'type' => 'income',
                'description' => $income->description ?? $income->incomeSource->name,
                'amount' => $income->amount,
                'currency' => $income->currency,
                'date' => $income->received_date,
            ]);

        $recentPurchases = Purchase::where('user_id', $userId)
            ->with(['currency', 'category'])
            ->latest('purchase_date')
            ->take(5)
            ->get()
            ->map(fn ($purchase) => [
                'id' => $purchase->id,
                'type' => 'purchase',
                'description' => $purchase->description,
                'amount' => $purchase->amount,
                'currency' => $purchase->currency,
                'date' => $purchase->purchase_date,
                'category' => $purchase->category,
            ]);

        $recentWithdrawals = Withdrawal::where('user_id', $userId)
            ->with(['currency', 'category'])
            ->latest('withdrawal_date')
            ->take(5)
            ->get()
            ->map(fn ($withdrawal) => [
                'id' => $withdrawal->id,
                'type' => 'withdrawal',
                'description' => $withdrawal->description,
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'date' => $withdrawal->withdrawal_date,
                'category' => $withdrawal->category,
            ]);

        $recentReserveTransactions = ReserveTransaction::whereHas('reserve', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->with(['currency', 'reserve'])
            ->latest('transaction_date')
            ->take(5)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->transaction_type === 'deposit' ? 'reserve_deposit' : 'reserve_withdrawal',
                'description' => ($tx->description ?? $tx->transaction_type).' ('.$tx->reserve->name.')',
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'date' => $tx->transaction_date,
                'category' => [
                    'name' => 'Savings',
                    'color' => $tx->reserve->color,
                ],
            ]);

        $recentTransactions = $recentIncomes
            ->concat($recentPurchases)
            ->concat($recentWithdrawals)
            ->concat($recentReserveTransactions)
            ->sortByDesc('date')
            ->take(10)
            ->values();

        // Get overdue debts
        $overdueDebts = Debt::where('user_id', $userId)
            ->overdue()
            ->with(['currency', 'creditCard', 'purchase'])
            ->get();

        // Quick stats
        $stats = [
            'total_purchases_this_month' => Purchase::where('user_id', $userId)
                ->whereBetween('purchase_date', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])
                ->count(),
            'total_incomes_this_month' => Income::where('user_id', $userId)
                ->whereBetween('received_date', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])
                ->count(),
            'total_withdrawals_this_month' => Withdrawal::where('user_id', $userId)
                ->whereBetween('withdrawal_date', [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ])
                ->count(),
            'active_debts_count' => Debt::where('user_id', $userId)
                ->where('status', '!=', 'paid')
                ->count(),
            'overdue_debts_count' => $overdueDebts->count(),
        ];

        return Inertia::render('finance/dashboard', [
            'balances' => $balances,
            'pendingDebts' => $pendingDebts,
            'recentTransactions' => $recentTransactions,
            'overdueDebts' => $overdueDebts,
            'stats' => $stats,
        ]);
    }
}
