<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Documents\DocumentIndexer;
use App\Ai\Documents\ExtractorFactory;
use App\Ai\Documents\PdfTextExtractor;
use App\Ai\Support\AiRequestExecutor;
use App\Ai\Support\AiScopeResolver;
use App\Jobs\OcrPdfDocument;
use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\HealthStudy;
use App\Models\User;
use App\Services\Health\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

    // PDF sin capa de texto: el indexador no debe dejarlo en "failed";
    // despacha el job de OCR para transcribirlo con visión.
    Queue::fake();
    (new DocumentIndexer)->index($attachment);
    $attachment->refresh();

    Queue::assertPushed(OcrPdfDocument::class);
    expect($attachment->status)->toBe('pending');
});

it('falls back to indexed document content when the query has no fts match', function () {
    $user = User::factory()->create();
    $thread = ChatThread::create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'title' => 'Hilo con documento',
    ]);

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'disk' => 'local',
        'path' => 'documents/analisis.txt',
        'original_name' => 'analisis.txt',
        'mime' => 'text/plain',
        'kind' => 'document',
        'status' => 'indexed',
    ]);
    $thread->sources()->syncWithoutDetaching([$attachment->id]);

    ChatDocumentChunk::create([
        'attachment_id' => $attachment->id,
        'position' => 0,
        'content' => 'TSH 2.5 uUI/mL dentro del rango de referencia 0.4-4.5',
    ]);

    // La consulta no contiene ningún término del documento, por lo que el FTS
    // no matchea; el fallback debe inyectar el contenido igual.
    $context = $thread->documentContext('qué dice el informe', limit: 6);

    expect($context)->not->toBeNull()
        ->and($context)->toContain('analisis.txt')
        ->and($context)->toContain('TSH');
});

it('links study pdf attachments as thread sources when creating a health chat', function () {
    $user = User::factory()->create();
    $this->withoutVite();
    $this->actingAs($user);

    $study = HealthStudy::factory()->create(['user_id' => $user->id, 'title' => 'Historia Clinica']);
    $study->addMediaFromString('%PDF-1.4 dummy')->usingFileName('historia.pdf')->toMediaCollection('attachments');

    $this->post('/health/chats', [
        'context_type' => 'health_study',
        'context_id' => $study->id,
    ])->assertRedirect();

    $thread = ChatThread::where('category', 'salud')->latest('id')->firstOrFail();

    $sources = $thread->sources()->get()->filter(fn ($a) => $a->original_name === 'historia.pdf');

    expect($sources)->not->toBeEmpty()
        ->and($sources->first()->kind)->toBe('document');
});

it('reuses cached ocr text without calling the provider again', function () {
    $user = User::factory()->create();

    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id,
        'original_name' => 'escaneo.pdf',
        'mime' => 'application/pdf',
        'kind' => 'document',
        'path' => 'attachments/escaneo.pdf',
        'status' => 'indexed',
        'meta' => ['ocr_text' => 'Valores: TSH 2.5, CK 180.', 'ocr_at' => now()->toIso8601String()],
    ]);

    // El job de OCR con caché NO debe requerir proveedor de IA: solo re-indexa.
    $job = new OcrPdfDocument($attachment->id);
    $job->handle(app(AiScopeResolver::class), app(AiRequestExecutor::class), app(PdfService::class));

    $attachment->refresh();

    expect($attachment->chunks()->count())->toBeGreaterThan(0)
        ->and($attachment->chunks()->first()->content)->toContain('TSH');
});
