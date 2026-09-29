<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonKeyDate;
use App\People\Enums\KeyDateType;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonKeyDateController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $this->people->addKeyDate($request->user(), $person, $request->validate($this->rules()));

        return back()->with('success', 'Fecha clave agregada.');
    }

    public function update(Request $request, PersonKeyDate $keyDate): RedirectResponse
    {
        $this->authorize('update', $keyDate->person);

        $this->people->updateKeyDate($request->user(), $keyDate, $request->validate($this->rules(partial: true)));

        return back()->with('success', 'Fecha clave actualizada.');
    }

    public function destroy(Request $request, PersonKeyDate $keyDate): RedirectResponse
    {
        $this->authorize('update', $keyDate->person);

        $this->people->deleteKeyDate($request->user(), $keyDate);

        return back()->with('success', 'Fecha clave eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        return [
            'type' => [$partial ? 'sometimes' : 'required', Rule::enum(KeyDateType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => [$partial ? 'sometimes' : 'required', 'date'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ];
    }
}
