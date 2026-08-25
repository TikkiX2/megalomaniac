<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreCurrencyExchangeRequest;
use App\Http\Resources\CurrencyExchangeResource;
use App\Models\CurrencyExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyExchangeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $exchanges = CurrencyExchange::query()
            ->where('user_id', $request->user()->id)
            ->with(['fromCurrency', 'toCurrency'])
            ->latest()
            ->paginate(20);

        return CurrencyExchangeResource::collection($exchanges);
    }

    public function store(StoreCurrencyExchangeRequest $request): JsonResponse
    {
        $exchange = CurrencyExchange::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new CurrencyExchangeResource($exchange->load(['fromCurrency', 'toCurrency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CurrencyExchange $exchange): CurrencyExchangeResource
    {
        abort_if($exchange->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new CurrencyExchangeResource($exchange->load(['fromCurrency', 'toCurrency']));
    }

    public function update(Request $request, CurrencyExchange $exchange): CurrencyExchangeResource
    {
        abort_if($exchange->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $exchange->update($request->validate([
            'from_currency_id' => ['sometimes', 'exists:currencies,id'],
            'to_currency_id' => ['sometimes', 'exists:currencies,id'],
            'from_amount' => ['sometimes', 'numeric'],
            'to_amount' => ['sometimes', 'numeric'],
            'exchange_rate' => ['sometimes', 'numeric'],
            'exchange_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'withdrawal_id' => ['nullable', 'exists:withdrawals,id'],
            'income_id' => ['nullable', 'exists:incomes,id'],
            'to_reserve_id' => ['nullable', 'exists:savings_reserves,id'],
            'reserve_transaction_id' => ['nullable', 'exists:reserve_transactions,id'],
        ]));

        return new CurrencyExchangeResource($exchange->load(['fromCurrency', 'toCurrency']));
    }

    public function destroy(Request $request, CurrencyExchange $exchange): JsonResponse
    {
        abort_if($exchange->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $exchange->delete();

        return response()->json(['message' => 'Currency exchange deleted.']);
    }
}
