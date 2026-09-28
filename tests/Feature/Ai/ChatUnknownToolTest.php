<?php

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function unknownToolSse(string $tool, string $id = 'call_1'): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0, 'id' => $id, 'type' => 'function',
            'function' => ['name' => $tool, 'arguments' => '{"action":"update_task","task_id":1}'],
        ]]], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]),
        'data: [DONE]',
    ])."\n\n";
}

function unknownToolFinalSse(string $text = 'Listo'): string
{
    return 'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => $text], 'finish_reason' => 'stop']]])."\n\ndata: [DONE]\n\n";
}

it('recovers when the model calls an unavailable tool', function () {
    Http::preventStrayRequests();
    Http::fakeSequence()
        ->push(unknownToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push(unknownToolFinalSse('No pude modificarla, pero seguimos'), 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), [
            'message' => 'Hola, contame algo interesante',
            'thread_id' => $thread->id,
        ])
        ->streamedContent();

    expect($content)->toContain('No pude modificarla, pero seguimos')
        ->toContain('[DONE]')
        ->not->toContain('"type":"error"');
});
