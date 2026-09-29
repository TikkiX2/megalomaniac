<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PeopleQueryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected PeopleService $people,
    ) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s personal contacts: search people by name or nickname, get a full record with key dates, socials and recent interactions, list people not contacted in N days, and list upcoming birthdays and key dates.';
    }

    public function handle(Request $request): Stringable|string
    {
        $personId = $request['person_id'] ?? null;

        if ($personId) {
            $person = Person::with(['keyDates', 'socials'])
                ->where('user_id', $this->user->id)
                ->find((int) $personId);

            if (! $person) {
                return 'Person not found.';
            }

            $payload = $person->toArray();
            $payload['interactions'] = $person->interactions()
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray();
            $payload['upcoming'] = $this->people->upcoming($this->user, 60, $person)->all();

            return json_encode(['person' => $payload], JSON_PRETTY_PRINT);
        }

        $query = Person::query()->where('user_id', $this->user->id);

        $query->when($request['search'] ?? null, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request['closeness'] ?? null, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $query->when($request['stale_days'] ?? null, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')->orderBy('first_name')->limit(15)->get();

        $payload = [
            'records' => $people->toArray(),
            'count' => $people->count(),
        ];

        if ($days = (int) ($request['upcoming_days'] ?? 0)) {
            $payload['upcoming'] = $this->people->upcoming($this->user, $days)->all();
        }

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID for the full record'),
            'search' => $schema->string()->description('Search by name, nickname or company'),
            'closeness' => $schema->string()->description('Filter by closeness: inner_circle, close, friend, acquaintance'),
            'stale_days' => $schema->integer()->description('People not contacted in the last N days'),
            'upcoming_days' => $schema->integer()->description('Include birthdays and key dates within the next N days'),
        ];
    }
}
