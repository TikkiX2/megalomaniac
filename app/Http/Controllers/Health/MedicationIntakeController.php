<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MedicationIntakeController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function store(Request $request, HealthMedication $medication): RedirectResponse
    {
        $this->authorize('update', $medication);

        $data = $request->validate([
            'taken_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(IntakeStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);
        $data['taken_at'] = Carbon::parse($data['taken_at'])->utc();

        $this->health->logIntake($request->user(), $medication, $data);

        return back()->with('success', 'Toma registrada.');
    }

    public function destroy(Request $request, HealthMedication $medication, HealthMedicationIntake $intake): RedirectResponse
    {
        $this->authorize('update', $medication);
        abort_if($intake->medication_id !== $medication->id, 404);

        $this->health->deleteIntake($request->user(), $intake);

        return back()->with('success', 'Toma eliminada.');
    }
}
