<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHealthMedicationScheduleRequest;
use App\Http\Requests\Api\UpdateHealthMedicationScheduleRequest;
use App\Http\Resources\HealthMedicationScheduleResource;
use App\Models\HealthMedicationSchedule;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HealthMedicationScheduleController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $schedules = HealthMedicationSchedule::query()
            ->where('user_id', $request->user()->id)
            ->with('medication')
            ->latest()
            ->paginate(15);

        return HealthMedicationScheduleResource::collection($schedules);
    }

    public function store(StoreHealthMedicationScheduleRequest $request): JsonResponse
    {
        $schedule = $this->health->createSchedule($request->user(), $request->validated());

        return (new HealthMedicationScheduleResource($schedule->load('medication')))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthMedicationSchedule $schedule): HealthMedicationScheduleResource
    {
        abort_if($schedule->user_id !== $request->user()->id, 403);

        return new HealthMedicationScheduleResource($schedule->load('medication'));
    }

    public function update(UpdateHealthMedicationScheduleRequest $request, HealthMedicationSchedule $schedule): HealthMedicationScheduleResource
    {
        abort_if($schedule->user_id !== $request->user()->id, 403);

        $updated = $this->health->updateSchedule($request->user(), $schedule, $request->validated());

        return new HealthMedicationScheduleResource($updated->load('medication'));
    }

    public function destroy(Request $request, HealthMedicationSchedule $schedule): JsonResponse
    {
        abort_if($schedule->user_id !== $request->user()->id, 403);

        $this->health->deleteSchedule($request->user(), $schedule);

        return response()->json(['message' => 'Schedule deleted.']);
    }
}
