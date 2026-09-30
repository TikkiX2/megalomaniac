<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\ProfessionalType;
use App\Http\Controllers\Controller;
use App\Models\HealthProfessional;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthProfessional::query()->where('user_id', $request->user()->id);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));
        $query->when($request->type, fn ($q, $type) => $q->where('type', $type));

        return Inertia::render('health/professionals/Index', [
            'professionals' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'type']),
            'typeOptions' => ProfessionalType::values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('health/professionals/Form', [
            'typeOptions' => ProfessionalType::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->health->createProfessional($request->user(), $request->validate($this->rules()));

        return redirect()->route('health.professionals.index')->with('success', 'Profesional creado.');
    }

    public function edit(HealthProfessional $professional): Response
    {
        $this->authorize('update', $professional);

        return Inertia::render('health/professionals/Form', [
            'professional' => $professional,
            'typeOptions' => ProfessionalType::values(),
        ]);
    }

    public function update(Request $request, HealthProfessional $professional): RedirectResponse
    {
        $this->authorize('update', $professional);

        $this->health->updateProfessional($request->user(), $professional, $request->validate($this->rules(partial: true)));

        return redirect()->route('health.professionals.index')->with('success', 'Profesional actualizado.');
    }

    public function destroy(Request $request, HealthProfessional $professional): RedirectResponse
    {
        $this->authorize('delete', $professional);

        $this->health->deleteProfessional($request->user(), $professional);

        return redirect()->route('health.professionals.index')->with('success', 'Profesional eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        return [
            'type' => [$partial ? 'sometimes' : 'required', Rule::enum(ProfessionalType::class)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
