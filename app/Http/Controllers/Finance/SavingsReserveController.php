<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\SavingsReserve;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SavingsReserveController extends Controller
{
    public function index()
    {
        return Inertia::render('finance/savings-reserves/index', [
            'reserves' => SavingsReserve::where('user_id', auth()->id())
                ->with('currency')
                ->orderBy('is_active', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function (SavingsReserve $reserve) {
                    $reserve->progress = $reserve->progress();

                    return $reserve;
                }),
            'currencies' => Currency::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/savings-reserves/create', [
            'currencies' => Currency::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'goal_amount' => 'nullable|numeric|min:0',
            'current_amount' => 'required|numeric|min:0',
            'target_date' => 'nullable|date|after:today',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $validated['is_active'] = true;

        $request->user()->savingsReserves()->create($validated);

        return redirect()->route('finance.savings-reserves.index')->with('success', 'Savings reserve created successfully.');
    }

    public function edit(SavingsReserve $savingsReserve)
    {
        $this->authorize('update', $savingsReserve);

        return Inertia::render('finance/savings-reserves/edit', [
            'reserve' => $savingsReserve,
            'currencies' => Currency::all(),
        ]);
    }

    public function show(SavingsReserve $savingsReserve)
    {
        $this->authorize('view', $savingsReserve);

        $savingsReserve->load(['currency', 'transactions.currency']);
        $savingsReserve->progress = $savingsReserve->progress();

        return Inertia::render('finance/savings-reserves/show', [
            'reserve' => $savingsReserve,
        ]);
    }

    public function update(Request $request, SavingsReserve $savingsReserve)
    {
        $this->authorize('update', $savingsReserve);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'goal_amount' => 'nullable|numeric|min:0',
            'target_date' => 'nullable|date',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $savingsReserve->update($validated);

        return redirect()->back()->with('success', 'Savings reserve updated successfully.');
    }

    public function deposit(Request $request, SavingsReserve $savingsReserve)
    {
        $this->authorize('update', $savingsReserve);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $savingsReserve->deposit(
            $validated['amount'],
            $validated['date'],
            $validated['description']
        );

        return redirect()->back()->with('success', 'Deposit recorded successfully.');
    }

    public function withdraw(Request $request, SavingsReserve $savingsReserve)
    {
        $this->authorize('update', $savingsReserve);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $savingsReserve->withdraw(
            $validated['amount'],
            $validated['date'],
            $validated['description']
        );

        return redirect()->back()->with('success', 'Withdrawal recorded successfully.');
    }

    public function destroy(SavingsReserve $savingsReserve)
    {
        $this->authorize('delete', $savingsReserve);

        $savingsReserve->delete();

        return redirect()->route('finance.savings-reserves.index')->with('success', 'Savings reserve deleted successfully.');
    }
}
