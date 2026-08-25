<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreIncomeRequest;
use App\Http\Resources\IncomeResource;
use App\Models\Income;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IncomeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $incomes = Income::query()
            ->where('user_id', $request->user()->id)
            ->with(['incomeSource', 'currency'])
            ->latest()
            ->paginate(20);

        return IncomeResource::collection($incomes);
    }

    public function store(StoreIncomeRequest $request): JsonResponse
    {
        $income = Income::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new IncomeResource($income->load(['incomeSource', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Income $income): IncomeResource
    {
        abort_if($income->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new IncomeResource($income->load(['incomeSource', 'currency']));
    }

    public function update(Request $request, Income $income): IncomeResource
    {
        abort_if($income->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $income->update($request->validate([
            'income_source_id' => ['nullable', 'exists:income_sources,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'amount' => ['sometimes', 'numeric'],
            'received_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_day' => ['nullable', 'integer'],
            'recurrence_end_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]));

        return new IncomeResource($income->load(['incomeSource', 'currency']));
    }

    public function destroy(Request $request, Income $income): JsonResponse
    {
        abort_if($income->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $income->delete();

        return response()->json(['message' => 'Income deleted.']);
    }
}
