<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonKeyDateRequest;
use App\Http\Requests\Api\UpdatePersonKeyDateRequest;
use App\Http\Resources\PersonKeyDateResource;
use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonKeyDateController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request, Person $person): AnonymousResourceCollection
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return PersonKeyDateResource::collection(
            $person->keyDates()->paginate(20)
        );
    }

    public function store(StorePersonKeyDateRequest $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $keyDate = $this->people->addKeyDate($request->user(), $person, $request->validated());

        return (new PersonKeyDateResource($keyDate))->response()->setStatusCode(201);
    }

    public function update(UpdatePersonKeyDateRequest $request, PersonKeyDate $keyDate): PersonKeyDateResource
    {
        abort_if($keyDate->user_id !== $request->user()->id, 403);

        return new PersonKeyDateResource(
            $this->people->updateKeyDate($request->user(), $keyDate, $request->validated())
        );
    }

    public function destroy(Request $request, PersonKeyDate $keyDate): JsonResponse
    {
        abort_if($keyDate->user_id !== $request->user()->id, 403);

        $this->people->deleteKeyDate($request->user(), $keyDate);

        return response()->json(['message' => 'Key date deleted.']);
    }
}
