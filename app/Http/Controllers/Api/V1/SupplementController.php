<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreSupplementRequest;
use App\Http\Resources\SupplementLogResource;
use App\Http\Resources\SupplementResource;
use App\Models\Supplement;
use App\Models\SupplementLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $supplements = Supplement::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return SupplementResource::collection($supplements);
    }

    public function store(StoreSupplementRequest $request): JsonResponse
    {
        $supplement = Supplement::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return (new SupplementResource($supplement))
            ->response()
            ->setStatusCode(201);
    }

    public function logIntake(Supplement $supplement, Request $request): JsonResponse
    {
        if ($supplement->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $log = SupplementLog::create([
            'supplement_id' => $supplement->id,
            'user_id' => $request->user()->id,
            'taken_at' => $request->get('taken_at', now()),
        ]);

        return (new SupplementLogResource($log->load('supplement')))
            ->response()
            ->setStatusCode(201);
    }

    public function getLogs(Supplement $supplement, Request $request): AnonymousResourceCollection
    {
        if ($supplement->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $logs = SupplementLog::where('supplement_id', $supplement->id)
            ->latest('taken_at')
            ->paginate(20);

        return SupplementLogResource::collection($logs);
    }
}
