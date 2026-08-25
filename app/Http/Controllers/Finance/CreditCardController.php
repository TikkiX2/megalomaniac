<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditCardController extends Controller
{
    public function index()
    {
        $creditCards = CreditCard::with(['owner', 'debts'])
            ->where('user_id', auth()->id())
            ->withCount(['debts as active_debts_count' => function ($query) {
                $query->where('status', '!=', 'paid');
            }])
            ->get();

        return Inertia::render('finance/credit-cards/index', [
            'creditCards' => $creditCards,
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/credit-cards/create', [
            'users' => User::where('id', '!=', auth()->id())->get(['id', 'name']),
        ]);
    }

    public function show(CreditCard $creditCard)
    {
        $this->authorize('view', $creditCard);

        $creditCard->load(['owner', 'debts.purchase', 'debts.currency']);

        return Inertia::render('finance/credit-cards/show', [
            'creditCard' => $creditCard,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'is_mine' => ['boolean'],
            'interest_rate' => ['numeric', 'min:0', 'max:100'],
            'tax_percentage' => ['numeric', 'min:0', 'max:100'],
            'apply_interest' => ['boolean'],
            'apply_tax' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['user_id'] = auth()->id();

        $creditCard = CreditCard::create($data);

        return redirect()->route('finance.credit-cards.index')
            ->with('success', 'Tarjeta de crédito registrada exitosamente.');
    }

    public function update(Request $request, CreditCard $creditCard)
    {
        $this->authorize('update', $creditCard);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_four_digits' => ['nullable', 'string', 'size:4'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'is_mine' => ['boolean'],
            'interest_rate' => ['numeric', 'min:0', 'max:100'],
            'tax_percentage' => ['numeric', 'min:0', 'max:100'],
            'apply_interest' => ['boolean'],
            'apply_tax' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $creditCard->update($data);

        return redirect()->route('finance.credit-cards.show', $creditCard)
            ->with('success', 'Tarjeta de crédito actualizada exitosamente.');
    }

    public function destroy(CreditCard $creditCard)
    {
        $this->authorize('delete', $creditCard);

        // Check if card has active debts
        if ($creditCard->debts()->where('status', '!=', 'paid')->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar una tarjeta con deudas activas.',
            ]);
        }

        $creditCard->delete();

        return redirect()->route('finance.credit-cards.index')
            ->with('success', 'Tarjeta de crédito eliminada exitosamente.');
    }
}
