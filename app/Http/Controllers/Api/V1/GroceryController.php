<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreGroceryItemRequest;
use App\Http\Resources\GroceryItemResource;
use App\Models\GroceryItem;
use App\Models\GroceryPriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroceryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GroceryItem::query()
            ->where('user_id', $request->user()->id);

        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $items = $query->latest()->paginate(20);

        return GroceryItemResource::collection($items);
    }

    public function store(StoreGroceryItemRequest $request): JsonResponse
    {
        $item = GroceryItem::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new GroceryItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GroceryItem $item, Request $request): GroceryItemResource
    {
        if ($item->user_id !== $request->user()->id) {
            abort(403);
        }

        return new GroceryItemResource($item->load('priceHistory'));
    }

    public function update(StoreGroceryItemRequest $request, GroceryItem $item): JsonResponse
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $oldPrice = $item->price;
        $item->update($request->validated());

        if ($request->has('price') && $request->get('price') != $oldPrice) {
            GroceryPriceHistory::create([
                'grocery_item_id' => $item->id,
                'price' => $request->get('price'),
                'quantity' => $request->get('current_stock', $item->current_stock),
                'purchased_at' => now(),
            ]);
        }

        return new GroceryItemResource($item->fresh('priceHistory'));
    }

    public function destroy(GroceryItem $item, Request $request): JsonResponse
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $item->delete();

        return response()->json(['message' => 'Grocery item deleted.']);
    }

    public function consume(GroceryItem $item, Request $request): JsonResponse
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quantity = $request->get('quantity', 1);
        $newStock = max(0, $item->current_stock - $quantity);

        $item->update(['current_stock' => $newStock]);

        return new GroceryItemResource($item->fresh('priceHistory'));
    }
}
