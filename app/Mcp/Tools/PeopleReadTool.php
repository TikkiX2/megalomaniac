<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Person;
use App\People\Enums\Closeness;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PeopleReadTool extends Tool
{
    protected string $name = 'people-read';

    protected string $description = 'Read the authenticated user\'s personal contacts: search and filter people (closeness, favorites, not contacted in N days), get a full person record with key dates and socials, and list upcoming birthdays and key dates.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID for the full record'),
            'search' => $schema->string()->description('Search by first name, last name, nickname or company'),
            'closeness' => $schema->string()->description('Filter by closeness')->enum(Closeness::values()),
            'favorite' => $schema->boolean()->description('Only favorite people'),
            'archived' => $schema->boolean()->description('Only archived people'),
            'stale_days' => $schema->integer()->description('Only people not contacted in the last N days')->min(1)->max(3650),
            'upcoming_days' => $schema->integer()->description('Include birthdays and key dates within the next N days')->min(1)->max(365),
            'limit' => $schema->integer()->description('Maximum records (default 20)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $personId = $request->get('person_id');

        if ($personId) {
            $person = Person::with(['keyDates', 'socials', 'media'])
                ->where('user_id', $user->id)
                ->find((int) $personId);

            if (! $person) {
                return Response::error('Person not found.');
            }

            $payload = $person->toArray();
            $payload['interactions'] = $person->interactions()
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray();
            $payload['upcoming'] = $this->people->upcoming($user, 60, $person)->all();

            return Response::structured(['person' => $payload]);
        }

        $limit = (int) $request->get('limit', 20);

        $query = Person::query()->where('user_id', $user->id)->with('media');

        $query->when($request->get('search'), fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request->get('closeness'), fn ($q, $closeness) => $q->where('closeness', $closeness));

        filter_var($request->get('archived', false), FILTER_VALIDATE_BOOLEAN)
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when(
            filter_var($request->get('favorite', false), FILTER_VALIDATE_BOOLEAN),
            fn ($q) => $q->where('is_favorite', true),
        );

        $query->when($request->get('stale_days'), fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')->orderBy('first_name')->limit($limit)->get();

        $payload = [
            'records' => $people->toArray(),
            'count' => $people->count(),
            'limit' => $limit,
        ];

        if ($days = (int) $request->get('upcoming_days', 0)) {
            $payload['upcoming'] = $this->people->upcoming($user, $days)->all();
        }

        return Response::structured($payload);
    }
}
