<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonInteractionRequest;
use App\Http\Resources\PersonInteractionResource;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonInteractionController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request, Person $person): AnonymousResourceCollection
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return PersonInteractionResource::collection(
            $person->interactions()->latest('occurred_at')->paginate(20)
        );
    }

    public function store(StorePersonInteractionRequest $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $interaction = $this->people->logInteraction($request->user(), $person, $request->validated());

        return (new PersonInteractionResource($interaction))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Person $person, PersonInteraction $interaction): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);
        abort_if($interaction->person_id !== $person->id, 404);

        $this->people->deleteInteraction($request->user(), $interaction);

        return response()->json(['message' => 'Interaction deleted.']);
    }
}
