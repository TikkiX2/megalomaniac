<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthProfessionalRequest;
use App\Http\Requests\Api\UpdateHealthProfessionalRequest;
use App\Http\Resources\HealthProfessionalResource;
use App\Models\HealthProfessional;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HealthProfessionalController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HealthProfessional::query()->where('user_id', $request->user()->id);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));
        $query->when($request->type, fn ($q, $type) => $q->where('type', $type));

        return HealthProfessionalResource::collection(
            $query->latest()->paginate(15)
        );
    }

    public function store(StoreHealthProfessionalRequest $request): JsonResponse
    {
        $professional = $this->health->createProfessional($request->user(), $request->validated());

        return (new HealthProfessionalResource($professional))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthProfessional $professional): HealthProfessionalResource
    {
        abort_if($professional->user_id !== $request->user()->id, 403);

        return new HealthProfessionalResource($professional);
    }

    public function update(UpdateHealthProfessionalRequest $request, HealthProfessional $professional): HealthProfessionalResource
    {
        abort_if($professional->user_id !== $request->user()->id, 403);

        return new HealthProfessionalResource(
            $this->health->updateProfessional($request->user(), $professional, $request->validated())
        );
    }

    public function destroy(Request $request, HealthProfessional $professional): JsonResponse
    {
        abort_if($professional->user_id !== $request->user()->id, 403);

        $this->health->deleteProfessional($request->user(), $professional);

        return response()->json(['message' => 'Professional deleted.']);
    }
}
