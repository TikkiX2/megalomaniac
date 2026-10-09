<?php

use App\Ai\Documents\DocumentIndexer;
use App\Ai\Documents\ExtractorFactory;
use App\Ai\Documents\PerplexityJsonExtractor;
use App\Ai\Documents\TextExtractor;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function perplexityExportPayload(): array
{
    $longParagraphs = array_map(
        fn (int $index): string => "Párrafo {$index} sobre fotovoltaica ".str_repeat('contenido largo de prueba ', 30),
        range(1, 8)
    );

    return [
        'conversations' => [
            [
                'id' => 'conv-1',
                'title' => 'Hipertrofia',
                'source' => 'perplexity',
                'mode' => 'ask',
                'collection_uuid' => 'uuid-1',
                'created_at' => '2026-01-01T10:00:00Z',
                'updated_at' => '2026-01-01T10:05:00Z',
                'messages' => [
                    ['id' => 'm1', 'role' => 'user', 'content' => '¿Cómo progresar en press banca?', 'created_at' => '2026-01-01T10:00:00Z'],
                    ['id' => 'm2', 'role' => 'assistant', 'content' => 'Haz press banca 4x8 con sobrecarga progresiva y buena técnica.', 'created_at' => '2026-01-01T10:01:00Z'],
                ],
            ],
            [
                'id' => 'conv-2',
                'title' => 'Energía solar',
                'source' => 'perplexity',
                'mode' => 'research',
                'collection_uuid' => 'uuid-2',
                'created_at' => '2026-02-01T10:00:00Z',
                'updated_at' => '2026-02-01T10:05:00Z',
                'messages' => [
                    ['id' => 'm3', 'role' => 'user', 'content' => 'Dime ventajas de la energía fotovoltaica doméstica', 'created_at' => '2026-02-01T10:00:00Z'],
                    ['id' => 'm4', 'role' => 'assistant', 'content' => 'La fotovoltaica doméstica reduce la factura eléctrica considerablemente.', 'created_at' => '2026-02-01T10:01:00Z'],
                    ['id' => 'm5', 'role' => 'assistant', 'content' => implode("\n\n", $longParagraphs), 'created_at' => '2026-02-01T10:02:00Z'],
                    ['id' => 'm6', 'role' => 'user', 'content' => '   ', 'created_at' => '2026-02-01T10:03:00Z'],
                ],
            ],
        ],
    ];
}

function perplexityJsonAttachment(User $user, string $name, string $contents, string $mime = 'application/json'): ChatAttachment
{
    $path = 'ai-attachments/qa/'.$name;
    Storage::disk('local')->put($path, $contents);

    return ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'kind' => 'document', 'status' => 'pending', 'disk' => 'local',
        'path' => $path, 'original_name' => $name, 'mime' => $mime, 'size' => strlen($contents),
    ]);
}

test('perplexity exports are detected and indexed one chunk per message', function () {
    $user = User::factory()->create();
    $contents = json_encode(perplexityExportPayload(), JSON_UNESCAPED_UNICODE);
    $attachment = perplexityJsonAttachment($user, 'perplexity.json', $contents);

    expect(ExtractorFactory::for('application/json', 'json', 'local', $attachment->path))
        ->toBeInstanceOf(PerplexityJsonExtractor::class);

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');
    // 2 mensajes de la 1ª conversación + 2 cortos de la 2ª + mensaje largo
    // de 8 párrafos (~840 caracteres cada uno) partido en 4 partes.
    expect($attachment->chunks()->count())->toBe(8);
});

test('perplexity chunks carry the exact header format and skip empty messages', function () {
    $user = User::factory()->create();
    $contents = json_encode(perplexityExportPayload(), JSON_UNESCAPED_UNICODE);
    $attachment = perplexityJsonAttachment($user, 'perplexity.json', $contents);

    (new DocumentIndexer)->index($attachment);

    $chunks = $attachment->chunks()->orderBy('position')->pluck('content')->all();

    expect($chunks[0])->toStartWith('### Hipertrofia [2026-01-01T10:00:00Z] (user)')
        ->and($chunks[0])->toContain('press banca')
        ->and($chunks[1])->toStartWith('### Hipertrofia [2026-01-01T10:01:00Z] (assistant)')
        ->and($chunks[2])->toStartWith('### Energía solar [2026-02-01T10:00:00Z] (user)')
        ->and($chunks[4])->toStartWith('### Energía solar [2026-02-01T10:02:00Z] (assistant) (parte 1/4)');

    // El mensaje largo se parte por párrafos repitiendo la cabecera.
    expect($chunks[7])->toStartWith('### Energía solar [2026-02-01T10:02:00Z] (assistant) (parte 4/4)');

    // Sin metadatos internos ni mensajes vacíos.
    foreach ($chunks as $chunk) {
        expect($chunk)->not->toContain('uuid-1', 'uuid-2', 'collection_uuid');
    }
    expect(implode("\n", $chunks))->not->toContain('2026-02-01T10:03:00Z');
});

test('perplexity chunks are stored in order by position', function () {
    $user = User::factory()->create();
    $contents = json_encode(perplexityExportPayload(), JSON_UNESCAPED_UNICODE);
    $attachment = perplexityJsonAttachment($user, 'perplexity.json', $contents);

    (new DocumentIndexer)->index($attachment);

    expect($attachment->chunks()->orderBy('position')->pluck('position')->all())
        ->toBe([0, 1, 2, 3, 4, 5, 6, 7]);
});

test('document context retrieves the chunk from the second conversation', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
    $contents = json_encode(perplexityExportPayload(), JSON_UNESCAPED_UNICODE);
    $attachment = perplexityJsonAttachment($user, 'perplexity.json', $contents);

    (new DocumentIndexer)->index($attachment->refresh());
    $thread->sources()->attach($attachment->id);

    $context = $thread->documentContext('fotovoltaica');

    expect($context)->not->toBeNull()
        ->and($context)->toContain('fotovoltaica')
        ->and($context)->toContain('Energía solar');
});

test('invalid json attachments fail visibly', function () {
    $user = User::factory()->create();
    $attachment = perplexityJsonAttachment($user, 'roto.json', '{"conversations": ');

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('failed');
    expect($attachment->error)->not->toBeNull();
});

test('generic json without conversations is indexed as plain text', function () {
    $user = User::factory()->create();
    $contents = '{"paciente":"Juan","diagnostico":"hipotiroidismo"}';
    $attachment = perplexityJsonAttachment($user, 'datos.json', $contents);

    expect(ExtractorFactory::for('application/json', 'json', 'local', $attachment->path))
        ->toBeInstanceOf(TextExtractor::class);

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed')
        ->and($attachment->chunks()->count())->toBeGreaterThan(0)
        ->and($attachment->chunks()->orderBy('position')->first()->content)->toContain('hipotiroidismo');
});

test('perplexity chunks never split multibyte characters', function () {
    $user = User::factory()->create();
    $filler = str_repeat('contenido de relleno para forzar el particionado ', 30);
    $content = $filler."\n\nRespuesta con emoji ✅\n\n".$filler;
    $payload = ['conversations' => [[
        'id' => 'c-emoji', 'title' => 'Emojis', 'created_at' => '2026-03-01T10:00:00Z',
        'messages' => [
            ['id' => 'm1', 'role' => 'assistant', 'content' => $content, 'created_at' => '2026-03-01T10:00:00Z'],
        ],
    ]]];
    $attachment = perplexityJsonAttachment($user, 'emoji.json', json_encode($payload, JSON_UNESCAPED_UNICODE));

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');

    $chunks = $attachment->chunks()->orderBy('position')->pluck('content')->all();

    expect($chunks)->not->toBeEmpty()
        ->and(implode("\n", $chunks))->toContain('✅');

    foreach ($chunks as $chunk) {
        expect(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
    }
});
