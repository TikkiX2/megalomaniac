<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonInteractionController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate([
            'channel' => ['required', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->people->logInteraction($request->user(), $person, $validated);

        return back()->with('success', 'Interacción registrada.');
    }

    public function quickLog(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate([
            'channel' => ['nullable', Rule::enum(InteractionChannel::class)],
        ]);

        $this->people->logInteraction($request->user(), $person, [
            'channel' => $validated['channel'] ?? InteractionChannel::Message->value,
            'occurred_at' => now(),
            'title' => 'Contacto rápido',
        ]);

        return back()->with('success', 'Contacto registrado.');
    }

    public function destroy(Request $request, PersonInteraction $interaction): RedirectResponse
    {
        abort_if($interaction->user_id !== $request->user()->id, 403);

        $this->people->deleteInteraction($request->user(), $interaction);

        return back()->with('success', 'Interacción eliminada.');
    }
}
