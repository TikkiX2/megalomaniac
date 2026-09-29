<?php

namespace App\Ai\Tools;

use App\Models\Supplement;
use App\Models\User;
use App\Services\Supplement\SupplementService;
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

class SupplementActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected SupplementService $supplements,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create, update or delete the user\'s supplements and log intakes (stock is decremented automatically). Use this for supplement inventory and adherence.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus suplementos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_supplement' => 'crear un suplemento',
            'update_supplement' => 'actualizar un suplemento',
            'delete_supplement' => 'eliminar un suplemento',
            'log_supplement' => 'registrar la toma de un suplemento',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_supplement' => $this->create($request),
                'update_supplement' => $this->update($request),
                'delete_supplement' => $this->delete($request),
                'log_supplement' => $this->logIntake($request),
                default => $this->error('Invalid action. Use: create_supplement, update_supplement, delete_supplement, log_supplement'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function create(Request $request): string
    {
        $supplement = $this->supplements->create($this->user, [
            'name' => $request['name'] ?? $request['supplement_name'] ?? null,
            'brand' => $request['brand'] ?? null,
            'dosage_amount' => $request['dosage_amount'] ?? null,
            'frequency' => $request['frequency'] ?? null,
            'stock_quantity' => $request['stock_quantity'] ?? 0,
            'low_stock_threshold' => $request['low_stock_threshold'] ?? 5,
        ]);

        return $this->success('Supplement created', ['supplement' => $supplement->toArray()]);
    }

    private function update(Request $request): string
    {
        $supplement = $this->findSupplement($request['supplement_id'] ?? null);

        if (! $supplement) {
            return $this->error('Supplement not found');
        }

        $data = array_filter([
            'name' => $request['name'] ?? null,
            'brand' => $request['brand'] ?? null,
            'dosage_amount' => $request['dosage_amount'] ?? null,
            'frequency' => $request['frequency'] ?? null,
            'stock_quantity' => $request['stock_quantity'] ?? null,
            'low_stock_threshold' => $request['low_stock_threshold'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $supplement = $this->supplements->update($this->user, $supplement, $data);

        return $this->success('Supplement updated', ['supplement' => $supplement->toArray()]);
    }

    private function delete(Request $request): string
    {
        $supplement = $this->findSupplement($request['supplement_id'] ?? null);

        if (! $supplement) {
            return $this->error('Supplement not found');
        }

        $this->supplements->delete($this->user, $supplement);

        return $this->success('Supplement deleted', ['supplement' => ['id' => $supplement->id]]);
    }

    private function logIntake(Request $request): string
    {
        $supplement = $this->findSupplement($request['supplement_id'] ?? null);

        if (! $supplement) {
            return $this->error('Supplement not found. Use the supplement query tool to discover the ID.');
        }

        $log = $this->supplements->logIntake($this->user, $supplement);

        return $this->success('Supplement intake logged', [
            'supplement_log' => $log->toArray(),
            'supplement' => $supplement->fresh()->only(['id', 'name', 'stock_quantity']),
        ]);
    }

    private function findSupplement(mixed $id): ?Supplement
    {
        if (! $id) {
            return null;
        }

        return $this->user->supplements()->find((int) $id);
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
                ->enum(['create_supplement', 'update_supplement', 'delete_supplement', 'log_supplement'])
                ->description('Action to perform')
                ->required(),
            'supplement_id' => $schema->integer()->description('Supplement ID (update, delete, log_supplement)'),
            'name' => $schema->string()->description('Supplement name (create_supplement)'),
            'brand' => $schema->string()->description('Brand'),
            'dosage_amount' => $schema->string()->description('Dosage (e.g. "5 g")'),
            'frequency' => $schema->string()->description('Frequency (e.g. "daily")'),
            'stock_quantity' => $schema->integer()->description('Current stock units'),
            'low_stock_threshold' => $schema->integer()->description('Low stock threshold'),
        ];
    }
}
