<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $purchases = Purchase::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'currency'])
            ->latest()
            ->paginate(20);

        return PurchaseResource::collection($purchases);
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = Purchase::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new PurchaseResource($purchase->load(['category', 'currency'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Purchase $purchase): PurchaseResource
    {
        abort_if($purchase->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new PurchaseResource($purchase->load(['category', 'currency']));
    }

    public function update(Request $request, Purchase $purchase): PurchaseResource
    {
        abort_if($purchase->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $purchase->update($request->validate([
            'category_id' => ['nullable', 'exists:purchase_categories,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'amount' => ['sometimes', 'numeric'],
            'purchase_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]));

        return new PurchaseResource($purchase->load(['category', 'currency']));
    }

    public function destroy(Request $request, Purchase $purchase): JsonResponse
    {
        abort_if($purchase->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $purchase->delete();

        return response()->json(['message' => 'Purchase deleted.']);
    }
}
