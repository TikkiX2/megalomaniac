<?php

namespace App\Http\Controllers\Supplement;

use App\Http\Controllers\Controller;
use App\Models\Supplement;
use App\Models\SupplementLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('fitness/supplement', [
            'supplements' => Supplement::where('user_id', $request->user()->id)->get(),
            'recentLogs' => SupplementLog::with('supplement')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('taken_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'dosage_amount' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'stock_quantity' => 'required|integer',
            'low_stock_threshold' => 'required|integer',
            'image_url' => 'nullable|url',
        ]);

        $request->user()->supplements()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Supplement $item)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'dosage_amount' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'stock_quantity' => 'nullable|integer',
            'low_stock_threshold' => 'nullable|integer',
            'image_url' => 'nullable|url',
        ]);

        $item->update($validated);

        return redirect()->back();
    }

    public function destroy(Supplement $item)
    {
        $item->delete();

        return response()->noContent();
    }

    public function logIntake(Request $request, Supplement $supplement)
    {
        // Decrement stock
        if ($supplement->stock_quantity > 0) {
            $supplement->decrement('stock_quantity');
        }

        // Create log
        SupplementLog::create([
            'user_id' => $request->user()->id,
            'supplement_id' => $supplement->id,
            'taken_at' => now(),
        ]);

        return redirect()->back();
    }

    public function getLogs(Request $request)
    {
        return SupplementLog::with('supplement')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('taken_at')
            ->limit(20)
            ->get();
    }
}
