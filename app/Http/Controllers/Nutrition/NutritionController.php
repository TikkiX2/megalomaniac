<?php

namespace App\Http\Controllers\Nutrition;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\MealItem;
use App\Models\MealLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionController extends Controller
{
    // Food Methods
    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());

        return Inertia::render('fitness/nutrition', [
            'logs' => MealLog::with('items.food')
                ->where('user_id', $request->user()->id)
                ->whereDate('date', $date)
                ->get(),
            'currentDate' => $date,
        ]);
    }

    public function storeMealItem(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'meal_type' => 'required|string|in:breakfast,lunch,dinner,snack',
            'food_id' => 'required|exists:foods,id',
            'quantity' => 'required|numeric|min:0.1',
        ]);

        $mealLog = MealLog::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
            'meal_type' => $validated['meal_type'],
        ]);

        $food = Food::findOrFail($validated['food_id']);

        $mealItem = $mealLog->items()->create([
            'food_id' => $food->id,
            'quantity' => $validated['quantity'],
            'calories_snapshot' => $food->calories * $validated['quantity'],
            'protein_snapshot' => $food->protein * $validated['quantity'],
            'carbs_snapshot' => $food->carbs * $validated['quantity'],
            'fats_snapshot' => $food->fats * $validated['quantity'],
        ]);

        return $mealItem->load('food');
    }

    public function deleteMealItem(MealItem $mealItem)
    {
        $mealItem->delete();

        return response()->noContent();
    }
}
