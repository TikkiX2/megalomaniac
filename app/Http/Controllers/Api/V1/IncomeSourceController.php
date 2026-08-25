<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreIncomeSourceRequest;
use App\Http\Resources\IncomeSourceResource;
use App\Models\IncomeSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncomeSourceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $incomeSources = IncomeSource::query()
            ->where('user_id', $request->user()->id)
            ->with(['defaultCurrency', 'incomes'])
            ->latest()
            ->paginate(20);

        return IncomeSourceResource::collection($incomeSources);
    }

    public function store(StoreIncomeSourceRequest $request): JsonResponse
    {
        $incomeSource = IncomeSource::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new IncomeSourceResource($incomeSource->load(['defaultCurrency', 'incomes'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, IncomeSource $incomeSource): IncomeSourceResource
    {
        abort_if($incomeSource->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new IncomeSourceResource($incomeSource->load(['defaultCurrency', 'incomes']));
    }

    public function update(Request $request, IncomeSource $incomeSource): IncomeSourceResource
    {
        abort_if($incomeSource->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $incomeSource->update($request->validate([
            'name' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'default_currency_id' => ['nullable', 'exists:currencies,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return new IncomeSourceResource($incomeSource->load(['defaultCurrency', 'incomes']));
    }

    public function destroy(Request $request, IncomeSource $incomeSource): JsonResponse
    {
        abort_if($incomeSource->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $incomeSource->delete();

        return response()->json(['message' => 'Income source deleted.']);
    }
}
