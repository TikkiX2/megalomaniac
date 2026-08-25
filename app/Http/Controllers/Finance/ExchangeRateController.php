<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExchangeRateController extends Controller
{
    public function index(Request $request)
    {
        $query = ExchangeRate::with(['fromCurrency', 'toCurrency'])
            ->latest('effective_date');

        if ($request->filled('from_currency_id')) {
            $query->where('from_currency_id', $request->from_currency_id);
        }

        if ($request->filled('to_currency_id')) {
            $query->where('to_currency_id', $request->to_currency_id);
        }

        return Inertia::render('finance/exchange-rates/index', [
            'exchangeRates' => $query->paginate(15),
            'currencies' => Currency::active()->get(),
            'filters' => $request->only(['from_currency_id', 'to_currency_id']),
        ]);
    }

    public function store(StoreExchangeRateRequest $request)
    {
        ExchangeRate::create($request->validated());

        return redirect()->route('finance.exchange-rates.index')
            ->with('success', 'Tasa de cambio registrada exitosamente.');
    }

    public function convert(Request $request)
    {
        $request->validate([
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric',
            'date' => 'nullable|date',
        ]);

        $from = Currency::findOrFail($request->from_currency_id);
        $to = Currency::findOrFail($request->to_currency_id);

        $result = $from->convertTo($to, $request->amount, $request->date ? Carbon::parse($request->date) : null);

        if ($result === null) {
            return response()->json(['error' => 'No exchange rate found'], 422);
        }

        return response()->json([
            'result' => $result,
            'rate' => $result / $request->amount,
        ]);
    }
}
