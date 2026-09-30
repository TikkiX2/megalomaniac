<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Health\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthDashboardController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->health->summary($request->user()),
        ]);
    }
}
