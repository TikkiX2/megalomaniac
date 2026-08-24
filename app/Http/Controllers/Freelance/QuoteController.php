<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia; // Or TCPDF wrapper

// I will use a Service for PDF generation as per plan.

class QuoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $quotes = Quote::query()
            ->with(['client', 'project', 'currency'])
            ->where('user_id', $request->user()->id)
            ->when($request->search, function ($query, $search) {
                $query->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('freelance/quotes/Index', [
            'quotes' => $quotes,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('freelance/quotes/Form', [
            'clients' => Client::where('user_id', auth()->id())->get(),
            'projects' => Project::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
            'nextQuoteNumber' => Quote::generateQuoteNumber(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'title' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'currency_id' => 'required|exists:currencies,id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'items' => 'array',
            'items.*.description' => 'required|string',
            'items.*.hours' => 'nullable|numeric',
            'items.*.hourly_rate' => 'nullable|numeric',
            'items.*.subtotal' => 'required|numeric',
        ]);

        $validated['quote_number'] = Quote::generateQuoteNumber();
        $validated['user_id'] = $request->user()->id;

        // Calculate totals
        $subtotal = collect($request->items)->sum('subtotal');
        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal; // Add tax logic later if needed or from request

        $quote = Quote::create($validated);

        if ($request->has('items')) {
            foreach ($request->items as $index => $item) {
                $quote->items()->create([
                    'description' => $item['description'],
                    'hours' => $item['hours'] ?? 0,
                    'hourly_rate' => $item['hourly_rate'] ?? 0,
                    'subtotal' => $item['subtotal'],
                    'order' => $index,
                ]);
            }
        }

        return redirect()->route('freelance.quotes.show', $quote)
            ->with('success', 'Cotización creada.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote)
    {
        $quote->load(['client', 'project', 'currency', 'items']);

        return Inertia::render('freelance/quotes/Show', [
            'quote' => $quote,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quote $quote)
    {
        $quote->load('items');

        return Inertia::render('freelance/quotes/Form', [
            'quote' => $quote,
            'clients' => Client::where('user_id', auth()->id())->get(),
            'projects' => Project::where('user_id', auth()->id())->get(),
            'currencies' => Currency::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quote $quote)
    {
        // Validation similar to store...
        // Logic to update items (delete all and recreate is easiest for now, or sync)

        // For brevity I'll skip full implementation details here but user can fill it.
        // Actually I should implement it.

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'title' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'currency_id' => 'required|exists:currencies,id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
            'items' => 'array',
        ]);

        // Calculate totals...
        $subtotal = collect($request->items)->sum('subtotal');
        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal;

        $quote->update($validated);

        $quote->items()->delete();
        if ($request->has('items')) {
            foreach ($request->items as $index => $item) {
                $quote->items()->create([
                    'description' => $item['description'],
                    'hours' => $item['hours'] ?? 0,
                    'hourly_rate' => $item['hourly_rate'] ?? 0,
                    'subtotal' => $item['subtotal'],
                    'order' => $index,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Cotización actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('freelance.quotes.index')
            ->with('success', 'Cotización eliminada.');
    }

    public function generatePDF(Quote $quote)
    {
        // Placeholder for now
        // $service = new QuotePDFService();
        // return $service->generate($quote);
        return response('PDF implementation pending');
    }

    public function duplicate(Quote $quote)
    {
        $newQuote = $quote->replicate();
        $newQuote->quote_number = Quote::generateQuoteNumber();
        $newQuote->status = 'draft';
        $newQuote->issue_date = now();
        $newQuote->save();

        foreach ($quote->items as $item) {
            $newQuote->items()->create($item->toArray());
        }

        return redirect()->route('freelance.quotes.edit', $newQuote)
            ->with('success', 'Cotización duplicada.');
    }

    public function convertToProject(Quote $quote)
    {
        // Conversion logic
        $project = new Project;
        $project->user_id = $quote->user_id;
        $project->client_id = $quote->client_id;
        $project->name = $quote->title ?? 'Proyecto de '.$quote->quote_number;
        $project->currency_id = $quote->currency_id;
        $project->total_amount = $quote->total;
        $project->hourly_rate = $quote->hourly_rate;
        $project->status = 'pending';
        $project->save();

        // Link quote
        $quote->project_id = $project->id;
        $quote->status = 'accepted';
        $quote->save();

        return redirect()->route('freelance.projects.edit', $project)
            ->with('success', 'Proyecto creado desde cotización.');
    }
}
