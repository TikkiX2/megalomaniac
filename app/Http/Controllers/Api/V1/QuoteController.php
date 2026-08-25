<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quote::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'client', 'project']);

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%'.$request->get('search').'%');
        }

        $quotes = $query->latest()->paginate(20);

        return QuoteResource::collection($quotes);
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $items = $request->get('items', []);

        $quote = Quote::create([
            'user_id' => $request->user()->id,
            'client_id' => $request->get('client_id'),
            'project_id' => $request->get('project_id'),
            'title' => $request->get('title'),
            'issue_date' => $request->get('issue_date'),
            'valid_until' => $request->get('valid_until'),
            'status' => $request->get('status', 'draft'),
            'subtotal' => $request->get('subtotal'),
            'tax_percentage' => $request->get('tax_percentage'),
            'tax_amount' => $request->get('tax_amount'),
            'total' => $request->get('total'),
            'currency_id' => $request->get('currency_id'),
            'hourly_rate' => $request->get('hourly_rate'),
            'notes' => $request->get('notes'),
            'terms_and_conditions' => $request->get('terms_and_conditions'),
            'quote_number' => Quote::generateQuoteNumber(),
        ]);

        foreach ($items as $index => $item) {
            QuoteItem::create([
                'quote_id' => $quote->id,
                'description' => $item['description'],
                'hours' => $item['hours'],
                'hourly_rate' => $item['hourly_rate'] ?? $quote->hourly_rate,
                'subtotal' => ($item['hours'] * ($item['hourly_rate'] ?? $quote->hourly_rate)),
                'order' => $index + 1,
            ]);
        }

        return (new QuoteResource($quote->load(['items', 'client', 'project'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Quote $quote, Request $request): QuoteResource
    {
        abort_if($quote->user_id !== $request->user()->id, 403);

        return new QuoteResource($quote->load(['items', 'client', 'project']));
    }

    public function update(StoreQuoteRequest $request, Quote $quote): JsonResponse
    {
        abort_if($quote->user_id !== $request->user()->id, 403);

        $quote->update($request->except('items'));

        if ($request->has('items')) {
            $quote->items()->delete();
            $items = $request->get('items');
            foreach ($items as $index => $item) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'description' => $item['description'],
                    'hours' => $item['hours'],
                    'hourly_rate' => $item['hourly_rate'] ?? $quote->hourly_rate,
                    'subtotal' => ($item['hours'] * ($item['hourly_rate'] ?? $quote->hourly_rate)),
                    'order' => $index + 1,
                ]);
            }
        }

        return new QuoteResource($quote->fresh(['items', 'client', 'project']));
    }

    public function destroy(Quote $quote, Request $request): JsonResponse
    {
        abort_if($quote->user_id !== $request->user()->id, 403);

        $quote->delete();

        return response()->json(['message' => 'Quote deleted.']);
    }
}
