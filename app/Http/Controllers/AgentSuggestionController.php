<?php

namespace App\Http\Controllers;

use App\Models\AgentSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suggestions = AgentSuggestion::query()
            ->where('user_id', $request->user()->id)
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($suggestions);
    }

    public function dismiss(AgentSuggestion $suggestion): JsonResponse
    {
        $suggestion->update(['dismissed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
