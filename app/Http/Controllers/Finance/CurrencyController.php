<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCurrencyRequest;
use App\Models\Currency;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    public function index()
    {
        return Inertia::render('finance/currencies/index', [
            'currencies' => Currency::orderBy('name')->get(),
        ]);
    }

    public function show(Currency $currency)
    {
        $currency->load(['exchangeRatesFrom.toCurrency', 'exchangeRatesTo.fromCurrency']);

        return Inertia::render('finance/currencies/show', [
            'currency' => $currency,
        ]);
    }

    public function store(StoreCurrencyRequest $request)
    {
        Currency::create($request->validated());

        return redirect()->route('finance.currencies.index')
            ->with('success', 'Moneda creada exitosamente.');
    }

    public function update(StoreCurrencyRequest $request, Currency $currency)
    {
        $currency->update($request->validated());

        return redirect()->route('finance.currencies.index')
            ->with('success', 'Moneda actualizada exitosamente.');
    }

    public function destroy(Currency $currency)
    {
        // Don't actually delete, just deactivate to preserve history
        $currency->update(['is_active' => false]);

        return redirect()->route('finance.currencies.index')
            ->with('success', 'Moneda desactivada exitosamente.');
    }

    public function restore(Currency $currency)
    {
        $currency->update(['is_active' => true]);

        return redirect()->route('finance.currencies.index')
            ->with('success', 'Moneda reactivada exitosamente.');
    }
}
