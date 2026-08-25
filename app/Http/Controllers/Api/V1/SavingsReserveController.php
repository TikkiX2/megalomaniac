<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreSavingsReserveRequest;
use App\Http\Resources\SavingsReserveResource;
use App\Models\SavingsReserve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SavingsReserveController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reserves = SavingsReserve::query()
            ->where('user_id', $request->user()->id)
            ->with(['currency', 'transactions'])
            ->latest()
            ->paginate(20);

        return SavingsReserveResource::collection($reserves);
    }

    public function store(StoreSavingsReserveRequest $request): JsonResponse
    {
        $reserve = SavingsReserve::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'current_amount' => 0,
        ]);

        return (new SavingsReserveResource($reserve->load(['currency', 'transactions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SavingsReserve $reserve): SavingsReserveResource
    {
        abort_if($reserve->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new SavingsReserveResource($reserve->load(['currency', 'transactions']));
    }

    public function update(Request $request, SavingsReserve $reserve): SavingsReserveResource
    {
        abort_if($reserve->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $reserve->update($request->validate([
            'name' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'goal_amount' => ['sometimes', 'numeric'],
            'target_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]));

        return new SavingsReserveResource($reserve->load(['currency', 'transactions']));
    }

    public function destroy(Request $request, SavingsReserve $reserve): JsonResponse
    {
        abort_if($reserve->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $reserve->delete();

        return response()->json(['message' => 'Savings reserve deleted.']);
    }

    public function deposit(Request $request, SavingsReserve $reserve): SavingsReserveResource
    {
        abort_if($reserve->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $reserve->deposit(
            $validated['amount'],
            $validated['transaction_date'],
            $validated['description'] ?? null
        );

        return new SavingsReserveResource($reserve->fresh(['currency', 'transactions']));
    }

    public function withdraw(Request $request, SavingsReserve $reserve): SavingsReserveResource
    {
        abort_if($reserve->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $reserve->withdraw(
            $validated['amount'],
            $validated['transaction_date'],
            $validated['description'] ?? null
        );

        return new SavingsReserveResource($reserve->fresh(['currency', 'transactions']));
    }
}
