<?php

namespace App\Http\Controllers;

use App\Ai\Agents\MegalomaniacAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AiChatController extends Controller
{
    public function chat(Request $request): StreamableAgentResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'conversation_id' => 'nullable|string|max:36',
        ]);

        $user = $request->user();
        $agent = new MegalomaniacAgent($user);

        if (! empty($validated['conversation_id'])) {
            $response = $agent
                ->continue($validated['conversation_id'], as: $user)
                ->stream($validated['message']);
        } else {
            $response = $agent
                ->forUser($user)
                ->stream($validated['message']);
        }

        return $response;
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        $conversations = DB::table($conversationsTable)
            ->where('participant_type', get_class($user))
            ->where('participant_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return response()->json($conversations);
    }
}
