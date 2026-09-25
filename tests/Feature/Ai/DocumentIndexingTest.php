<?php

use App\Ai\Documents\DocumentIndexer;
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
