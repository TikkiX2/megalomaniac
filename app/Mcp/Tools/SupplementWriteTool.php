<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Supplement;
use App\Services\Supplement\SupplementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class SupplementWriteTool extends Tool
{
    protected string $name = 'supplement-write';

    protected string $description = 'Create, update or delete supplements and log intakes (stock is decremented automatically) for the authenticated user.';

    public function __construct(protected SupplementService $supplements) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create, update, delete, log')->enum(['create', 'update', 'delete', 'log'])->required(),
            'supplement_id' => $schema->integer()->description('Supplement ID (required for update, delete and log)'),
            'name' => $schema->string()->description('Supplement name (required for create)')->max(255),
            'brand' => $schema->string()->description('Brand')->max(255),
            'dosage_amount' => $schema->string()->description('Dosage (e.g. "5 g")')->max(255),
            'frequency' => $schema->string()->description('Frequency (e.g. "daily")')->max(255),
            'stock_quantity' => $schema->integer()->description('Current stock units (required for create)')->min(0),
            'low_stock_threshold' => $schema->integer()->description('Low stock threshold (required for create)')->min(0),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create' => $this->create($request, $user),
                'update' => $this->update($request, $user),
                'delete' => $this->delete($request, $user),
                'log' => $this->logIntake($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function create(Request $request, $user): Response|ResponseFactory
    {
        $supplement = $this->supplements->create($user, [
            'name' => $request->get('name'),
            'brand' => $request->get('brand'),
            'dosage_amount' => $request->get('dosage_amount'),
            'frequency' => $request->get('frequency'),
            'stock_quantity' => $request->get('stock_quantity', 0),
            'low_stock_threshold' => $request->get('low_stock_threshold', 5),
        ]);

        return Response::structured([
            'supplement' => $supplement->fresh(),
            'message' => 'Supplement created successfully.',
        ]);
    }

    private function update(Request $request, $user): Response|ResponseFactory
    {
        $supplement = $this->find($request, $user);

        if (! $supplement instanceof Supplement) {
            return $supplement;
        }

        $data = array_filter([
            'name' => $request->get('name'),
            'brand' => $request->get('brand'),
            'dosage_amount' => $request->get('dosage_amount'),
            'frequency' => $request->get('frequency'),
            'stock_quantity' => $request->get('stock_quantity'),
            'low_stock_threshold' => $request->get('low_stock_threshold'),
        ], fn ($value) => $value !== null);

        $supplement = $this->supplements->update($user, $supplement, $data);

        return Response::structured([
            'supplement' => $supplement,
            'message' => 'Supplement updated successfully.',
        ]);
    }

    private function delete(Request $request, $user): Response|ResponseFactory
    {
        $supplement = $this->find($request, $user);

        if (! $supplement instanceof Supplement) {
            return $supplement;
        }

        $this->supplements->delete($user, $supplement);

        return Response::structured([
            'message' => 'Supplement deleted successfully.',
        ]);
    }

    private function logIntake(Request $request, $user): Response|ResponseFactory
    {
        $supplement = $this->find($request, $user);

        if (! $supplement instanceof Supplement) {
            return $supplement;
        }

        $log = $this->supplements->logIntake($user, $supplement);

        return Response::structured([
            'supplement_log' => $log,
            'supplement' => $supplement->fresh()->only(['id', 'name', 'stock_quantity']),
            'message' => 'Supplement intake logged successfully.',
        ]);
    }

    private function find(Request $request, $user): Supplement|Response|ResponseFactory
    {
        $supplement = Supplement::where('user_id', $user->id)->find($request->get('supplement_id', 0));

        if (! $supplement) {
            return Response::error('Supplement not found or unauthorized.');
        }

        return $supplement;
    }
}
