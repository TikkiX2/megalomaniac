<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonRequest;
use App\Http\Requests\Api\UpdatePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Person::query()->where('user_id', $request->user()->id)->with('media');

        $query->when($request->search, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%");
        }));

        $query->when($request->closeness, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $request->boolean('archived')
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when($request->boolean('favorite'), fn ($q) => $q->where('is_favorite', true));

        $query->when($request->stale_days, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        return PersonResource::collection(
            $query->orderByDesc('is_favorite')->orderBy('first_name')->paginate(15)
        );
    }

    public function store(StorePersonRequest $request): JsonResponse
    {
        $person = $this->people->createPerson($request->user(), $request->validated());

        return (new PersonResource($person))->response()->setStatusCode(201);
    }

    public function show(Request $request, Person $person): PersonResource
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return new PersonResource($person->load(['keyDates', 'socials']));
    }

    public function update(UpdatePersonRequest $request, Person $person): PersonResource
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return new PersonResource(
            $this->people->updatePerson($request->user(), $person, $request->validated())
        );
    }

    public function destroy(Request $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $this->people->deletePerson($request->user(), $person);

        return response()->json(['message' => 'Person deleted.']);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);

        return response()->json([
            'data' => $this->people->upcoming($request->user(), $days)->all(),
        ]);
    }
}
