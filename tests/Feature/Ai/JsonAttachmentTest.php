<?php

declare(strict_types=1);

use App\Ai\Documents\DocumentIndexer;
use App\Ai\Documents\ExtractorFactory;
use App\Ai\Documents\TextExtractor;
use App\Jobs\IndexChatDocument;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('maps application/json to the TextExtractor', function () {
    expect(ExtractorFactory::for('application/json', 'json'))->toBeInstanceOf(TextExtractor::class);
    expect(ExtractorFactory::for('text/plain', 'txt'))->toBeInstanceOf(TextExtractor::class);
});

it('uploads a json attachment and queues indexing', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('datos.json', '{"paciente":"Juan","tsh":2.5}'),
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'document')->assertJsonPath('status', 'pending');
    Queue::assertPushed(IndexChatDocument::class);

    $attachment = ChatAttachment::query()->forUser($user)->documents()->sole();
    expect($attachment->original_name)->toEndWith('.json');
});

it('indexes a json attachment end to end', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('datos.json', '{"paciente":"Juan","diagnostico":"hipotiroidismo"}'),
    ]);
    $response->assertCreated();

    $attachment = ChatAttachment::query()->forUser($user)->documents()->sole();

    (new DocumentIndexer)->index($attachment->refresh());

    expect($attachment->refresh()->status)->toBe('indexed')
        ->and($attachment->chunks()->count())->toBeGreaterThan(0)
        ->and($attachment->chunks()->first()->content)->toContain('hipotiroidismo');
});

it('still uploads a txt file containing json', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('datos.txt', '{"clave":"valor"}'),
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'document');
    Queue::assertPushed(IndexChatDocument::class);
});
