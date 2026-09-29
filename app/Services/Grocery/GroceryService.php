<?php

namespace App\Services\Grocery;

use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GroceryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): GroceryItem
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('The name field is required.');
        }

        if (! isset($data['current_stock']) || ! isset($data['target_stock'])) {
            throw new InvalidArgumentException('Current stock and target stock are required.');
        }

        return $user->groceryItems()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, GroceryItem $item, array $data): GroceryItem
    {
        $this->assertOwner($user, $item);

        $item->update($data);

        return $item->fresh();
    }

    public function delete(User $user, GroceryItem $item): void
    {
        $this->assertOwner($user, $item);

        $item->delete();
    }

    public function consume(User $user, GroceryItem $item, float $quantity = 1): GroceryItem
    {
        $this->assertOwner($user, $item);

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity): GroceryItem {
            $locked = GroceryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->current_stock = max(0, (float) $locked->current_stock - $quantity);
            $locked->save();

            return $locked;
        });
    }

    public function restock(
        User $user,
        GroceryItem $item,
        float $quantity,
        ?float $price = null,
        ?string $date = null,
    ): GroceryItem {
        $this->assertOwner($user, $item);

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $price, $date): GroceryItem {
            $locked = GroceryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->current_stock = (float) $locked->current_stock + $quantity;
            $locked->purchased_at = $date ?? now();

            if ($price !== null) {
                $locked->price = $price;
            }

            $locked->save();

            $locked->priceHistory()->create([
                'price' => $price ?? $locked->price ?? 0,
                'quantity' => $quantity,
                'purchased_at' => $date ?? now(),
            ]);

            return $locked->fresh();
        });
    }

    private function assertOwner(User $user, GroceryItem $item): void
    {
        $this->assertOwnedBy($user, $item, 'grocery item');
    }

    private function assertOwnedBy(User $user, Model $model, string $label): void
    {
        if ($model->user_id !== $user->id) {
            throw new AuthorizationException("You do not own this {$label}.");
        }
    }
}
