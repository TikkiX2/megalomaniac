<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthMedicationRequest;
use App\Http\Requests\Api\UpdateHealthMedicationRequest;
use App\Http\Resources\HealthMedicationResource;
use App\Models\HealthMedication;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HealthMedicationController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HealthMedication::query()
            ->where('user_id', $request->user()->id)
            ->with('condition');

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return HealthMedicationResource::collection(
            $query->latest()->paginate(15)
        );
    }

    public function store(StoreHealthMedicationRequest $request): JsonResponse
    {
        $medication = $this->health->createMedication($request->user(), $request->validated());

        return (new HealthMedicationResource($medication))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthMedication $medication): HealthMedicationResource
    {
        abort_if($medication->user_id !== $request->user()->id, 403);

        return new HealthMedicationResource($medication->load('condition'));
    }

    public function update(UpdateHealthMedicationRequest $request, HealthMedication $medication): HealthMedicationResource
    {
        abort_if($medication->user_id !== $request->user()->id, 403);

        return new HealthMedicationResource(
            $this->health->updateMedication($request->user(), $medication, $request->validated())
        );
    }

    public function destroy(Request $request, HealthMedication $medication): JsonResponse
    {
        abort_if($medication->user_id !== $request->user()->id, 403);

        $this->health->deleteMedication($request->user(), $medication);

        return response()->json(['message' => 'Medication deleted.']);
    }
}
