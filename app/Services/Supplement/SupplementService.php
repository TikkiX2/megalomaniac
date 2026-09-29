<?php

namespace App\Services\Supplement;

use App\Models\Supplement;
use App\Models\SupplementLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SupplementService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Supplement
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('The name field is required.');
        }

        if (! isset($data['stock_quantity']) || ! isset($data['low_stock_threshold'])) {
            throw new InvalidArgumentException('Stock quantity and low stock threshold are required.');
        }

        return $user->supplements()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Supplement $supplement, array $data): Supplement
    {
        $this->assertOwner($user, $supplement);

        $supplement->update($data);

        return $supplement->fresh();
    }

    public function delete(User $user, Supplement $supplement): void
    {
        $this->assertOwner($user, $supplement);

        $supplement->delete();
    }

    public function logIntake(User $user, Supplement $supplement): SupplementLog
    {
        $this->assertOwner($user, $supplement);

        if ($supplement->stock_quantity > 0) {
            $supplement->decrement('stock_quantity');
        }

        return SupplementLog::create([
            'user_id' => $user->id,
            'supplement_id' => $supplement->id,
            'taken_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, SupplementLog>
     */
    public function recentLogs(User $user, int $limit = 20): Collection
    {
        return SupplementLog::with('supplement')
            ->where('user_id', $user->id)
            ->orderByDesc('taken_at')
            ->limit($limit)
            ->get();
    }

    private function assertOwner(User $user, Supplement $supplement): void
    {
        if ($supplement->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this supplement.');
        }
    }
}
