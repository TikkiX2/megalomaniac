<?php

namespace App\Http\Controllers\Grocery;

use App\Http\Controllers\Controller;
use App\Models\GroceryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroceryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('fitness/grocery', [
            'items' => GroceryItem::where('user_id', $request->user()->id)
                ->orderBy('is_purchased')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'quantity' => 'required|numeric',
            'unit' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
        ]);

        return $request->user()->groceryItems()->create($validated);
    }

    public function update(Request $request, GroceryItem $groceryItem)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:255',
            'quantity' => 'nullable|numeric',
            'unit' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'is_purchased' => 'boolean',
        ]);

        if ($request->has('is_purchased') && $validated['is_purchased']) {
            $validated['purchased_at'] = now();
        }

        $groceryItem->update($validated);

        return $groceryItem;
    }

    public function destroy(GroceryItem $groceryItem)
    {
        $groceryItem->delete();

        return response()->noContent();
    }

    public function togglePurchased(GroceryItem $groceryItem)
    {
        $groceryItem->is_purchased = ! $groceryItem->is_purchased;
        $groceryItem->purchased_at = $groceryItem->is_purchased ? now() : null;
        $groceryItem->save();

        return $groceryItem;
    }
}
