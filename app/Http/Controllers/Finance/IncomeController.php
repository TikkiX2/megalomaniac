<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreIncomeRequest;
use App\Models\Currency;
use App\Models\Income;
use App\Models\IncomeSource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $query = Income::with(['currency', 'incomeSource'])
            ->where('user_id', auth()->id())
            ->latest('received_date');

        // Filters
        if ($request->filled('income_source_id')) {
            $query->where('income_source_id', $request->income_source_id);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date_from')) {
            $query->where('received_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('received_date', '<=', $request->date_to);
        }

        if ($request->filled('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }

        $incomes = $query->paginate(15);

        // Calculate summary by currency
        $summary = Income::where('user_id', auth()->id())
            ->selectRaw('currency_id, SUM(amount) as total')
            ->groupBy('currency_id')
            ->with('currency')
            ->get();

        return Inertia::render('finance/incomes/index', [
            'incomes' => $incomes,
            'incomeSources' => IncomeSource::where('user_id', auth()->id())->active()->get(),
            'currencies' => Currency::active()->get(),
            'summary' => $summary,
            'filters' => $request->only(['income_source_id', 'currency_id', 'date_from', 'date_to', 'is_recurring']),
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/incomes/create', [
            'incomeSources' => IncomeSource::where('user_id', auth()->id())->active()->get(),
            'currencies' => Currency::active()->get(),
        ]);
    }

    public function show(Income $income)
    {
        $this->authorize('view', $income);

        $income->load(['currency', 'incomeSource']);

        return Inertia::render('finance/incomes/show', [
            'income' => $income,
        ]);
    }

    public function store(StoreIncomeRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        $income = Income::create($data);

        return redirect()->route('finance.incomes.index')
            ->with('success', 'Ingreso registrado exitosamente.');
    }

    public function update(StoreIncomeRequest $request, Income $income)
    {
        $this->authorize('update', $income);

        $income->update($request->validated());

        return redirect()->route('finance.incomes.show', $income)
            ->with('success', 'Ingreso actualizado exitosamente.');
    }

    public function destroy(Income $income)
    {
        $this->authorize('delete', $income);

        $income->delete();

        return redirect()->route('finance.incomes.index')
            ->with('success', 'Ingreso eliminado exitosamente.');
    }
}
