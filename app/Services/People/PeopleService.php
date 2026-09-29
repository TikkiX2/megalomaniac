<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PeopleService
{
    public function findPerson(User $user, int $personId): Person
    {
        $person = Person::where('user_id', $user->id)->find($personId);

        if (! $person) {
            throw new ModelNotFoundException('Person not found.');
        }

        return $person;
    }

    public function createPerson(User $user, array $data): Person
    {
        return $user->people()->create($data);
    }

    public function updatePerson(User $user, Person $person, array $data): Person
    {
        $this->assertOwner($user, $person);
        $person->update($data);

        return $person->refresh();
    }

    public function deletePerson(User $user, Person $person): void
    {
        $this->assertOwner($user, $person);
        $person->delete();
    }

    public function logInteraction(User $user, Person $person, array $data): PersonInteraction
    {
        $this->assertOwner($user, $person);

        return $person->interactions()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function deleteInteraction(User $user, PersonInteraction $interaction): void
    {
        $this->assertOwner($user, $interaction);
        $interaction->delete();
    }

    public function addKeyDate(User $user, Person $person, array $data): PersonKeyDate
    {
        $this->assertOwner($user, $person);

        return $person->keyDates()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function updateKeyDate(User $user, PersonKeyDate $keyDate, array $data): PersonKeyDate
    {
        $this->assertOwner($user, $keyDate);
        $keyDate->update($data);

        return $keyDate->refresh();
    }

    public function deleteKeyDate(User $user, PersonKeyDate $keyDate): void
    {
        $this->assertOwner($user, $keyDate);
        $keyDate->delete();
    }

    public function addSocial(User $user, Person $person, array $data): PersonSocial
    {
        $this->assertOwner($user, $person);

        return $person->socials()->create($data);
    }

    public function updateSocial(User $user, PersonSocial $social, array $data): PersonSocial
    {
        $this->assertSocialOwner($user, $social);
        $social->update($data);

        return $social->refresh();
    }

    public function deleteSocial(User $user, PersonSocial $social): void
    {
        $this->assertSocialOwner($user, $social);
        $social->delete();
    }

    /**
     * Cumpleaños y fechas clave dentro de los próximos N días.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcoming(User $user, int $days = 30, ?Person $person = null): Collection
    {
        $today = now()->startOfDay();
        $items = collect();

        $people = Person::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->when($person, fn ($query) => $query->whereKey($person->id))
            ->get(['id', 'first_name', 'last_name', 'birthday']);

        foreach ($people as $subject) {
            if ($subject->birthday === null) {
                continue;
            }

            $next = $this->nextAnnualOccurrence($subject->birthday);

            if ($today->diffInDays($next, false) > $days) {
                continue;
            }

            $items->push([
                'kind' => 'birthday',
                'label' => 'Cumpleaños',
                'next_occurrence' => $next->toDateString(),
                'days_until' => (int) $today->diffInDays($next, false),
                'reminds_days_before' => 7,
                'person' => [
                    'id' => $subject->id,
                    'name' => $subject->full_name,
                ],
            ]);
        }

        $keyDates = PersonKeyDate::with('person:id,first_name,last_name')
            ->where('user_id', $user->id)
            ->whereHas('person', fn ($query) => $query->where('is_archived', false))
            ->when($person, fn ($query) => $query->where('person_id', $person->id))
            ->get();

        foreach ($keyDates as $keyDate) {
            $next = $keyDate->nextOccurrence();

            if ($next === null || $today->diffInDays($next, false) > $days) {
                continue;
            }

            $items->push([
                'kind' => 'key_date',
                'label' => $keyDate->display_label,
                'next_occurrence' => $next->toDateString(),
                'days_until' => (int) $today->diffInDays($next, false),
                'reminds_days_before' => $keyDate->remind_days_before,
                'person' => [
                    'id' => $keyDate->person?->id,
                    'name' => $keyDate->person?->full_name,
                ],
            ]);
        }

        return new Collection($items->sortBy('days_until')->values()->all());
    }

    private function nextAnnualOccurrence(CarbonInterface $date): CarbonInterface
    {
        $candidate = $date->copy()->year((int) now()->year);

        if ($candidate->lt(now()->startOfDay())) {
            $candidate = $candidate->addYear();
        }

        return $candidate;
    }

    private function assertOwner(User $user, Model $model): void
    {
        if ((int) $model->user_id !== (int) $user->id) {
            throw new ModelNotFoundException(class_basename($model).' not found.');
        }
    }

    private function assertSocialOwner(User $user, PersonSocial $social): void
    {
        if ((int) $social->person?->user_id !== (int) $user->id) {
            throw new ModelNotFoundException('PersonSocial not found.');
        }
    }
}
