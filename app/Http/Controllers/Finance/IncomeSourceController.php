<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreIncomeSourceRequest;
use App\Models\Currency;
use App\Models\IncomeSource;
use Inertia\Inertia;

class IncomeSourceController extends Controller
{
    public function index()
    {
        $sources = IncomeSource::with('defaultCurrency')
            ->where('user_id', auth()->id())
            ->get();

        return Inertia::render('finance/income-sources/index', [
            'sources' => $sources,
            'currencies' => Currency::active()->get(),
        ]);
    }

    public function store(StoreIncomeSourceRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        IncomeSource::create($data);

        return redirect()->route('finance.income-sources.index')
            ->with('success', 'Fuente de ingresos creada exitosamente.');
    }

    public function update(StoreIncomeSourceRequest $request, IncomeSource $incomeSource)
    {
        if ($incomeSource->user_id !== auth()->id()) {
            abort(403);
        }

        $incomeSource->update($request->validated());

        return redirect()->route('finance.income-sources.index')
            ->with('success', 'Fuente de ingresos actualizada exitosamente.');
    }

    public function destroy(IncomeSource $incomeSource)
    {
        if ($incomeSource->user_id !== auth()->id()) {
            abort(403);
        }

        $incomeSource->delete();

        return redirect()->route('finance.income-sources.index')
            ->with('success', 'Fuente de ingresos eliminada exitosamente.');
    }
}
