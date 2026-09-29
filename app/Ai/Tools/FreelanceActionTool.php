<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Freelance\FreelanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FreelanceActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected FreelanceService $freelance,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update or delete the user\'s freelance clients and quotes, and convert a quote into a project. Use this when the user asks to record client work.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tu freelance').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_client' => 'crear un cliente',
            'update_client' => 'actualizar un cliente',
            'delete_client' => 'eliminar un cliente',
            'create_quote' => 'crear una cotización',
            'update_quote' => 'actualizar una cotización',
            'delete_quote' => 'eliminar una cotización',
            'convert_quote' => 'convertir una cotización en proyecto',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_client' => $this->createClient($request),
                'update_client' => $this->updateClient($request),
                'delete_client' => $this->deleteClient($request),
                'create_quote' => $this->createQuote($request),
                'update_quote' => $this->updateQuote($request),
                'delete_quote' => $this->deleteQuote($request),
                'convert_quote' => $this->convertQuote($request),
                default => $this->error('Invalid action. Use: create/update/delete_client, create/update/delete_quote, convert_quote'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createClient(Request $request): string
    {
        $client = $this->freelance->createClient($this->user, [
            'name' => $request['name'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'company' => $request['company'] ?? null,
        ]);

        return $this->success('Client created', ['client' => $client->toArray()]);
    }

    private function updateClient(Request $request): string
    {
        $client = $this->user->clients()->find($request['client_id'] ?? 0);

        if (! $client) {
            return $this->error('Client not found');
        }

        $client = $this->freelance->updateClient($this->user, $client, array_filter([
            'name' => $request['name'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'company' => $request['company'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Client updated', ['client' => $client->toArray()]);
    }

    private function deleteClient(Request $request): string
    {
        $client = $this->user->clients()->find($request['client_id'] ?? 0);

        if (! $client) {
            return $this->error('Client not found');
        }

        $this->freelance->deleteClient($this->user, $client);

        return $this->success('Client deleted', ['client' => ['id' => $client->id]]);
    }

    private function createQuote(Request $request): string
    {
        $quote = $this->freelance->createQuote($this->user, [
            'client_id' => $request['client_id'] ?? null,
            'project_id' => $request['project_id'] ?? null,
            'title' => $request['title'] ?? null,
            'issue_date' => $request['issue_date'] ?? now()->toDateString(),
            'currency_id' => $request['currency_id'] ?? null,
            'hourly_rate' => $request['hourly_rate'] ?? null,
            'notes' => $request['notes'] ?? null,
            'items' => $request['items'] ?? [],
        ]);

        return $this->success('Quote created', ['quote' => $quote->toArray()]);
    }

    private function updateQuote(Request $request): string
    {
        $quote = $this->user->quotes()->find($request['quote_id'] ?? 0);

        if (! $quote) {
            return $this->error('Quote not found');
        }

        $data = array_filter([
            'title' => $request['title'] ?? null,
            'status' => $request['status'] ?? null,
            'issue_date' => $request['issue_date'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        if ($request->offsetExists('items')) {
            $data['items'] = $request['items'];
        }

        $quote = $this->freelance->updateQuote($this->user, $quote, $data);

        return $this->success('Quote updated', ['quote' => $quote->toArray()]);
    }

    private function deleteQuote(Request $request): string
    {
        $quote = $this->user->quotes()->find($request['quote_id'] ?? 0);

        if (! $quote) {
            return $this->error('Quote not found');
        }

        $this->freelance->deleteQuote($this->user, $quote);

        return $this->success('Quote deleted', ['quote' => ['id' => $quote->id]]);
    }

    private function convertQuote(Request $request): string
    {
        $quote = $this->user->quotes()->find($request['quote_id'] ?? 0);

        if (! $quote) {
            return $this->error('Quote not found');
        }

        $project = $this->freelance->convertQuoteToProject($this->user, $quote);

        return $this->success('Quote converted to project', [
            'project' => $project->only(['id', 'name', 'type', 'client_id', 'total_amount']),
            'quote' => $quote->fresh()->only(['id', 'status', 'project_id']),
        ]);
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
                ->enum([
                    'create_client', 'update_client', 'delete_client',
                    'create_quote', 'update_quote', 'delete_quote', 'convert_quote',
                ])
                ->description('Action to perform')
                ->required(),
            'client_id' => $schema->integer()->description('Client ID (update_client, delete_client, create_quote)'),
            'quote_id' => $schema->integer()->description('Quote ID (update_quote, delete_quote, convert_quote)'),
            'project_id' => $schema->integer()->description('Optional project ID to link the quote (create_quote)'),
            'name' => $schema->string()->description('Client name (create_client, update_client)'),
            'email' => $schema->string()->description('Client email'),
            'phone' => $schema->string()->description('Client phone'),
            'company' => $schema->string()->description('Client company'),
            'title' => $schema->string()->description('Quote title (create_quote, update_quote)'),
            'issue_date' => $schema->string()->description('Issue date YYYY-MM-DD (create_quote, update_quote)'),
            'currency_id' => $schema->integer()->description('Currency ID (required for create_quote)'),
            'hourly_rate' => $schema->number()->description('Hourly rate (create_quote)'),
            'status' => $schema->string()->description('Quote status (update_quote): draft, sent, accepted, rejected'),
            'notes' => $schema->string()->description('Notes'),
            'items' => $schema->array()
                ->description('Quote items (create_quote, update_quote replaces them)')
                ->items($schema->object([
                    'description' => $schema->string()->description('Item description'),
                    'hours' => $schema->number(),
                    'hourly_rate' => $schema->number(),
                    'subtotal' => $schema->number(),
                ])),
        ];
    }
}
