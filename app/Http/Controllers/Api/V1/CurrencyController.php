<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $currencies = Currency::query()
            ->with('exchangeRatesTo')
            ->latest()
            ->paginate(20);

        return CurrencyResource::collection($currencies);
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = Currency::create($request->validated());

        return (new CurrencyResource($currency))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Currency $currency): CurrencyResource
    {
        return new CurrencyResource($currency->load('exchangeRatesTo'));
    }

    public function update(Request $request, Currency $currency): CurrencyResource
    {
        $currency->update($request->validate([
            'code' => ['sometimes', 'string', 'size:3'],
            'name' => ['sometimes', 'string'],
            'symbol' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return new CurrencyResource($currency->load('exchangeRatesTo'));
    }

    public function destroy(Request $request, Currency $currency): JsonResponse
    {
        $currency->delete();

        return response()->json(['message' => 'Currency deleted.']);
    }

    public function restore(Request $request, int $currency): JsonResponse
    {
        $currency = Currency::withTrashed()->find($currency);

        abort_if(! $currency, 404, 'Currency not found.');

        $currency->restore();

        return (new CurrencyResource($currency))
            ->response()
            ->setStatusCode(200);
    }
}
