<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CurrencyExchange;
use App\Models\Income;
use App\Models\Purchase;
use App\Models\SavingsReserve;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceStatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        $totalIncome = Income::where('user_id', $user->id)
            ->where('received_date', '>=', $startOfYear)
            ->sum('amount');

        $totalPurchases = Purchase::where('user_id', $user->id)
            ->where('purchase_date', '>=', $startOfYear)
            ->sum('amount');

        $totalWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('withdrawal_date', '>=', $startOfYear)
            ->sum('amount');

        $totalExchange = CurrencyExchange::where('user_id', $user->id)
            ->where('exchange_date', '>=', $startOfYear)
            ->sum('from_amount');

        $reserves = SavingsReserve::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $totalSaved = $reserves->sum('current_amount');
        $totalGoals = $reserves->sum('goal_amount');

        $monthlyIncome = Income::where('user_id', $user->id)
            ->where('received_date', '>=', $startOfMonth)
            ->sum('amount');

        $monthlyPurchases = Purchase::where('user_id', $user->id)
            ->where('purchase_date', '>=', $startOfMonth)
            ->sum('amount');

        $monthlyWithdrawals = Withdrawal::where('user_id', $user->id)
            ->where('withdrawal_date', '>=', $startOfMonth)
            ->sum('amount');

        return response()->json([
            'yearly' => [
                'income' => $totalIncome,
                'purchases' => $totalPurchases,
                'withdrawals' => $totalWithdrawals,
                'exchange' => $totalExchange,
                'net' => $totalIncome - $totalPurchases - $totalWithdrawals,
            ],
            'monthly' => [
                'income' => $monthlyIncome,
                'purchases' => $monthlyPurchases,
                'withdrawals' => $monthlyWithdrawals,
                'net' => $monthlyIncome - $monthlyPurchases - $monthlyWithdrawals,
            ],
            'reserves' => [
                'total_saved' => $totalSaved,
                'total_goals' => $totalGoals,
                'progress' => $totalGoals > 0
                    ? round(($totalSaved / $totalGoals) * 100, 1)
                    : 0,
                'count' => $reserves->count(),
            ],
        ]);
    }
}
