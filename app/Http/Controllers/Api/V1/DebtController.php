<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreDebtRequest;
use App\Http\Resources\DebtResource;
use App\Models\Debt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DebtController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $debts = Debt::query()
            ->where('user_id', $request->user()->id)
            ->with(['payments', 'creditCard', 'currency'])
            ->latest()
            ->paginate(20);

        return DebtResource::collection($debts);
    }

    public function store(StoreDebtRequest $request): JsonResponse
    {
        $debt = Debt::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'remaining_amount' => $request->get('total_amount'),
            'status' => 'pending',
        ]);

        return (new DebtResource($debt->load(['payments', 'creditCard', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Debt $debt): DebtResource
    {
        abort_if($debt->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new DebtResource($debt->load(['payments', 'creditCard', 'currency']));
    }

    public function update(Request $request, Debt $debt): DebtResource
    {
        abort_if($debt->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $debt->update($request->validate([
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'credit_card_id' => ['nullable', 'exists:credit_cards,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'original_amount' => ['sometimes', 'numeric'],
            'total_amount' => ['sometimes', 'numeric'],
            'interest_amount' => ['sometimes', 'numeric'],
            'tax_amount' => ['sometimes', 'numeric'],
            'due_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:pending,partial,paid'],
            'notes' => ['nullable', 'string'],
        ]));

        return new DebtResource($debt->load(['payments', 'creditCard', 'currency']));
    }

    public function destroy(Request $request, Debt $debt): JsonResponse
    {
        abort_if($debt->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $debt->delete();

        return response()->json(['message' => 'Debt deleted.']);
    }

    public function addPayment(Request $request, Debt $debt): JsonResponse
    {
        abort_if($debt->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $debt->addPayment(
            $validated['amount'],
            $validated['payment_date'],
            $validated['notes'] ?? null
        );

        return (new DebtResource($debt->fresh(['payments', 'creditCard', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }
}
