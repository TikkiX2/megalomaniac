<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\People\Enums\Closeness;
use App\People\Enums\KeyDateType;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use App\Services\People\PeopleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PeopleWriteTool extends Tool
{
    protected string $name = 'people-write';

    protected string $description = 'Create, update or delete people, and manage their key dates and social networks. Use people-log for interactions.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform')->enum([
                'create_person', 'update_person', 'delete_person',
                'add_key_date', 'update_key_date', 'delete_key_date',
                'add_social', 'update_social', 'delete_social',
            ])->required(),
            'person_id' => $schema->integer()->description('Person ID (all actions except create_person)'),
            'first_name' => $schema->string()->description('First name (create_person)')->max(255),
            'last_name' => $schema->string()->description('Last name'),
            'nickname' => $schema->string()->description('Nickname'),
            'birthday' => $schema->string()->description('Birthday in YYYY-MM-DD format'),
            'email' => $schema->string()->description('Email'),
            'phone' => $schema->string()->description('Phone'),
            'whatsapp' => $schema->string()->description('WhatsApp'),
            'city' => $schema->string()->description('City'),
            'country' => $schema->string()->description('Country'),
            'company' => $schema->string()->description('Company'),
            'job_title' => $schema->string()->description('Job title'),
            'website' => $schema->string()->description('Website URL'),
            'how_we_met' => $schema->string()->description('How you met'),
            'closeness' => $schema->string()->description('Closeness')->enum(Closeness::values()),
            'relationship_status' => $schema->string()->description('Relationship status'),
            'preferred_contact_channel' => $schema->string()->description('Preferred contact channel'),
            'is_favorite' => $schema->boolean()->description('Favorite flag'),
            'is_archived' => $schema->boolean()->description('Archived flag'),
            'notes' => $schema->string()->description('Notes'),
            'key_date_id' => $schema->integer()->description('Key date ID (update_key_date, delete_key_date)'),
            'key_date_type' => $schema->string()->description('Key date type')->enum(KeyDateType::values()),
            'label' => $schema->string()->description('Key date label'),
            'date' => $schema->string()->description('Key date in YYYY-MM-DD format'),
            'remind_days_before' => $schema->integer()->description('Reminder days before the date')->min(0)->max(90),
            'is_recurring_annually' => $schema->boolean()->description('Repeats every year'),
            'social_id' => $schema->integer()->description('Social ID (update_social, delete_social)'),
            'network' => $schema->string()->description('Social network (e.g. instagram, x, linkedin)'),
            'handle' => $schema->string()->description('Social handle'),
            'url' => $schema->string()->description('Social URL'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action', '');
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_person' => $this->createPerson($request, $user),
                'update_person' => $this->updatePerson($request, $user),
                'delete_person' => $this->deletePerson($request, $user),
                'add_key_date' => $this->addKeyDate($request, $user),
                'update_key_date' => $this->updateKeyDate($request, $user),
                'delete_key_date' => $this->deleteKeyDate($request, $user),
                'add_social' => $this->addSocial($request, $user),
                'update_social' => $this->updateSocial($request, $user),
                'delete_social' => $this->deleteSocial($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createPerson(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['nullable', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $person = $this->people->createPerson($user, array_filter($request->all([
            'first_name', 'last_name', 'nickname', 'birthday', 'email', 'phone',
            'whatsapp', 'city', 'country', 'company', 'job_title', 'website',
            'how_we_met', 'closeness', 'relationship_status',
            'preferred_contact_channel', 'is_favorite', 'notes',
        ]), fn ($value) => $value !== null));

        return Response::structured([
            'person' => $person->toArray(),
            'message' => 'Person created successfully.',
        ]);
    }

    private function updatePerson(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
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
            'closeness' => ['sometimes', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data = array_filter($request->all([
            'first_name', 'last_name', 'nickname', 'birthday', 'email', 'phone',
            'whatsapp', 'address', 'city', 'country', 'company', 'job_title',
            'website', 'how_we_met', 'closeness', 'relationship_status',
            'preferred_contact_channel', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_favorite') !== null) {
            $data['is_favorite'] = (bool) $request->get('is_favorite');
        }

        if ($request->get('is_archived') !== null) {
            $data['is_archived'] = (bool) $request->get('is_archived');
        }

        $person = $this->people->updatePerson($user, $person, $data);

        return Response::structured([
            'person' => $person->toArray(),
            'message' => 'Person updated successfully.',
        ]);
    }

    private function deletePerson(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));
        $this->people->deletePerson($user, $person);

        return Response::structured(['message' => 'Person deleted successfully.']);
    }

    private function addKeyDate(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $request->validate([
            'key_date_type' => ['required', Rule::enum(KeyDateType::class)],
            'date' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ]);

        $keyDate = $this->people->addKeyDate($user, $person, [
            'type' => $request->get('key_date_type'),
            'label' => $request->get('label'),
            'date' => $request->get('date'),
            'remind_days_before' => $request->get('remind_days_before', 7),
            'is_recurring_annually' => (bool) $request->get('is_recurring_annually', true),
        ]);

        return Response::structured([
            'key_date' => $keyDate->toArray(),
            'message' => 'Key date added successfully.',
        ]);
    }

    private function updateKeyDate(Request $request, $user): Response|ResponseFactory
    {
        try {
            $keyDate = PersonKeyDate::where('user_id', $user->id)
                ->findOrFail((int) $request->get('key_date_id', 0));
        } catch (ModelNotFoundException) {
            return Response::error('PersonKeyDate not found.');
        }

        $request->validate([
            'key_date_type' => ['sometimes', Rule::enum(KeyDateType::class)],
            'date' => ['sometimes', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['sometimes', 'boolean'],
        ]);

        $data = array_filter($request->all([
            'label', 'date', 'remind_days_before',
        ]), fn ($value) => $value !== null);

        if ($request->get('key_date_type') !== null) {
            $data['type'] = $request->get('key_date_type');
        }

        if ($request->get('is_recurring_annually') !== null) {
            $data['is_recurring_annually'] = (bool) $request->get('is_recurring_annually');
        }

        $keyDate = $this->people->updateKeyDate($user, $keyDate, $data);

        return Response::structured(['key_date' => $keyDate->toArray(), 'message' => 'Key date updated.']);
    }

    private function deleteKeyDate(Request $request, $user): Response|ResponseFactory
    {
        $keyDate = PersonKeyDate::where('user_id', $user->id)
            ->findOrFail((int) $request->get('key_date_id', 0));

        $this->people->deleteKeyDate($user, $keyDate);

        return Response::structured(['message' => 'Key date deleted.']);
    }

    private function addSocial(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $request->validate([
            'network' => ['required', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        $social = $this->people->addSocial($user, $person, [
            'network' => $request->get('network'),
            'handle' => $request->get('handle'),
            'url' => $request->get('url'),
        ]);

        return Response::structured(['social' => $social->toArray(), 'message' => 'Social added.']);
    }

    private function updateSocial(Request $request, $user): Response|ResponseFactory
    {
        try {
            $social = PersonSocial::whereHas('person', fn ($q) => $q->where('user_id', $user->id))
                ->findOrFail((int) $request->get('social_id', 0));
        } catch (ModelNotFoundException) {
            return Response::error('PersonSocial not found.');
        }

        $request->validate([
            'network' => ['sometimes', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        $data = array_filter($request->all(['network', 'handle', 'url']), fn ($value) => $value !== null);

        $social = $this->people->updateSocial($user, $social, $data);

        return Response::structured(['social' => $social->toArray(), 'message' => 'Social updated.']);
    }

    private function deleteSocial(Request $request, $user): Response|ResponseFactory
    {
        $social = PersonSocial::whereHas('person', fn ($q) => $q->where('user_id', $user->id))
            ->findOrFail((int) $request->get('social_id', 0));

        $this->people->deleteSocial($user, $social);

        return Response::structured(['message' => 'Social deleted.']);
    }
}
