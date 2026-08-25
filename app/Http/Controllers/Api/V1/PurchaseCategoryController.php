<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePurchaseCategoryRequest;
use App\Http\Resources\PurchaseCategoryResource;
use App\Models\PurchaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = PurchaseCategory::query()
            ->where('user_id', $request->user()->id)
            ->with('purchases')
            ->latest()
            ->paginate(20);

        return PurchaseCategoryResource::collection($categories);
    }

    public function store(StorePurchaseCategoryRequest $request): JsonResponse
    {
        $category = PurchaseCategory::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new PurchaseCategoryResource($category->load('purchases')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PurchaseCategory $category): PurchaseCategoryResource
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        return new PurchaseCategoryResource($category->load('purchases'));
    }

    public function update(Request $request, PurchaseCategory $category): PurchaseCategoryResource
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $category->update($request->validate([
            'name' => ['sometimes', 'string'],
            'icon' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
        ]));

        return new PurchaseCategoryResource($category->load('purchases'));
    }

    public function destroy(Request $request, PurchaseCategory $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403, 'Unauthorized.');

        $category->delete();

        return response()->json(['message' => 'Purchase category deleted.']);
    }
}
