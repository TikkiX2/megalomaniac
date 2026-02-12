<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreDebtRequest;
use App\Models\CreditCard;
use App\Models\Currency;
use App\Models\Debt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $query = Debt::with(['currency', 'purchase', 'creditCard', 'payments'])
            ->where('user_id', auth()->id())
            ->latest('created_at');

        // Filters
        if ($request->filled('credit_card_id')) {
            $query->where('credit_card_id', $request->credit_card_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('overdue')) {
            $query->overdue();
        }

        $debts = $query->paginate(15);

        // Calculate summary by currency
        $summary = Debt::where('user_id', auth()->id())
            ->where('status', '!=', 'paid')
            ->selectRaw('currency_id, SUM(remaining_amount) as total')
            ->groupBy('currency_id')
            ->with('currency')
            ->get();

        return Inertia::render('finance/debts/index', [
            'debts' => $debts,
            'creditCards' => CreditCard::where('user_id', auth()->id())->get(),
            'currencies' => Currency::active()->get(),
            'summary' => $summary,
            'filters' => $request->only(['credit_card_id', 'status', 'overdue']),
        ]);
    }

    public function create()
    {
        return Inertia::render('finance/debts/create', [
            'creditCards' => CreditCard::where('user_id', auth()->id())->get(),
            'currencies' => Currency::active()->get(),
        ]);
    }

    public function show(Debt $debt)
    {
        $this->authorize('view', $debt);

        $debt->load(['currency', 'purchase', 'creditCard', 'payments.currency']);

        return Inertia::render('finance/debts/show', [
            'debt' => $debt,
        ]);
    }

    public function store(StoreDebtRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        $originalAmount = $data['original_amount'];
        $interestAmount = 0;
        $taxAmount = 0;

        // Calculate interest and tax only if credit card is selected
        if (! empty($data['credit_card_id'])) {
            $creditCard = CreditCard::findOrFail($data['credit_card_id']);
            $interestAmount = $creditCard->calculateInterest($originalAmount);
            $taxAmount = $creditCard->calculateTax($originalAmount);
        }

        $totalAmount = $originalAmount + $interestAmount + $taxAmount;

        $data['interest_amount'] = $interestAmount;
        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = $totalAmount;
        $data['remaining_amount'] = $totalAmount;

        $debt = Debt::create($data);

        return redirect()->route('finance.debts.index')
            ->with('success', 'Deuda registrada exitosamente.');
    }

    public function update(StoreDebtRequest $request, Debt $debt)
    {
        $this->authorize('update', $debt);

        $data = $request->validated();

        // Recalculate if amount or credit card changed
        if (isset($data['original_amount']) || array_key_exists('credit_card_id', $data)) {
            $originalAmount = $data['original_amount'] ?? $debt->original_amount;
            $interestAmount = 0;
            $taxAmount = 0;

            $cardId = $data['credit_card_id'] ?? $debt->credit_card_id;

            if ($cardId) {
                $creditCard = CreditCard::findOrFail($cardId);
                $interestAmount = $creditCard->calculateInterest($originalAmount);
                $taxAmount = $creditCard->calculateTax($originalAmount);
            }

            $totalAmount = $originalAmount + $interestAmount + $taxAmount;

            $data['interest_amount'] = $interestAmount;
            $data['tax_amount'] = $taxAmount;
            $data['total_amount'] = $totalAmount;

            // Adjust remaining amount proportionally
            $paidAmount = $debt->total_amount - $debt->remaining_amount;
            $data['remaining_amount'] = max(0, $totalAmount - $paidAmount);
        }

        $debt->update($data);

        return redirect()->route('finance.debts.show', $debt)
            ->with('success', 'Deuda actualizada exitosamente.');
    }

    public function destroy(Debt $debt)
    {
        $this->authorize('delete', $debt);

        $debt->delete();

        return redirect()->route('finance.debts.index')
            ->with('success', 'Deuda eliminada exitosamente.');
    }

    public function addPayment(Request $request, Debt $debt)
    {
        $this->authorize('update', $debt);

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$debt->remaining_amount],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $debt->addPayment(
            $request->amount,
            $request->payment_date,
            $request->notes
        );

        return redirect()->route('finance.debts.show', $debt)
            ->with('success', 'Pago registrado exitosamente.');
    }
}
