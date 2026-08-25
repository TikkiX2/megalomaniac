<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreMealItemRequest;
use App\Http\Resources\FoodResource;
use App\Http\Resources\MealItemResource;
use App\Http\Resources\MealLogResource;
use App\Models\Food;
use App\Models\MealItem;
use App\Models\MealLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NutritionController extends Controller
{
    public function searchFoods(Request $request): AnonymousResourceCollection
    {
        $query = Food::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        if ($request->has('brand')) {
            $query->where('brand', 'like', '%'.$request->get('brand').'%');
        }

        $foods = $query->latest()->paginate(20);

        return FoodResource::collection($foods);
    }

    public function getDailyLog(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $mealLogs = MealLog::with(['items.food'])
            ->where('user_id', $request->user()->id)
            ->whereDate('date', $date)
            ->get();

        $totalCalories = $mealLogs->sum('total_calories');
        $totalMacros = [
            'protein' => $mealLogs->sum('total_macros.protein'),
            'carbs' => $mealLogs->sum('total_macros.carbs'),
            'fats' => $mealLogs->sum('total_macros.fats'),
        ];

        return response()->json([
            'date' => $date,
            'meal_logs' => MealLogResource::collection($mealLogs),
            'total_calories' => $totalCalories,
            'total_macros' => $totalMacros,
        ]);
    }

    public function storeMealItem(StoreMealItemRequest $request, MealLog $mealLog): JsonResponse
    {
        if ($mealLog->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $food = Food::findOrFail($request->validated('food_id'));
        $quantity = $request->validated('quantity');

        $mealItem = MealItem::create([
            'meal_log_id' => $mealLog->id,
            'food_id' => $food->id,
            'quantity' => $quantity,
            'calories_snapshot' => $food->calories * $quantity,
            'protein_snapshot' => $food->protein * $quantity,
            'carbs_snapshot' => $food->carbs * $quantity,
            'fats_snapshot' => $food->fats * $quantity,
        ]);

        return (new MealItemResource($mealItem->load('food')))
            ->response()
            ->setStatusCode(201);
    }
}
