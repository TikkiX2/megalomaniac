<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonSocialRequest;
use App\Http\Requests\Api\UpdatePersonSocialRequest;
use App\Http\Resources\PersonSocialResource;
use App\Models\Person;
use App\Models\PersonSocial;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonSocialController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request, Person $person): AnonymousResourceCollection
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return PersonSocialResource::collection(
            $person->socials()->paginate(20)
        );
    }

    public function store(StorePersonSocialRequest $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $social = $this->people->addSocial($request->user(), $person, $request->validated());

        return (new PersonSocialResource($social))->response()->setStatusCode(201);
    }

    public function update(UpdatePersonSocialRequest $request, PersonSocial $social): PersonSocialResource
    {
        $social->loadMissing('person');

        abort_if($social->person?->user_id !== $request->user()->id, 403);

        return new PersonSocialResource(
            $this->people->updateSocial($request->user(), $social, $request->validated())
        );
    }

    public function destroy(Request $request, PersonSocial $social): JsonResponse
    {
        $social->loadMissing('person');

        abort_if($social->person?->user_id !== $request->user()->id, 403);

        $this->people->deleteSocial($request->user(), $social);

        return response()->json(['message' => 'Social deleted.']);
    }
}
