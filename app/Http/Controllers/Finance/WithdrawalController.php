<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Withdrawal;
use App\Models\WithdrawalCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdrawal::where('user_id', auth()->id())
            ->with(['currency', 'category']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->input('currency_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('withdrawal_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        if ($request->boolean('recurring_only')) {
            $query->where('is_recurring', true);
        }

        return Inertia::render('finance/withdrawals/index', [
            'withdrawals' => $query->latest('withdrawal_date')->paginate(20)->withQueryString(),
            'categories' => WithdrawalCategory::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
            'filters' => $request->only(['category_id', 'currency_id', 'start_date', 'end_date', 'recurring_only']),
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/withdrawals/create', [
            'categories' => WithdrawalCategory::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'category_id' => 'nullable|exists:withdrawal_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'withdrawal_date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'is_recurring' => 'boolean',
            'recurrence_frequency' => 'nullable|required_if:is_recurring,true|in:monthly,weekly,yearly',
            'recurrence_day' => 'nullable|required_if:recurrence_frequency,monthly|integer|min:1|max:31',
            'recurrence_end_date' => 'nullable|date|after:withdrawal_date',
        ]);

        $request->user()->withdrawals()->create($validated);

        return redirect()->route('finance.withdrawals.index')->with('success', 'Withdrawal recorded successfully.');
    }

    public function update(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('update', $withdrawal);

        $validated = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'category_id' => 'nullable|exists:withdrawal_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'withdrawal_date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'is_recurring' => 'boolean',
            'recurrence_frequency' => 'nullable|required_if:is_recurring,true|in:monthly,weekly,yearly',
            'recurrence_day' => 'nullable|required_if:recurrence_frequency,monthly|integer|min:1|max:31',
            'recurrence_end_date' => 'nullable|date|after:withdrawal_date',
        ]);

        $withdrawal->update($validated);

        return redirect()->back()->with('success', 'Withdrawal updated successfully.');
    }

    public function destroy(Withdrawal $withdrawal)
    {
        $this->authorize('delete', $withdrawal);

        $withdrawal->delete();

        return redirect()->back()->with('success', 'Withdrawal deleted successfully.');
    }
}
