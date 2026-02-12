<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WithdrawalCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('finance/withdrawal-categories/index', [
            'categories' => WithdrawalCategory::where('user_id', auth()->id())
                ->withCount('withdrawals')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $request->user()->withdrawalCategories()->create($validated);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, WithdrawalCategory $withdrawalCategory)
    {
        $this->authorize('update', $withdrawalCategory);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $withdrawalCategory->update($validated);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(WithdrawalCategory $withdrawalCategory)
    {
        $this->authorize('delete', $withdrawalCategory);

        $withdrawalCategory->delete();

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
