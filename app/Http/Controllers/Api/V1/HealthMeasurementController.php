<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthMeasurementRequest;
use App\Http\Requests\Api\UpdateHealthMeasurementRequest;
use App\Http\Resources\HealthMeasurementResource;
use App\Models\HealthMeasurement;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class HealthMeasurementController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HealthMeasurement::query()
            ->where('user_id', $request->user()->id)
            ->with('person');

        $query->when($request->type, fn ($q, $type) => $q->where('type', $type));

        return HealthMeasurementResource::collection(
            $query->latest('measured_at')->orderByDesc('id')->paginate(15)
        );
    }

    public function store(StoreHealthMeasurementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['measured_at'] = Carbon::parse($data['measured_at'])->utc();

        $measurement = $this->health->logMeasurement($request->user(), $data);

        return (new HealthMeasurementResource($measurement))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthMeasurement $measurement): HealthMeasurementResource
    {
        abort_if($measurement->user_id !== $request->user()->id, 403);

        return new HealthMeasurementResource($measurement->load('person'));
    }

    public function update(UpdateHealthMeasurementRequest $request, HealthMeasurement $measurement): HealthMeasurementResource
    {
        abort_if($measurement->user_id !== $request->user()->id, 403);

        $data = $request->validated();

        if (array_key_exists('measured_at', $data)) {
            $data['measured_at'] = Carbon::parse($data['measured_at'])->utc();
        }

        return new HealthMeasurementResource(
            $this->health->updateMeasurement($request->user(), $measurement, $data)
        );
    }

    public function destroy(Request $request, HealthMeasurement $measurement): JsonResponse
    {
        abort_if($measurement->user_id !== $request->user()->id, 403);

        $this->health->deleteMeasurement($request->user(), $measurement);

        return response()->json(['message' => 'Measurement deleted.']);
    }
}
