<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePurchaseCategoryRequest;
use App\Models\PurchaseCategory;
use Inertia\Inertia;

class PurchaseCategoryController extends Controller
{
    public function index()
    {
        $categories = PurchaseCategory::where('user_id', auth()->id())
            ->withCount('purchases')
            ->get();

        return Inertia::render('finance/categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(StorePurchaseCategoryRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        PurchaseCategory::create($data);

        return redirect()->back()
            ->with('success', 'Categoría creada exitosamente.');
    }

    public function update(StorePurchaseCategoryRequest $request, PurchaseCategory $category)
    {
        // Using 'purchase_category' for route parameter binding might need adjustment depending on how it's registered
        // Assuming standard resource routing maps to model binding

        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        $category->update($request->validated());

        return redirect()->back()
            ->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(PurchaseCategory $category)
    {
        if ($category->user_id !== auth()->id()) {
            abort(403);
        }

        // Optional: Check if used in purchases before deleting
        if ($category->purchases()->exists()) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar una categoría que tiene compras asociadas.');
        }

        $category->delete();

        return redirect()->back()
            ->with('success', 'Categoría eliminada exitosamente.');
    }
}
