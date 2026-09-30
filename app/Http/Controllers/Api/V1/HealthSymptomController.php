<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreHealthSymptomRequest;
use App\Http\Requests\Api\UpdateHealthSymptomRequest;
use App\Http\Resources\HealthSymptomResource;
use App\Models\HealthSymptom;
use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class HealthSymptomController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HealthSymptom::query()
            ->where('user_id', $request->user()->id)
            ->with('person');

        $query->when($request->search, fn ($q, $search) => $q->where('symptom', 'like', "%{$search}%"));
        $query->when($request->severity, fn ($q, $severity) => $q->where('severity', $severity));

        return HealthSymptomResource::collection(
            $query->latest('occurred_at')->orderByDesc('id')->paginate(15)
        );
    }

    public function store(StoreHealthSymptomRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['occurred_at'] = Carbon::parse($data['occurred_at'])->utc();

        $symptom = $this->health->logSymptom($request->user(), $data);

        return (new HealthSymptomResource($symptom))->response()->setStatusCode(201);
    }

    public function show(Request $request, HealthSymptom $symptom): HealthSymptomResource
    {
        abort_if($symptom->user_id !== $request->user()->id, 403);

        return new HealthSymptomResource($symptom->load('person'));
    }

    public function update(UpdateHealthSymptomRequest $request, HealthSymptom $symptom): HealthSymptomResource
    {
        abort_if($symptom->user_id !== $request->user()->id, 403);

        $data = $request->validated();

        if (array_key_exists('occurred_at', $data)) {
            $data['occurred_at'] = Carbon::parse($data['occurred_at'])->utc();
        }

        return new HealthSymptomResource(
            $this->health->updateSymptom($request->user(), $symptom, $data)
        );
    }

    public function destroy(Request $request, HealthSymptom $symptom): JsonResponse
    {
        abort_if($symptom->user_id !== $request->user()->id, 403);

        $this->health->deleteSymptom($request->user(), $symptom);

        return response()->json(['message' => 'Symptom deleted.']);
    }
}
