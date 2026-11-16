<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalReflectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReflectionController extends Controller
{
    public function __construct(
        private readonly JournalReflectionService $reflections,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $requested = $validated['week_start'] ?? null;

        try {
            $weekStart = $requested === null || $requested === ''
                ? $this->reflections->weekStart($user)
                : $this->reflections->parseWeekStart($user, $requested);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'week_start' => $exception->getMessage(),
            ]);
        }

        $this->reflections->generate($user, $weekStart);

        return back()->with('success', 'Reflexión guardada.');
    }
}
