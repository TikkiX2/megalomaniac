<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request): Response
    {
        $query = Person::query()
            ->where('user_id', $request->user()->id)
            ->with('media');

        $query->when($request->search, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request->closeness, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $request->boolean('archived')
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when($request->boolean('favorites'), fn ($q) => $q->where('is_favorite', true));

        $query->when($request->stale_days, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('people/Index', [
            'people' => $people,
            'filters' => $request->only(['search', 'closeness', 'favorites', 'archived', 'stale_days']),
            'closenessOptions' => Closeness::values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('people/Form', [
            'closenessOptions' => Closeness::values(),
            'relationshipOptions' => RelationshipStatus::values(),
            'channelOptions' => PreferredContactChannel::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $person = $this->people->createPerson($request->user(), $validated);

        return redirect()->route('people.show', $person)->with('success', 'Persona creada.');
    }

    public function show(Person $person): Response
    {
        $this->authorize('view', $person);

        $person->load(['keyDates', 'socials', 'media']);

        return Inertia::render('people/Show', [
            'person' => $person,
            'interactions' => $person->interactions()
                ->latest('occurred_at')
                ->paginate(30)
                ->withQueryString(),
            'upcoming' => $this->people->upcoming(request()->user(), 60, $person),
            'channelOptions' => InteractionChannel::values(),
        ]);
    }

    public function edit(Person $person): Response
    {
        $this->authorize('update', $person);

        return Inertia::render('people/Form', [
            'person' => $person,
            'closenessOptions' => Closeness::values(),
            'relationshipOptions' => RelationshipStatus::values(),
            'channelOptions' => PreferredContactChannel::values(),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate($this->rules());

        $this->people->updatePerson($request->user(), $person, $validated);

        return redirect()->route('people.show', $person)->with('success', 'Persona actualizada.');
    }

    public function destroy(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);

        $this->people->deletePerson($request->user(), $person);

        return redirect()->route('people.index')->with('success', 'Persona eliminada.');
    }

    public function calendar(Request $request): Response
    {
        return Inertia::render('people/Calendar', [
            'people' => Person::query()
                ->where('user_id', $request->user()->id)
                ->visible()
                ->with('media')
                ->get(['id', 'first_name', 'last_name', 'birthday']),
            'keyDates' => PersonKeyDate::query()
                ->where('user_id', $request->user()->id)
                ->with('person:id,first_name,last_name')
                ->get(),
        ]);
    }

    public function timeline(Request $request): Response
    {
        $interactions = PersonInteraction::query()
            ->where('user_id', $request->user()->id)
            ->with('person:id,first_name,last_name')
            ->latest('occurred_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('people/Timeline', ['interactions' => $interactions]);
    }

    public function uploadAvatar(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $person->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return back()->with('success', 'Avatar actualizado.');
    }

    public function destroyAvatar(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $person->clearMediaCollection('avatar');

        return back()->with('success', 'Avatar eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['required', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['boolean'],
            'is_archived' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
