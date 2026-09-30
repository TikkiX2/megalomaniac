<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthConditionRequest;
use App\Http\Requests\Api\UpdateHealthConditionRequest;
use App\Http\Resources\HealthConditionResource;
use App\Models\HealthCondition;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HealthConditionController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HealthCondition::query()
            ->where('user_id', $request->user()->id)
            ->with(['person', 'provider']);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));
        $query->when($request->status, fn ($q, $status) => $q->where('status', $status));
        $query->when($request->kind, fn ($q, $kind) => $q->where('kind', $kind));

        return HealthConditionResource::collection(
            $query->latest()->paginate(15)
        );
    }

    public function store(StoreHealthConditionRequest $request): JsonResponse
    {
        $condition = $this->health->createCondition($request->user(), $request->validated());

        return (new HealthConditionResource($condition))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthCondition $condition): HealthConditionResource
    {
        abort_if($condition->user_id !== $request->user()->id, 403);

        return new HealthConditionResource($condition->load(['person', 'provider']));
    }

    public function update(UpdateHealthConditionRequest $request, HealthCondition $condition): HealthConditionResource
    {
        abort_if($condition->user_id !== $request->user()->id, 403);

        return new HealthConditionResource(
            $this->health->updateCondition($request->user(), $condition, $request->validated())
        );
    }

    public function destroy(Request $request, HealthCondition $condition): JsonResponse
    {
        abort_if($condition->user_id !== $request->user()->id, 403);

        $this->health->deleteCondition($request->user(), $condition);

        return response()->json(['message' => 'Condition deleted.']);
    }
}
