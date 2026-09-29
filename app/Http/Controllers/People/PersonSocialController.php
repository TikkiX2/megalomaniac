<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonSocial;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonSocialController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $this->people->addSocial($request->user(), $person, $request->validate($this->rules()));

        return back()->with('success', 'Red agregada.');
    }

    public function update(Request $request, PersonSocial $social): RedirectResponse
    {
        $this->authorize('update', $social->person);

        $this->people->updateSocial($request->user(), $social, $request->validate($this->rules(partial: true)));

        return back()->with('success', 'Red actualizada.');
    }

    public function destroy(Request $request, PersonSocial $social): RedirectResponse
    {
        $this->authorize('update', $social->person);

        $this->people->deleteSocial($request->user(), $social);

        return back()->with('success', 'Red eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        return [
            'network' => [$partial ? 'sometimes' : 'required', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
