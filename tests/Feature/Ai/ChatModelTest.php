<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('thread belongs to user through participant morph', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    expect($thread->belongsToUser($user))->toBeTrue();

    $other = User::factory()->create();

    expect($thread->belongsToUser($other))->toBeFalse();
});

test('for user scope returns only own threads', function () {
    $user = User::factory()->create();
    $own = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatThread::factory()->create();

    expect(ChatThread::query()->forUser($user)->pluck('id')->all())->toBe([$own->id]);
});

test('active scope excludes archived threads', function () {
    $active = ChatThread::factory()->create();
    ChatThread::factory()->archived()->create();

    expect(ChatThread::query()->active()->pluck('id')->all())->toBe([$active->id]);
});

test('with messages scope excludes empty threads', function () {
    $withMessage = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $withMessage->id]);
    ChatThread::factory()->create();

    expect(ChatThread::query()->withMessages()->pluck('id')->all())->toBe([$withMessage->id]);
});

test('ordered scope puts pinned first then most recently updated', function () {
    $old = ChatThread::factory()->create(['updated_at' => now()->subDays(2)]);
    $recent = ChatThread::factory()->create(['updated_at' => now()->subDay()]);
    $pinned = ChatThread::factory()->pinned()->create(['updated_at' => now()->subWeek()]);

    expect(ChatThread::query()->ordered()->pluck('id')->all())
        ->toBe([$pinned->id, $recent->id, $old->id]);
});

test('search scope matches title substring', function () {
    $match = ChatThread::factory()->create(['title' => 'Rutina de hipertrofia']);
    ChatThread::factory()->create(['title' => 'Presupuesto mensual']);

    expect(ChatThread::query()->search('hiper')->pluck('id')->all())->toBe([$match->id]);
});

test('message citations are read from meta', function () {
    $message = ChatMessage::factory()
        ->assistant()
        ->withCitations([['url' => 'https://example.com', 'title' => 'Example', 'start_index' => 0, 'end_index' => 3]])
        ->create();

    expect($message->citations())->toHaveCount(1);
    expect($message->citations()[0]['url'])->toBe('https://example.com');
    expect($message->isUser())->toBeFalse();
});

test('message without citations returns empty array', function () {
    $message = ChatMessage::factory()->create();

    expect($message->citations())->toBe([]);
});
