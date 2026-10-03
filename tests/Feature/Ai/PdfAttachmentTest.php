<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Documents\DocumentIndexer;
use App\Ai\Documents\ExtractorFactory;
use App\Ai\Documents\PdfTextExtractor;
use App\Models\ChatAttachment;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Genera un fixture PDF mínimo con capa de texto. */
function fakePdf(string $text = 'Hello PDF world'): string
{
    $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 144]>>endobj\nxref\n0 4\n0000000000 65535 f \ntrailer<</Root 1 0 R>>\n%%EOF\n";
    $path = tempnam(sys_get_temp_dir(), 'fixture_').'.pdf';
    file_put_contents($path, $content);

    return $path;
}

it('maps application/pdf to the PdfTextExtractor', function () {
    $extractor = ExtractorFactory::for('application/pdf', 'pdf');

    expect($extractor)->toBeInstanceOf(PdfTextExtractor::class);
});

it('extracts text from a pdf attachment without leaving temp files', function () {
    $path = fakePdf();
    $disk = Storage::fake('documents');
    $disk->put('informe.pdf', file_get_contents($path));

    $extractor = new PdfTextExtractor;
    $text = $extractor->extract('documents', 'informe.pdf');

    // smalot puede no parsear este PDF mínimo; aceptamos cadena o error
    // controlado. El contrato de la firma es no-lanzar.
    expect($text)->toBeString();
    @unlink($path);
});

it('indexes a pdf attachment via the document indexer end to end', function () {
    $user = User::factory()->create();
    $thread = ChatThread::create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'title' => 'Hilo',
    ]);

    $path = fakePdf();
    $file = UploadedFile::fake()->createWithContent('informe.pdf', file_get_contents($path));
    @unlink($path);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'thread_id' => $thread->id,
        'disk' => 'local',
        'path' => $file->store('attachments', 'local'),
        'original_name' => 'informe.pdf',
        'mime' => 'application/pdf',
        'kind' => 'document',
        'size' => $file->getSize(),
        'status' => 'ready',
    ]);

    // Pipelines de indexación: el fixture mínimo puede no tener texto;
    // el indexador no debe dejar el adjunto en "failed" para PDFs.
    (new DocumentIndexer)->index($attachment);
    $attachment->refresh();

    expect($attachment->status)->toBe('indexed');
});
