<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $withdrawals = Withdrawal::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'currency'])
            ->latest()
            ->paginate(20);

        return WithdrawalResource::collection($withdrawals);
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = Withdrawal::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new WithdrawalResource($withdrawal->load(['category', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Withdrawal $withdrawal): WithdrawalResource
    {
        abort_if($withdrawal->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new WithdrawalResource($withdrawal->load(['category', 'currency']));
    }

    public function update(Request $request, Withdrawal $withdrawal): WithdrawalResource
    {
        abort_if($withdrawal->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $withdrawal->update($request->validate([
            'category_id' => ['nullable', 'exists:withdrawal_categories,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'amount' => ['sometimes', 'numeric'],
            'withdrawal_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string'],
            'recurrence_day' => ['nullable', 'integer'],
            'recurrence_end_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]));

        return new WithdrawalResource($withdrawal->load(['category', 'currency']));
    }

    public function destroy(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        abort_if($withdrawal->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $withdrawal->delete();

        return response()->json(['message' => 'Withdrawal deleted.']);
    }
}
