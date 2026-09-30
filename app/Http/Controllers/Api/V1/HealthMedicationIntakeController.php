<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthMedicationIntakeRequest;
use App\Http\Resources\HealthMedicationIntakeResource;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class HealthMedicationIntakeController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request, HealthMedication $medication): AnonymousResourceCollection
    {
        abort_if($medication->user_id !== $request->user()->id, 403);

        return HealthMedicationIntakeResource::collection(
            $medication->intakes()->latest('taken_at')->paginate(15)
        );
    }

    public function store(StoreHealthMedicationIntakeRequest $request, HealthMedication $medication): JsonResponse
    {
        abort_if($medication->user_id !== $request->user()->id, 403);

        $data = $request->validated();
        $data['taken_at'] = Carbon::parse($data['taken_at'])->utc();

        $intake = $this->health->logIntake($request->user(), $medication, $data);

        return (new HealthMedicationIntakeResource($intake))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, HealthMedication $medication, HealthMedicationIntake $intake): JsonResponse
    {
        abort_if($medication->user_id !== $request->user()->id, 403);
        abort_if($intake->user_id !== $request->user()->id, 403);
        abort_if($intake->medication_id !== $medication->id, 404);

        $this->health->deleteIntake($request->user(), $intake);

        return response()->json(['message' => 'Intake deleted.']);
    }
}
