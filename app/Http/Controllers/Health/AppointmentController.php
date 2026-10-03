<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthAppointment;
use App\Models\HealthProfessional;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = HealthAppointment::query()
            ->where('user_id', $request->user()->id)
            ->with(['person:id,first_name,last_name', 'provider:id,name']);

        $query->when($request->search, fn ($q, $search) => $q->where('title', 'like', "%{$search}%"));
        $query->when($request->status, fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('health/appointments/Index', [
            'appointments' => $query->latest('scheduled_at')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'statusOptions' => AppointmentStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('health/appointments/Form', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request));
        $data['user_id'] = $request->user()->id;

        HealthAppointment::create($data);

        return redirect()->route('health.appointments.index')->with('success', 'Cita creada.');
    }

    public function edit(Request $request, HealthAppointment $appointment): Response
    {
        $this->authorize('update', $appointment);

        return Inertia::render('health/appointments/Form', [
            'appointment' => $appointment->load('person:id,first_name,last_name'),
            ...$this->formProps($request),
        ]);
    }

    public function update(Request $request, HealthAppointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $appointment->update($request->validate($this->rules($request, true)));

        return redirect()->route('health.appointments.index')->with('success', 'Cita actualizada.');
    }

    public function destroy(Request $request, HealthAppointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return redirect()->route('health.appointments.index')->with('success', 'Cita eliminada.');
    }

    /** @return array<string, mixed> */
    private function formProps(Request $request): array
    {
        return [
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']),
            'providers' => HealthProfessional::where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statusOptions' => AppointmentStatus::values(),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, bool $partial = false): array
    {
        return [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'scheduled_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'status' => [$partial ? 'sometimes' : 'required', Rule::enum(AppointmentStatus::class)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
