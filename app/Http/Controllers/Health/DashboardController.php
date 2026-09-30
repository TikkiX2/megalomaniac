<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        return Inertia::render('health/Dashboard', [
            'summary' => $this->health->summary($request->user()),
        ]);
    }
}
