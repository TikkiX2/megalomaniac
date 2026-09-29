<?php

namespace App\Services\Nutrition;

use App\Models\Food;
use App\Models\MealItem;
use App\Models\MealLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class NutritionService
{
    /**
     * Meal logs are unique per user, date and meal type.
     */
    public function createMealLog(User $user, string $date, string $mealType): MealLog
    {
        if (! in_array($mealType, ['breakfast', 'lunch', 'dinner', 'snack'], true)) {
            throw new InvalidArgumentException('Invalid meal type.');
        }

        $existing = MealLog::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->where('meal_type', $mealType)
            ->first();

        return $existing ?? MealLog::create([
            'user_id' => $user->id,
            'date' => $date,
            'meal_type' => $mealType,
        ]);
    }

    /**
     * Snapshot macros use the food's per-serving values multiplied by the
     * quantity, matching the web flow.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMealItem(User $user, MealLog $mealLog, array $data): MealItem
    {
        if ($mealLog->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this meal log.');
        }

        $food = Food::find($data['food_id'] ?? null);

        if (! $food) {
            throw new InvalidArgumentException('Food not found.');
        }

        $quantity = (float) ($data['quantity'] ?? 0);

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $mealLog->items()->create([
            'food_id' => $food->id,
            'quantity' => $quantity,
            'calories_snapshot' => $food->calories * $quantity,
            'protein_snapshot' => $food->protein * $quantity,
            'carbs_snapshot' => $food->carbs * $quantity,
            'fats_snapshot' => $food->fats * $quantity,
        ]);
    }

    public function deleteMealItem(User $user, MealItem $mealItem): void
    {
        if ($mealItem->mealLog && $mealItem->mealLog->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this meal item.');
        }

        $mealItem->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFood(User $user, array $data): Food
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('The name field is required.');
        }

        return Food::create($data);
    }

    /**
     * @return Collection<int, Food>
     */
    public function searchFoods(User $user, ?string $query = null, int $limit = 20): Collection
    {
        return Food::query()
            ->when($query, fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
