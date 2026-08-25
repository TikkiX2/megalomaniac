<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\SavingsReserve;
use App\Models\Withdrawal;
use App\Models\WithdrawalCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CurrencyExchangeController extends Controller
{
    public function index()
    {
        $exchanges = CurrencyExchange::where('user_id', auth()->id())
            ->with(['fromCurrency', 'toCurrency', 'toReserve'])
            ->latest('exchange_date')
            ->paginate(15);

        return Inertia::render('finance/currency-exchanges/index', [
            'exchanges' => $exchanges,
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/currency-exchanges/create', [
            'currencies' => Currency::active()->get(),
            'incomeSources' => IncomeSource::where('user_id', auth()->id())->get(),
            'withdrawalCategories' => WithdrawalCategory::where('user_id', auth()->id())->get(),
            'savingsReserves' => SavingsReserve::where('user_id', auth()->id())->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
            'from_amount' => 'required|numeric|min:0.01',
            'to_amount' => 'required|numeric|min:0.01',
            'exchange_date' => 'required|date',
            'notes' => 'nullable|string',
            'withdrawal_category_id' => 'nullable|exists:withdrawal_categories,id',
            'income_source_id' => 'nullable|exists:income_sources,id',
            'to_reserve_id' => 'nullable|exists:savings_reserves,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $userId = auth()->id();

            // 1. Create the Withdrawal (The Egreso) - ALWAYS
            $withdrawal = Withdrawal::create([
                'user_id' => $userId,
                'currency_id' => $validated['from_currency_id'],
                'category_id' => $validated['withdrawal_category_id'],
                'amount' => $validated['from_amount'],
                'withdrawal_date' => $validated['exchange_date'],
                'description' => 'Currency Exchange (Out)',
                'notes' => 'Generated from exchange. '.($validated['notes'] ?? ''),
            ]);

            $incomeId = null;
            $reserveTransactionId = null;

            if ($validated['to_reserve_id']) {
                // 2a. Create Reserve Transaction (instead of Income)
                $reserve = SavingsReserve::findOrFail($validated['to_reserve_id']);

                // Ensure currency matches (or use the one from reserve)
                $reserveTransaction = $reserve->transactions()->create([
                    'currency_id' => $reserve->currency_id,
                    'amount' => $validated['to_amount'],
                    'transaction_type' => 'deposit',
                    'transaction_date' => $validated['exchange_date'],
                    'description' => 'Currency Exchange (In)',
                    'notes' => 'Generated from exchange. '.($validated['notes'] ?? ''),
                ]);

                // Update reserve balance
                $reserve->increment('current_amount', $validated['to_amount']);
                $reserveTransactionId = $reserveTransaction->id;
            } else {
                // 2b. Create the Income (The Ingreso) - Standard balance
                $income = Income::create([
                    'user_id' => $userId,
                    'currency_id' => $validated['to_currency_id'],
                    'income_source_id' => $validated['income_source_id'],
                    'amount' => $validated['to_amount'],
                    'received_date' => $validated['exchange_date'],
                    'description' => 'Currency Exchange (In)',
                    'notes' => 'Generated from exchange. '.($validated['notes'] ?? ''),
                ]);
                $incomeId = $income->id;
            }

            // 3. Create the Exchange record
            $exchange = CurrencyExchange::create([
                'user_id' => $userId,
                'from_currency_id' => $validated['from_currency_id'],
                'to_currency_id' => $validated['to_currency_id'],
                'from_amount' => $validated['from_amount'],
                'to_amount' => $validated['to_amount'],
                'exchange_rate' => $validated['to_amount'] / $validated['from_amount'],
                'exchange_date' => $validated['exchange_date'],
                'notes' => $validated['notes'],
                'withdrawal_id' => $withdrawal->id,
                'income_id' => $incomeId,
                'to_reserve_id' => $validated['to_reserve_id'],
                'reserve_transaction_id' => $reserveTransactionId,
            ]);

            return redirect()->route('finance.dashboard')
                ->with('success', 'Currency exchange recorded successfully.');
        });
    }

    public function destroy(CurrencyExchange $currencyExchange)
    {
        $this->authorize('delete', $currencyExchange);

        DB::transaction(function () use ($currencyExchange) {
            // Delete associated ledger entries if they exist
            $currencyExchange->withdrawal?->delete();
            $currencyExchange->income?->delete();

            if ($currencyExchange->reserveTransaction) {
                // Adjust reserve balance back
                $currencyExchange->toReserve?->decrement('current_amount', $currencyExchange->to_amount);
                $currencyExchange->reserveTransaction->delete();
            }

            $currencyExchange->delete();
        });

        return redirect()->back()->with('success', 'Exchange deleted successfully.');
    }
}
