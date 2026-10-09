<?php

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('document context returns up to ten chunks by default', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document',
        'status' => 'indexed',
        'original_name' => 'manual.txt',
    ]);

    foreach (range(0, 11) as $position) {
        ChatDocumentChunk::create([
            'attachment_id' => $attachment->id,
            'position' => $position,
            'content' => "Contenido palabraclave{$position} sobre nutrición y entrenamiento.",
        ]);
    }

    $thread->sources()->attach($attachment->id);

    $context = $thread->documentContext('palabraclave');

    expect($context)->not->toBeNull()
        ->and(substr_count($context, '###'))->toBe(10);
});
