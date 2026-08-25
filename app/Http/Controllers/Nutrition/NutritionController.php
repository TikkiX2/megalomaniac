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
    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());

        $logs = MealLog::with('items.food')
            ->where('user_id', $request->user()->id)
            ->whereDate('date', $date)
            ->get();

        return Inertia::render('fitness/nutrition', [
            'logs' => $logs,
            'currentDate' => $date,
        ]);
    }

    public function searchFoods(Request $request)
    {
        $query = $request->query('query', '');

        if (blank($query)) {
            return response()->json([]);
        }

        $foods = Food::where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($foods);
    }

    public function storeFood(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'calories' => 'required|integer|min:0',
            'protein' => 'required|numeric|min:0',
            'carbs' => 'required|numeric|min:0',
            'fats' => 'required|numeric|min:0',
            'serving_size' => 'nullable|numeric|min:0',
            'serving_unit' => 'nullable|string|max:50',
        ]);

        $food = Food::create($validated);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json($food, 201);
        }

        return back()->with('success', 'Alimento creado');
    }

    public function getDailyLog(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $logs = MealLog::with('items.food')
            ->where('user_id', $request->user()->id)
            ->whereDate('date', $date)
            ->get();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json($logs);
        }

        return Inertia::render('fitness/nutrition', [
            'logs' => $logs,
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

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json($mealItem->load('food'), 201);
        }

        return back()->with('success', 'Alimento añadido');
    }

    public function deleteMealItem(Request $request, MealItem $mealItem)
    {
        // Ensure belongs to user's log
        if ($mealItem->mealLog && $mealItem->mealLog->user_id !== $request->user()->id) {
            abort(403);
        }

        $mealItem->delete();

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Alimento eliminado');
    }
}
