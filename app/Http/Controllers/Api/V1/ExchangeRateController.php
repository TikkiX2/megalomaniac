<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreExchangeRateRequest;
use App\Http\Resources\CurrencyResource;
use App\Http\Resources\ExchangeRateResource;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExchangeRateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $exchangeRates = ExchangeRate::query()
            ->with(['fromCurrency', 'toCurrency'])
            ->latest()
            ->paginate(20);

        return ExchangeRateResource::collection($exchangeRates);
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $exchangeRate = ExchangeRate::create($request->validated());

        return (new ExchangeRateResource($exchangeRate->load(['fromCurrency', 'toCurrency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        return new ExchangeRateResource($exchangeRate->load(['fromCurrency', 'toCurrency']));
    }

    public function update(Request $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        $exchangeRate->update($request->validate([
            'from_currency_id' => ['sometimes', 'exists:currencies,id'],
            'to_currency_id' => ['sometimes', 'exists:currencies,id'],
            'rate' => ['sometimes', 'numeric'],
            'effective_date' => ['sometimes', 'date'],
        ]));

        return new ExchangeRateResource($exchangeRate->load(['fromCurrency', 'toCurrency']));
    }

    public function destroy(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        return response()->json(['message' => 'Exchange rate deleted.']);
    }

    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_currency_id' => ['required', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
        ]);

        $fromCurrency = Currency::find($validated['from_currency_id']);
        $toCurrency = Currency::find($validated['to_currency_id']);

        $date = $validated['date'] ? Carbon::parse($validated['date']) : null;
        $convertedAmount = $fromCurrency->convertTo($toCurrency, $validated['amount'], $date);

        abort_if($convertedAmount === null, 422, 'No exchange rate found for this currency pair.');

        return response()->json([
            'from_currency' => new CurrencyResource($fromCurrency),
            'to_currency' => new CurrencyResource($toCurrency),
            'from_amount' => $validated['amount'],
            'to_amount' => $convertedAmount,
        ]);
    }
}
