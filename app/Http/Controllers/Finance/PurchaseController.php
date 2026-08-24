<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePurchaseRequest;
use App\Models\Currency;
use App\Models\Purchase;
use App\Models\PurchaseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::with(['currency', 'category', 'debt'])
            ->where('user_id', auth()->id())
            ->latest('purchase_date');

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date_from')) {
            $query->where('purchase_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('purchase_date', '<=', $request->date_to);
        }

        $purchases = $query->paginate(15);

        return Inertia::render('finance/purchases/index', [
            'purchases' => $purchases,
            'categories' => PurchaseCategory::where('user_id', auth()->id())->get(),
            'currencies' => Currency::active()->get(),
            'filters' => $request->only(['category_id', 'currency_id', 'date_from', 'date_to']),
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/purchases/create', [
            'categories' => PurchaseCategory::where('user_id', auth()->id())->get(),
            'currencies' => Currency::active()->get(),
        ]);
    }

    public function show(Purchase $purchase)
    {
        $this->authorize('view', $purchase);

        $purchase->load(['currency', 'category', 'debt.creditCard', 'debt.payments']);

        return Inertia::render('finance/purchases/show', [
            'purchase' => $purchase,
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // Handle receipt upload
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        $purchase = Purchase::create($data);

        return redirect()->route('finance.purchases.index')
            ->with('success', 'Compra registrada exitosamente.');
    }

    public function update(StorePurchaseRequest $request, Purchase $purchase)
    {
        $this->authorize('update', $purchase);

        $data = $request->validated();

        // Handle receipt upload
        if ($request->hasFile('receipt')) {
            // Delete old receipt if exists
            if ($purchase->receipt_path) {
                \Storage::disk('public')->delete($purchase->receipt_path);
            }

            $path = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        $purchase->update($data);

        return redirect()->route('finance.purchases.show', $purchase)
            ->with('success', 'Compra actualizada exitosamente.');
    }

    public function destroy(Purchase $purchase)
    {
        $this->authorize('delete', $purchase);

        // Delete receipt if exists
        if ($purchase->receipt_path) {
            \Storage::disk('public')->delete($purchase->receipt_path);
        }

        $purchase->delete();

        return redirect()->route('finance.purchases.index')
            ->with('success', 'Compra eliminada exitosamente.');
    }
}
