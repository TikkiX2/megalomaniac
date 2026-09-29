<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PeopleActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected PeopleService $people,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create and update personal contacts: create or edit a person, log an interaction, add a key date. Use this when the user asks to record something about a person.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus contactos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_person' => 'crear una persona',
            'update_person' => 'actualizar una persona',
            'log_interaction' => 'registrar una interacción',
            'add_key_date' => 'agregar una fecha clave',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_person' => $this->createPerson($request),
                'update_person' => $this->updatePerson($request),
                'log_interaction' => $this->logInteraction($request),
                'add_key_date' => $this->addKeyDate($request),
                default => $this->error('Invalid action. Use: create_person, update_person, log_interaction, add_key_date'),
            };
        } catch (ModelNotFoundException|AuthorizationException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createPerson(Request $request): string
    {
        $name = trim((string) ($request['first_name'] ?? ''));

        if ($name === '') {
            return $this->error('First name is required.');
        }

        $person = $this->people->createPerson($this->user, array_filter([
            'first_name' => $name,
            'last_name' => $request['last_name'] ?? null,
            'nickname' => $request['nickname'] ?? null,
            'birthday' => $request['birthday'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'whatsapp' => $request['whatsapp'] ?? null,
            'city' => $request['city'] ?? null,
            'company' => $request['company'] ?? null,
            'closeness' => $request['closeness'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn ($value) => $value !== null));

        return $this->success('Person created.', ['person' => $person->toArray()]);
    }

    private function updatePerson(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $data = array_filter([
            'first_name' => $request['first_name'] ?? null,
            'last_name' => $request['last_name'] ?? null,
            'nickname' => $request['nickname'] ?? null,
            'birthday' => $request['birthday'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'whatsapp' => $request['whatsapp'] ?? null,
            'city' => $request['city'] ?? null,
            'company' => $request['company'] ?? null,
            'closeness' => $request['closeness'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn ($value) => $value !== null);

        if (($request['is_favorite'] ?? null) !== null) {
            $data['is_favorite'] = (bool) $request['is_favorite'];
        }

        if (($request['is_archived'] ?? null) !== null) {
            $data['is_archived'] = (bool) $request['is_archived'];
        }

        $person = $this->people->updatePerson($this->user, $person, $data);

        return $this->success('Person updated.', ['person' => $person->toArray()]);
    }

    private function logInteraction(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $interaction = $this->people->logInteraction($this->user, $person, [
            'channel' => $request['channel'] ?? 'message',
            'occurred_at' => $request['occurred_at'] ?? now(),
            'title' => $request['title'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]);

        return $this->success('Interaction logged.', [
            'interaction' => $interaction->toArray(),
            'last_contacted_at' => $person->fresh()->last_contacted_at?->toIso8601String(),
        ]);
    }

    private function addKeyDate(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $date = (string) ($request['date'] ?? '');

        if ($date === '') {
            return $this->error('Date is required.');
        }

        $keyDate = $this->people->addKeyDate($this->user, $person, [
            'type' => $request['key_date_type'] ?? 'custom',
            'label' => $request['label'] ?? null,
            'date' => $date,
            'remind_days_before' => (int) ($request['remind_days_before'] ?? 7),
            'is_recurring_annually' => (bool) ($request['is_recurring_annually'] ?? true),
        ]);

        return $this->success('Key date added.', ['key_date' => $keyDate->toArray()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_person', 'update_person', 'log_interaction', 'add_key_date'])
                ->description('Action to perform')
                ->required(),
            'person_id' => $schema->integer()->description('Person ID (update_person, log_interaction, add_key_date)'),
            'first_name' => $schema->string()->description('First name (create_person)'),
            'last_name' => $schema->string()->description('Last name'),
            'nickname' => $schema->string()->description('Nickname'),
            'birthday' => $schema->string()->description('Birthday YYYY-MM-DD'),
            'email' => $schema->string()->description('Email'),
            'phone' => $schema->string()->description('Phone'),
            'whatsapp' => $schema->string()->description('WhatsApp'),
            'city' => $schema->string()->description('City'),
            'company' => $schema->string()->description('Company'),
            'closeness' => $schema->string()->description('Closeness: inner_circle, close, friend, acquaintance'),
            'is_favorite' => $schema->boolean()->description('Favorite flag'),
            'is_archived' => $schema->boolean()->description('Archived flag'),
            'notes' => $schema->string()->description('Notes'),
            'channel' => $schema->string()->description('Interaction channel for log_interaction'),
            'occurred_at' => $schema->string()->description('ISO 8601 datetime for log_interaction'),
            'title' => $schema->string()->description('Title for log_interaction or key date label context'),
            'key_date_type' => $schema->string()->description('Key date type for add_key_date'),
            'label' => $schema->string()->description('Key date label'),
            'date' => $schema->string()->description('Key date YYYY-MM-DD (add_key_date)'),
            'remind_days_before' => $schema->integer()->description('Key date reminder days'),
            'is_recurring_annually' => $schema->boolean()->description('Key date repeats annually'),
        ];
    }
}
