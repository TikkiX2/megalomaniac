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
        $userId = $request->user()->id;

        $history = \App\Models\GroceryPriceHistory::whereHas('groceryItem', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->with('groceryItem')
            ->orderByDesc('purchased_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->purchased_at->format('Y-m-d H:i:s');
            });

        return Inertia::render('fitness/grocery', [
            'items' => GroceryItem::where('user_id', $userId)
                ->orderBy('name')
                ->get(),
            'categories' => GroceryItem::where('user_id', $userId)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->map(fn ($category) => preg_split('/[\s,]+/', $category, -1, PREG_SPLIT_NO_EMPTY))
                ->flatten()
                ->unique()
                ->values()
                ->toArray(),
            'history' => $history,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'current_stock' => 'required|numeric|min:0',
            'target_stock' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
        ]);

        $request->user()->groceryItems()->create($validated);

        return back();
    }

    public function update(Request $request, GroceryItem $item)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:255',
            'current_stock' => 'nullable|numeric|min:0',
            'target_stock' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
        ]);

        $item->update($validated);

        return back();
    }

    public function destroy(GroceryItem $item)
    {
        $item->delete();

        return back();
    }

    public function consume(GroceryItem $item)
    {
        if ($item->current_stock > 0) {
            $item->decrement('current_stock');
        }

        return back();
    }

    public function bulkRestock(Request $request)
    {
        $items = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:grocery_items,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
        ])['items'];

        $now = now();

        \Illuminate\Support\Facades\DB::transaction(function () use ($items, $now) {
            foreach ($items as $data) {
                $item = GroceryItem::find($data['id']);

                // Update item stock and price
                $item->current_stock += $data['quantity'];
                $item->price = $data['price'];
                $item->purchased_at = $now;
                $item->save();

                // Rate limiting history creation to avoid spam if needed,
                // but for bulk restock we assume valid intent.
                $item->priceHistory()->create([
                    'price' => $data['price'],
                    'quantity' => $data['quantity'],
                    'purchased_at' => $now,
                ]);
            }
        });

        return back();
    }

    public function history(Request $request)
    {
        $userId = $request->user()->id;

        $history = \App\Models\GroceryPriceHistory::whereHas('groceryItem', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->with('groceryItem')
            ->orderByDesc('purchased_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->purchased_at->format('Y-m-d H:i:s');
            });

        return Inertia::render('fitness/grocery-history', [
            'history' => $history,
        ]);
    }
}
