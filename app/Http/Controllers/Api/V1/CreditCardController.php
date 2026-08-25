<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreCreditCardRequest;
use App\Http\Resources\CreditCardResource;
use App\Models\CreditCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreditCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $creditCards = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->with(['debts'])
            ->latest()
            ->paginate(20);

        return CreditCardResource::collection($creditCards);
    }

    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $creditCard = CreditCard::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new CreditCardResource($creditCard->load('debts')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CreditCard $creditCard): CreditCardResource
    {
        abort_if($creditCard->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new CreditCardResource($creditCard->load('debts'));
    }

    public function update(Request $request, CreditCard $creditCard): CreditCardResource
    {
        abort_if($creditCard->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $creditCard->update($request->validate([
            'name' => ['sometimes', 'string'],
            'last_four_digits' => ['sometimes', 'string', 'size:4'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'is_mine' => ['sometimes', 'boolean'],
            'interest_rate' => ['sometimes', 'numeric'],
            'tax_percentage' => ['sometimes', 'numeric'],
            'apply_interest' => ['sometimes', 'boolean'],
            'apply_tax' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]));

        return new CreditCardResource($creditCard->load('debts'));
    }

    public function destroy(Request $request, CreditCard $creditCard): JsonResponse
    {
        abort_if($creditCard->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $creditCard->delete();

        return response()->json(['message' => 'Credit card deleted.']);
    }
}
