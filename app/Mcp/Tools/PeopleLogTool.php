<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PeopleLogTool extends Tool
{
    protected string $name = 'people-log';

    protected string $description = 'Quick-log a social interaction with a person (call, message, meeting...). Only creates new interaction records; it never edits or deletes existing data.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID')->required(),
            'channel' => $schema->string()->description('Interaction channel')->enum(InteractionChannel::values()),
            'occurred_at' => $schema->string()->description('ISO 8601 datetime (defaults to now)'),
            'title' => $schema->string()->description('Short title'),
            'notes' => $schema->string()->description('Notes'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $request->validate([
            'person_id' => ['required', 'integer'],
            'channel' => ['nullable', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $person = $this->people->findPerson($user, (int) $request->get('person_id'));
        } catch (ModelNotFoundException $exception) {
            return Response::error($exception->getMessage());
        }

        $interaction = $this->people->logInteraction($user, $person, [
            'channel' => $request->get('channel') ?? InteractionChannel::Message->value,
            'occurred_at' => $request->get('occurred_at') ?? now(),
            'title' => $request->get('title'),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'interaction' => $interaction->toArray(),
            'message' => 'Interaction logged successfully.',
        ]);
    }
}
