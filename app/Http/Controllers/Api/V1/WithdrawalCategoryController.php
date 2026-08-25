<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWithdrawalCategoryRequest;
use App\Http\Resources\WithdrawalCategoryResource;
use App\Models\WithdrawalCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = WithdrawalCategory::query()
            ->where('user_id', $request->user()->id)
            ->with('withdrawals')
            ->latest()
            ->paginate(20);

        return WithdrawalCategoryResource::collection($categories);
    }

    public function store(StoreWithdrawalCategoryRequest $request): JsonResponse
    {
        $category = WithdrawalCategory::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new WithdrawalCategoryResource($category->load('withdrawals')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, WithdrawalCategory $category): WithdrawalCategoryResource
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new WithdrawalCategoryResource($category->load('withdrawals'));
    }

    public function update(Request $request, WithdrawalCategory $category): WithdrawalCategoryResource
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $category->update($request->validate([
            'name' => ['sometimes', 'string'],
            'color' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
        ]));

        return new WithdrawalCategoryResource($category->load('withdrawals'));
    }

    public function destroy(Request $request, WithdrawalCategory $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $category->delete();

        return response()->json(['message' => 'Withdrawal category deleted.']);
    }
}
