<?php

use App\Ai\Documents\DocumentIndexer;
use App\Ai\Documents\DocxExtractor;
use App\Models\ChatAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function documentAttachment(string $name, string $mime, string $contents): ChatAttachment
{
    $path = 'ai-attachments/qa/'.$name;
    Storage::disk('local')->put($path, $contents);

    return ChatAttachment::factory()->create([
        'kind' => 'document', 'status' => 'pending', 'disk' => 'local',
        'path' => $path, 'original_name' => $name, 'mime' => $mime, 'size' => strlen($contents),
    ]);
}

test('txt documents are indexed into chunks', function () {
    $attachment = documentAttachment('notas.txt', 'text/plain', str_repeat('La rutina de hipertrofia usa press banca. ', 40));

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');
    expect($attachment->chunks()->count())->toBeGreaterThan(1);
    expect($attachment->chunks()->orderBy('position')->first()->content)->toContain('hipertrofia');
});

test('md documents are indexed and unsupported files fail visibly', function () {
    $md = documentAttachment('plan.md', 'text/markdown', "# Plan\n\n- comprar avena\n- pagar deuda");
    (new DocumentIndexer)->index($md);
    expect($md->refresh()->status)->toBe('indexed');

    $bad = documentAttachment('foto.bin', 'application/octet-stream', "\x00\x01binario");
    (new DocumentIndexer)->index($bad);
    expect($bad->refresh()->status)->toBe('failed');
    expect($bad->error)->not->toBeNull();
});

test('docx documents are extracted from the xml body', function () {
    $path = 'ai-attachments/qa/plan.docx';
    Storage::disk('local')->makeDirectory('ai-attachments/qa');
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE);
    $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Entrenamiento de fuerza</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    $attachment = ChatAttachment::factory()->create([
        'kind' => 'document', 'status' => 'pending', 'path' => $path,
        'original_name' => 'plan.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size' => Storage::disk('local')->size($path),
    ]);

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');
    expect($attachment->chunks()->first()->content)->toContain('Entrenamiento de fuerza');
});

test('reindexing is idempotent', function () {
    $attachment = documentAttachment('repetido.txt', 'text/plain', 'contenido repetido');

    (new DocumentIndexer)->index($attachment);
    $first = $attachment->chunks()->count();
    (new DocumentIndexer)->index($attachment->refresh());

    expect($attachment->chunks()->count())->toBe($first);
});

test('very large documents cannot exceed the maximum chunk count', function () {
    $attachment = documentAttachment('enorme.txt', 'text/plain', str_repeat('a', 2_500_000));

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');
    expect($attachment->chunks()->count())->toBe(3125);
});

test('perplexity documents cannot exceed the maximum chunk count', function () {
    $messages = [];

    for ($i = 0; $i < 13_000; $i++) {
        $messages[] = ['id' => null, 'role' => 'user', 'content' => "mensaje {$i}", 'created_at' => '2026-01-01T00:00:00Z'];
    }

    $json = json_encode(['conversations' => [
        ['id' => 'c1', 'title' => 'Tope', 'created_at' => '2026-01-01T00:00:00Z', 'messages' => $messages],
    ]]);

    $attachment = documentAttachment('tope.json', 'application/json', $json);

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');
    expect($attachment->chunks()->count())->toBe(12000);
});

test('txt chunks with nul bytes are sanitized, not fatal on pgsql', function () {
    $attachment = documentAttachment('nul.txt', 'text/plain', "texto con\0nulo en medio");

    (new DocumentIndexer)->index($attachment);

    expect($attachment->refresh()->status)->toBe('indexed');

    $chunks = $attachment->chunks()->orderBy('position')->pluck('content')->all();

    expect($chunks)->not->toBeEmpty()
        ->and(implode('', $chunks))->toContain('texto connulo en medio');

    foreach ($chunks as $chunk) {
        expect(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
        expect(str_contains($chunk, "\0"))->toBeFalse();
    }
});

test('docx xml over the size limit is rejected before extraction', function () {
    $path = 'ai-attachments/qa/gigante.docx';
    Storage::disk('local')->makeDirectory('ai-attachments/qa');
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE);
    $zip->addFromString('word/document.xml', str_repeat('<w:p/>', 3_500_000));
    $zip->close();

    expect(fn () => (new DocxExtractor)->extract('local', $path))
        ->toThrow(RuntimeException::class, '20 MB');
});
