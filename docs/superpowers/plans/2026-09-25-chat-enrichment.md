# Chat enriquecido (razonamiento, adjuntos, confirmaciones) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir al chat fase de pensamiento en vivo y persistida, adjuntos (imágenes + documentos txt/md/docx con retrieval FTS5 ligado al hilo) y confirmaciones interactivas (aprobaciones de escritura y preguntas del agente), arreglando antes los cortes de SSE por nginx.

**Architecture:** F0 corrige nginx (timeouts + buffering) y humaniza el error de transporte. F1 añade un driver `reasoning-compatible` que emite eventos `reasoning_*` y los persiste en `meta.reasoning`. F2 introduce `chat_attachments` + `chat_document_chunks` (FTS5), un pipeline de extracción/indexado en cola y un middleware de agente que inyecta fragmentos relevantes sin contaminar el mensaje persistido. F3 activa approvals nativos del SDK (`Approvable`, `Decisions`) para escrituras y una `AskUserTool` cuya respuesta viaja como `reject(result)`.

**Tech Stack:** PHP 8.4 · Laravel 12 · laravel/ai v0.11 · Inertia v2 · React 19 · Tailwind v4 · SQLite FTS5 · react-dropzone · Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-25-chat-enrichment-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest 4. Tests: `php artisan test --compact --filter=<name>`.
- `vendor/bin/pint --dirty --format agent` antes de cerrar cada task con PHP.
- Frontend: `npm run types`, `npx eslint <archivos> --fix`, `npm run build`.
- Wayfinder: `php artisan wayfinder:generate --with-form` tras cambiar rutas (salida gitignored, no stagear).
- Convenciones: Eloquent `casts()`, Form Requests, `$this->authorize` + policies, sin `DB::` salvo migraciones/FTS y transacciones, tokens Ember (sin hex nuevos), sin `dangerouslySetInnerHTML`.
- Eventos SSE del SDK: no renombrar los existentes; los nuevos son `reasoning_start|delta|end` y `tool_approval_request` (con `approvals: [{id, tool, arguments, reason}]`).
- `AskUserTool`: la respuesta libre viaja como `Decision::reject($texto)`; documentarlo en código.
- El middleware de documentos usa `AgentPrompt::append()` (devuelve copia) — NUNCA mutar `$prompt` original.
- Stagear solo los archivos de cada task (repo con WIP ajeno ya checkpointed en `f5574bd`); commits `feat(ai): …` / `fix(ai): …`.
- No logs de contenido de chat, documentos ni razonamiento.

---

### Task 1: F0 — Hotfix nginx + mensaje de corte en el cliente

**Files:**
- Modify (infra, fuera del repo): `/opt/dev/nginx/conf.d/megalomaniac.conf`
- Create: `docker/nginx/conf.d/megalomaniac.conf` (copia de referencia versionada)
- Modify: `resources/js/lib/chat-sse.ts`

**Interfaces:**
- Produces: respuesta SSE que ya no se corta a los 60s y mensaje `La conexión con la IA se interrumpió antes de terminar. Reinténtalo.` ante cortes de transporte.
- Consumes: nada.

- [ ] **Step 1: Editar el vhost vivo y recargar**

Sustituir el bloque `location ~ \.php$` de `/opt/dev/nginx/conf.d/megalomaniac.conf` por:

```nginx
    location ~ \.php$ {
        fastcgi_pass dev-megalomaniac:8070;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
        fastcgi_buffering off;
    }
```

Run: `docker exec dev-nginx nginx -t && docker exec dev-nginx nginx -s reload`
Expected: `syntax is ok` / `test is successful` / `reload ... done`

- [ ] **Step 2: Copia de referencia en el repo**

Crear `docker/nginx/conf.d/megalomaniac.conf` con el archivo completo resultante del Step 1 (el compose monta `/opt/dev/nginx/conf.d`; esta copia documenta el cambio en git).

- [ ] **Step 3: Humanizar el error de transporte en el cliente**

En `resources/js/lib/chat-sse.ts`, dentro de `streamChatRequest`, envolver el `while (true) { const { done, value } = await reader.read(); ... }` en un `try/catch`:

```ts
    let finished = false;

    try {
        while (true) {
            const { done, value } = await reader.read();

            if (done) break;

            buffer += decoder.decode(value, { stream: true });

            const parts = buffer.split('\n\n');
            buffer = parts.pop() ?? '';

            for (const part of parts) {
                const line = part.trim();

                if (!line.startsWith('data:')) continue;

                const payload = line.slice(5).trim();

                if (payload === '') continue;

                if (payload === '[DONE]') {
                    finished = true;

                    return;
                }

                try {
                    dispatch(JSON.parse(payload) as StreamEvent, handlers);
                } catch {
                    // línea no JSON
                }
            }
        }
    } catch (caught) {
        if (caught instanceof DOMException && caught.name === 'AbortError') {
            throw caught;
        }

        throw new Error('La conexión con la IA se interrumpió antes de terminar. Reinténtalo.');
    }

    if (!finished) {
        throw new Error('La conexión con la IA se interrumpió antes de terminar. Reinténtalo.');
    }
```

(El bucle `while` y el bloque `if (!finished)` existentes se unifican aquí; conservar intacta la detección de `[DONE]` y el `return` temprano.)

- [ ] **Step 4: Verificar**

Run: `npm run types`
Expected: 0 errores
Run: `npx eslint resources/js/lib/chat-sse.ts`
Expected: 0 errores
Run: `npm run build`
Expected: build OK

- [ ] **Step 5: Verificación manual del corte**

Con un proveedor fake lento (SSE con >60s de silencio), una generación ya no debe cortarse; y si se fuerza un corte (matar el proceso del fake a mitad), la UI debe mostrar el mensaje humanizado, no el crudo del navegador.

- [ ] **Step 6: Commit**

```bash
git add docker/nginx/conf.d/megalomaniac.conf resources/js/lib/chat-sse.ts
git commit -m "fix(ai): keep long chat streams alive behind nginx and humanize transport errors"
```

---

### Task 2: F1a — Driver de razonamiento (gateway + provider + registro)

**Files:**
- Create: `app/Ai/Gateway/ReasoningOpenAiCompatibleGateway.php`
- Create: `app/Ai/Providers/ReasoningOpenAiCompatibleProvider.php`
- Create: `app/Providers/AiChatServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: `config/ai.php` (provider `user` → driver `reasoning-compatible`)
- Test: `tests/Feature/Ai/ReasoningGatewayTest.php`

**Interfaces:**
- Consumes: `Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway`, `Laravel\Ai\Streaming\Events\{ReasoningStart,ReasoningDelta,ReasoningEnd,TextDelta,ToolCall}`.
- Produces: driver `reasoning-compatible` registrado en `AiManager`; `ReasoningOpenAiCompatibleProvider::textGateway()` devuelve el gateway que emite `reasoning_*`.

- [ ] **Step 1: Escribir el test (fallará)**

```php
<?php

use App\Ai\Providers\ReasoningOpenAiCompatibleProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\TextDelta;

test('reasoning compatible provider is registered as a driver', function () {
    $provider = app(\Laravel\Ai\AiManager::class)->driver('reasoning-compatible');

    expect($provider)->toBeInstanceOf(ReasoningOpenAiCompatibleProvider::class);
});

test('gateway emits reasoning deltas before text deltas', function () {
    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => 'Pienso'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => ' mucho'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'Hola'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
    ])."\n\n";

    $stream = \GuzzleHttp\Psr7\Utils::streamFor($sse);
    $gateway = new class(app(Dispatcher::class)) extends App\Ai\Gateway\ReasoningOpenAiCompatibleGateway
    {
        public function exposeStream($body): Generator
        {
            return $this->streamBody($body, 'inv-1', 'msg-1');
        }
    };

    $events = iterator_to_array($gateway->exposeStream($stream));

    $reasoning = implode('', array_map(fn ($e) => $e instanceof ReasoningDelta ? $e->delta : '', $events));
    $text = implode('', array_map(fn ($e) => $e instanceof TextDelta ? $e->delta : '', $events));

    expect($reasoning)->toBe('Pienso mucho');
    expect($text)->toBe('Hola');
});
```

Nota: `streamBody()` y el método `exposeStream()` son helpers del test; el gateway debe exponer un método protegido `streamBody($body, string $invocationId, string $messageId): Generator` con la lógica de parseo (nombrable/overrideable).

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=ReasoningGatewayTest`
Expected: FAIL (clases no existen)

- [ ] **Step 3: Implementar el gateway**

Copiar el cuerpo actual de `vendor/laravel/ai/src/Gateway/OpenAiCompatible/Concerns/HandlesTextStreaming.php` (v0.11) como base de `streamBody()`, con estos cambios: detectar `$delta['reasoning_content']` → emitir `ReasoningStart` (una vez, asociado a un `reasoningId` uuid) y `ReasoningDelta` por fragmento; al primer `$delta['content']` no vacío o al terminar, emitir `ReasoningEnd`. Mantener intactos `StreamStart`, `TextStart/Delta/End`, `ToolCall`, `ToolResult`, `Error`, `StreamEnd` y el manejo de `usage`. Documentar en el encabezado: `// Ported from laravel/ai v0.11 HandlesTextStreaming; contract test: ReasoningGatewayTest.`

```php
<?php

namespace App\Ai\Gateway;

use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;

class ReasoningOpenAiCompatibleGateway extends OpenAiCompatibleGateway
{
    // streamBody() con el parser ampliado (ver arriba)
}
```

- [ ] **Step 4: Implementar el provider y el registro**

```php
<?php

namespace App\Ai\Providers;

use App\Ai\Gateway\ReasoningOpenAiCompatibleGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;

class ReasoningOpenAiCompatibleProvider extends OpenAiCompatibleProvider
{
    #[\Override]
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new ReasoningOpenAiCompatibleGateway($this->events);
    }
}
```

```php
<?php

namespace App\Providers;

use App\Ai\Providers\ReasoningOpenAiCompatibleProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

class AiChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Ai::extend('reasoning-compatible', fn ($app, array $config) => new ReasoningOpenAiCompatibleProvider($config, $app['events']));
    }
}
```

Registrar `App\Providers\AiChatServiceProvider::class` en `bootstrap/providers.php` y en `config/ai.php` cambiar solo el provider `'user'`:

```php
        'user' => [
            'driver' => 'reasoning-compatible',
            'url' => null,
            'key' => null,
        ],
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=ReasoningGatewayTest`
Expected: 2 passed

Run: `php artisan test --compact --filter=Chat`
Expected: suite de chat verde (el driver nuevo no debe romper nada)

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Gateway/ReasoningOpenAiCompatibleGateway.php app/Ai/Providers/ReasoningOpenAiCompatibleProvider.php app/Providers/AiChatServiceProvider.php bootstrap/providers.php config/ai.php tests/Feature/Ai/ReasoningGatewayTest.php
git commit -m "feat(ai): add reasoning-aware openai-compatible driver"
```

---

### Task 3: F1b — Persistencia del razonamiento + panel UI

**Files:**
- Modify: `app/Http/Controllers/Ai/ChatController.php`
- Modify: `app/Http/Resources/ChatMessageResource.php`
- Modify: `resources/js/types/chat.ts`
- Modify: `resources/js/lib/chat-sse.ts`
- Modify: `resources/js/hooks/use-chat-stream.ts`
- Create: `resources/js/components/ai/chat/ReasoningPanel.tsx`
- Modify: `resources/js/components/ai/chat/AssistantMessage.tsx`
- Modify: `resources/js/components/ai/chat/MessageList.tsx`
- Modify: `resources/js/pages/ai/thread.tsx`
- Test: `tests/Feature/Ai/ChatStreamTest.php` (añadir caso)

**Interfaces:**
- Consumes: eventos `reasoning_start|delta|end` (Task 2).
- Produces: `ChatMessageResource.reasoning: {text: string, duration_ms: int}|null`; `useChatStream.reasoning: string` + `reasoningMs: number|null`; `ReasoningPanel({text, durationMs, streaming})`.

- [ ] **Step 1: Test de persistencia (fallará)**

Añadir a `tests/Feature/Ai/ChatStreamTest.php`:

```php
test('reasoning deltas are persisted on the assistant message meta', function () {
    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => 'Analizo'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'Listo'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
    ])."\n\n";

    Http::fake(['*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);
    $content = $response->streamedContent();

    expect($content)->toContain('reasoning_delta');

    $thread = \App\Models\ChatThread::query()->forUser($user)->first();
    $assistant = $thread->messages()->orderByDesc('id')->first();

    expect($assistant->meta['reasoning']['text'])->toBe('Analizo');
    expect($assistant->meta['reasoning'])->toHaveKey('duration_ms');
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=ChatStreamTest`
Expected: FAIL (no hay `meta.reasoning`)

- [ ] **Step 3: Implementar la captura y persistencia**

En `ChatController::streamResponse`, antes del `try`:

```php
        $reasoning = '';
        $reasoningStartedAt = null;
```

En el `foreach`, antes de `echo`:

```php
                $eventArray = $event->toArray();
                $eventType = $eventArray['type'] ?? null;

                if ($eventType === 'reasoning_delta') {
                    $reasoningStartedAt ??= microtime(true);
                    $reasoning .= (string) ($eventArray['delta'] ?? '');
                }
```

Después del `try/catch` (antes del `[DONE]`), persistir si hubo razonamiento:

```php
            if ($reasoning !== '') {
                $this->storeReasoning($thread, $reasoning, $reasoningStartedAt);
            }
```

Y el método protegido:

```php
    protected function storeReasoning(ChatThread $thread, string $reasoning, ?float $startedAt): void
    {
        $message = $thread->messages()->orderByDesc('id')->first();

        if (! $message instanceof \App\Models\ChatMessage || $message->role !== 'assistant') {
            return;
        }

        $meta = $message->meta ?? [];
        $meta['reasoning'] = [
            'text' => trim($reasoning),
            'duration_ms' => $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000),
        ];

        $message->update(['meta' => $meta]);
    }
```

- [ ] **Step 4: Exponer en el resource**

En `ChatMessageResource::toArray` añadir:

```php
            'reasoning' => $this->meta['reasoning'] ?? null,
```

En `resources/js/types/chat.ts`:

```ts
export interface ChatReasoning {
    text: string;
    duration_ms: number | null;
}
```

y `ChatMessage` gana `reasoning: ChatReasoning | null;`.

- [ ] **Step 5: Cliente: parser + hook**

`chat-sse.ts`: `StreamEvent` gana `reasoning_id?: string`; `dispatch` añade:

```ts
        case 'reasoning_delta':
            if (event.delta) handlers.onReasoningDelta?.(event.delta);
            break;
```

`ChatStreamHandlers` gana `onReasoningDelta?: (delta: string) => void;`.

`use-chat-stream.ts`: estado `reasoning` + ref `reasoningStartedAtRef` + estado `reasoningMs`; en `start` se resetean; `onReasoningDelta` acumula y fija el inicio en la primera llamada; al completar (`onComplete`) calcula `reasoningMs`. Exponer ambos en el resultado.

- [ ] **Step 6: UI — ReasoningPanel**

```tsx
import { useState } from 'react';
import { Brain, ChevronDown } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

interface ReasoningPanelProps {
    text: string;
    durationMs?: number | null;
    streaming?: boolean;
}

export function ReasoningPanel({ text, durationMs = null, streaming = false }: ReasoningPanelProps) {
    const [open, setOpen] = useState(streaming);

    if (text === '') return null;

    const label = streaming
        ? 'Pensando…'
        : durationMs ? `Pensó durante ${Math.max(1, Math.round(durationMs / 1000))}s` : 'Razonamiento';

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="mb-2 rounded-xl border border-border bg-card/60">
            <CollapsibleTrigger className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-muted-foreground hover:text-foreground">
                <Brain className={cn('h-3.5 w-3.5 text-primary', streaming && 'animate-pulse motion-reduce:animate-none')} />
                <span>{label}</span>
                <ChevronDown className={cn('ml-auto h-3.5 w-3.5 transition-transform', open && 'rotate-180')} />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="max-h-52 overflow-auto border-t border-border px-3 py-2 text-xs leading-relaxed whitespace-pre-wrap text-muted-foreground">
                    {text}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
```

Auto-colapso: en `AssistantMessage`, pasar `streaming={streaming && content === ''}` para que el panel se auto-expanda sólo en la fase sin texto (el `useState(streaming)` inicial gestiona el arranque; al llegar texto, el panel se recrea con `streaming=false` y arranca colapsado).

- [ ] **Step 7: UI — cableado**

- `AssistantMessage` gana props `reasoning?: string` y `reasoningMs?: number | null`; renderiza `<ReasoningPanel text={reasoning ?? ''} durationMs={reasoningMs} streaming={streaming && content === ''} />` antes de `<Markdown>`.
- `MessageList` pasa `liveReasoning={liveReasoning}` y, para mensajes persistidos, `reasoning={message.reasoning?.text}` + `reasoningMs={message.reasoning?.duration_ms}`.
- `thread.tsx` pasa `liveReasoning={stream.reasoning}` y `reasoningMs={stream.reasoningMs}` a `MessageList`.

- [ ] **Step 8: Verificar**

Run: `php artisan test --compact --filter=Chat`  → verde
Run: `npm run types` → 0 · `npx eslint resources/js ...--fix` → 0 · `npm run build` → OK

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Ai/ChatController.php app/Http/Resources/ChatMessageResource.php tests/Feature/Ai/ChatStreamTest.php resources/js/types/chat.ts resources/js/lib/chat-sse.ts resources/js/hooks/use-chat-stream.ts resources/js/components/ai/chat/ReasoningPanel.tsx resources/js/components/ai/chat/AssistantMessage.tsx resources/js/components/ai/chat/MessageList.tsx resources/js/pages/ai/thread.tsx
git commit -m "feat(ai): stream and persist assistant reasoning"
```

---

### Task 4: F2a — Migraciones, modelos y FTS5

**Files:**
- Create: `database/migrations/<ts>_create_chat_attachments_and_document_chunks_tables.php`
- Create: `database/migrations/<ts>_create_chat_document_chunks_fts_table.php`
- Create: `app/Models/ChatAttachment.php`
- Create: `app/Models/ChatDocumentChunk.php`
- Create: `database/factories/ChatAttachmentFactory.php`
- Create: `database/factories/ChatDocumentChunkFactory.php`
- Test: `tests/Feature/Ai/ChatAttachmentModelTest.php`

**Interfaces:**
- Consumes: `ChatThread` (Spec A).
- Produces: `ChatAttachment` (`thread()`, `chunks()`, scopes `images()`, `documents()`, `ready()`, `forUser(User)`, `isIndexed(): bool`); `ChatDocumentChunk` (`attachment()`); tabla FTS5 `chat_document_chunks_fts` sincronizada por triggers.

- [ ] **Step 1: Test (fallará)**

```php
<?php

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('attachment relations and scopes', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create();
    $image = ChatAttachment::factory()->create(['user_id' => $user->id, 'thread_id' => $thread->id, 'kind' => 'image']);
    ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'document', 'status' => 'indexed']);

    expect($thread->attachments()->count())->toBe(1);
    expect(ChatAttachment::query()->forUser($user)->images()->count())->toBe(1);
    expect(ChatAttachment::query()->forUser($user)->documents()->count())->toBe(1);
    expect($image->isIndexed())->toBeFalse();
});

test('chunks are searchable through fts5', function () {
    $attachment = ChatAttachment::factory()->create(['kind' => 'document', 'status' => 'indexed']);
    ChatDocumentChunk::create(['attachment_id' => $attachment->id, 'position' => 0, 'content' => 'La rutina de hipertrofia usa press banca y sentadilla']);
    ChatDocumentChunk::create(['attachment_id' => $attachment->id, 'position' => 1, 'content' => 'El presupuesto mensual incluye inversiones']);

    $rows = DB::select("select rowid from chat_document_chunks_fts where chat_document_chunks_fts match 'hipertrofia'");

    expect($rows)->toHaveCount(1);

    ChatDocumentChunk::query()->where('position', 0)->delete();

    $rows = DB::select("select rowid from chat_document_chunks_fts where chat_document_chunks_fts match 'hipertrofia'");

    expect($rows)->toHaveCount(0);
});
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=ChatAttachmentModelTest` → FAIL.

- [ ] **Step 3: Migración de tablas**

```php
Schema::create('chat_attachments', function (Blueprint $table) {
    $table->string('id', 36)->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('thread_id', 36)->nullable()->index();
    $table->string('message_id', 36)->nullable()->index();
    $table->string('kind', 10);          // image | document
    $table->string('disk', 30)->default('local');
    $table->string('path', 500);
    $table->string('original_name', 255);
    $table->string('mime', 100);
    $table->unsignedInteger('size');
    $table->string('status', 12)->default('ready'); // ready | pending | indexed | failed
    $table->text('error')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'thread_id']);
});

Schema::create('chat_document_chunks', function (Blueprint $table) {
    $table->id();
    $table->string('attachment_id', 36)->index();
    $table->unsignedInteger('position');
    $table->text('content');
    $table->timestamps();

    $table->foreign('attachment_id')->references('id')->on('chat_attachments')->cascadeOnDelete();
    $table->index(['attachment_id', 'position']);
});
```

`ChatAttachment::thread()` es `belongsTo(ChatThread::class, 'thread_id')`.

- [ ] **Step 4: Migración FTS5 (con triggers)**

```php
DB::statement("
    CREATE VIRTUAL TABLE chat_document_chunks_fts USING fts5(
        content,
        content='chat_document_chunks',
        content_rowid='id',
        tokenize='unicode61 remove_diacritics 2'
    );
");

DB::unprepared("
    CREATE TRIGGER chat_document_chunks_ai AFTER INSERT ON chat_document_chunks BEGIN
        INSERT INTO chat_document_chunks_fts(rowid, content) VALUES (new.id, new.content);
    END;
    CREATE TRIGGER chat_document_chunks_ad AFTER DELETE ON chat_document_chunks BEGIN
        INSERT INTO chat_document_chunks_fts(chat_document_chunks_fts, rowid, content) VALUES ('delete', old.id, old.content);
    END;
    CREATE TRIGGER chat_document_chunks_au AFTER UPDATE ON chat_document_chunks BEGIN
        INSERT INTO chat_document_chunks_fts(chat_document_chunks_fts, rowid, content) VALUES ('delete', old.id, old.content);
        INSERT INTO chat_document_chunks_fts(rowid, content) VALUES (new.id, new.content);
    END;
");
```

`down()`: `DROP TRIGGER` x3 + `DROP TABLE chat_document_chunks_fts` (el orden importa antes de dropear las tablas).

- [ ] **Step 5: Modelos y factories**

`ChatAttachment`: `use HasFactory; public $incrementing = false; protected $keyType = 'string';` casts `size => integer`; scopes `forUser`, `images`, `documents`, `ready`, `indexed`; `isIndexed(): bool` (`status === 'indexed'`); `threadsDocs` nada más. Factory: id uuid7, user_id factory, kind 'image', disk 'local', path `ai-attachments/qa/test.png`, original_name 'test.png', mime 'image/png', size 1234, status 'ready'.

`ChatDocumentChunk`: `$guarded = [];` relation `attachment()`.

- [ ] **Step 6: Verificar y commit**

Run: `php artisan test --compact --filter=ChatAttachmentModelTest` → 2 passed
```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*chat_attachments* database/migrations/*fts* app/Models/ChatAttachment.php app/Models/ChatDocumentChunk.php database/factories/ChatAttachmentFactory.php database/factories/ChatDocumentChunkFactory.php tests/Feature/Ai/ChatAttachmentModelTest.php
git commit -m "feat(ai): add chat attachment and fts5 document chunk tables"
```

---

### Task 5: F2b — Extracción, chunking y job de indexado

**Files:**
- Create: `app/Ai/Documents/TextExtractor.php`
- Create: `app/Ai/Documents/DocxExtractor.php`
- Create: `app/Ai/Documents/ExtractorFactory.php`
- Create: `app/Ai/Documents/DocumentIndexer.php`
- Create: `app/Jobs/IndexChatDocument.php`
- Test: `tests/Feature/Ai/DocumentIndexingTest.php`

**Interfaces:**
- Consumes: `ChatAttachment`, `ChatDocumentChunk` (Task 4).
- Produces: `DocumentIndexer::index(ChatAttachment): void` (status `pending` → `indexed`/`failed`); `ExtractorFactory::for(string $mime, string $extension): ?TextExtractor|DocxExtractor`; job `IndexChatDocument`.

- [ ] **Step 1: Test (fallará)**

```php
<?php

use App\Ai\Documents\DocumentIndexer;
use App\Models\ChatAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=DocumentIndexingTest` → FAIL.

- [ ] **Step 3: Implementar extractores**

```php
// TextExtractor: lee el contenido (txt/md) y valida UTF-8
public function extract(string $disk, string $path): string
{
    $raw = (string) Storage::disk($disk)->get($path);

    if (! mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }

    return $raw;
}
```

```php
// DocxExtractor: sin dependencias (ZipArchive + XML)
public function extract(string $disk, string $path): string
{
    $zip = new ZipArchive;

    if ($zip->open(Storage::disk($disk)->path($path)) !== true) {
        throw new RuntimeException('No se pudo abrir el DOCX.');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        throw new RuntimeException('DOCX sin word/document.xml.');
    }

    $xml = str_replace(['</w:p>', '<w:br/>', '</w:tr>'], "\n", $xml);
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

    return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
}
```

`ExtractorFactory::for(mime, extension)`: `text/plain|text/markdown|txt|md` → TextExtractor; mime docx o extensión `docx` → DocxExtractor; resto → null.

- [ ] **Step 4: Implementar chunking + indexer + job**

`DocumentIndexer`:

```php
public function index(ChatAttachment $attachment): void
{
    DB::transaction(function () use ($attachment): void {
        $attachment->chunks()->delete();

        try {
            $extractor = ExtractorFactory::for($attachment->mime, pathinfo($attachment->path, PATHINFO_EXTENSION));

            if ($extractor === null) {
                throw new RuntimeException('Tipo de documento no soportado.');
            }

            $text = trim($extractor->extract($attachment->disk, $attachment->path));

            if ($text === '') {
                throw new RuntimeException('El documento no contiene texto extraíble.');
            }

            foreach ($this->chunks($text) as $position => $content) {
                ChatDocumentChunk::create([
                    'attachment_id' => $attachment->id,
                    'position' => $position,
                    'content' => $content,
                ]);
            }

            $attachment->update(['status' => 'indexed', 'error' => null]);
        } catch (Throwable $exception) {
            report($exception);
            $attachment->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500)]);
        }
    });
}

/**
 * @return array<int, string>
 */
protected function chunks(string $text, int $size = 1000, int $overlap = 200): array
{
    $normalized = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
    $chunks = [];
    $offset = 0;
    $length = mb_strlen($normalized);

    while ($offset < $length) {
        $chunks[] = mb_substr($normalized, $offset, $size);
        $offset += max(1, $size - $overlap);
    }

    return $chunks;
}
```

Job:

```php
class IndexChatDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $attachmentId) {}

    public function handle(DocumentIndexer $indexer): void
    {
        $attachment = ChatAttachment::find($this->attachmentId);

        if ($attachment !== null && $attachment->kind === 'document') {
            $indexer->index($attachment);
        }
    }
}
```

- [ ] **Step 5: Verificar y commit**

Run: `php artisan test --compact --filter=DocumentIndexingTest` → 4 passed
```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Documents app/Jobs/IndexChatDocument.php tests/Feature/Ai/DocumentIndexingTest.php
git commit -m "feat(ai): index chat documents into fts5 chunks"
```

---

### Task 6: F2c — Endpoints de adjuntos (subida, descarga, borrado)

**Files:**
- Create: `app/Policies/ChatAttachmentPolicy.php`
- Create: `app/Http/Requests/Ai/StoreChatAttachmentRequest.php`
- Create: `app/Http/Resources/ChatAttachmentResource.php`
- Create: `app/Http/Controllers/Ai/ChatAttachmentController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Ai/ChatAttachmentUploadTest.php`

**Interfaces:**
- Consumes: modelos (Task 4), job (Task 5).
- Produces: rutas `ai.chat.attachments.store` (POST `ai/chat/attachments`), `ai.chat.attachments.index` (GET `ai/chat/attachments?ids[]=…`), `ai.chat.attachments.show` (GET `ai/chat/attachments/{attachment}`), `ai.chat.attachments.destroy` (DELETE). Resource `{id, kind, name, mime, size, status, url, is_image, error}`.

- [ ] **Step 1: Tests (fallarán)**

```php
<?php

use App\Models\ChatAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an image uploads and returns a ready resource', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->image('foto.png', 200, 200),
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'image')->assertJsonPath('status', 'ready');
    expect(ChatAttachment::query()->forUser($user)->images()->count())->toBe(1);
});

test('a document uploads, queues indexing and reports pending', function () {
    Storage::fake('local');
    Queue::fake();
    $user = User::factory()->create();
    $thread = \App\Models\ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('ai.chat.attachments.store'), [
        'file' => UploadedFile::fake()->createWithContent('notas.txt', 'hola mundo'),
        'thread_id' => $thread->id,
    ]);

    $response->assertCreated()->assertJsonPath('kind', 'document')->assertJsonPath('status', 'pending');
    Queue::assertPushed(\App\Jobs\IndexChatDocument::class);
});

test('uploads validate mime and size', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.attachments.store'), ['file' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('file');
});

test('another user cannot view or delete an attachment', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $attachment = ChatAttachment::factory()->create(['user_id' => $owner->id]);
    Storage::disk('local')->put($attachment->path, 'x');

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('ai.chat.attachments.show', $attachment))->assertNotFound();
    $this->actingAs($intruder)->delete(route('ai.chat.attachments.destroy', $attachment))->assertForbidden();
});

test('owner can stream the file inline and delete it', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $attachment = ChatAttachment::factory()->create(['user_id' => $user->id, 'path' => 'ai-attachments/qa/x.png']);
    Storage::disk('local')->put($attachment->path, 'binary');

    $this->actingAs($user)
        ->get(route('ai.chat.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="test.png"');

    $this->actingAs($user)->delete(route('ai.chat.attachments.destroy', $attachment))->assertRedirect();
    expect(ChatAttachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=ChatAttachmentUploadTest` → FAIL.

- [ ] **Step 3: Form Request, policy y controller**

`StoreChatAttachmentRequest`: `file` required file max 25600 (25MB) `mimes:jpg,jpeg,png,webp,txt,md,docx`; `thread_id` nullable string size:36. En `withValidator` o `after`: si `file` es imagen y `> 10240` KB → error `file` (imágenes max 10MB).

`ChatAttachmentPolicy`: `view`/`delete` → `$attachment->user_id === $user->id` (`delete` con bool para 403; `view` con `Response::denyAsNotFound()` para 404, igual que `ChatThreadPolicy`).

`ChatAttachmentController`:
- `store`: valida; determina `kind` por mime (`image/*` → image); guarda con `$request->file('file')->store('ai-attachments/'.$user->id, 'local')`; `status = image ? 'ready' : 'pending'`; si `thread_id` viene, verifica propiedad (`ChatThread::query()->forUser($user)->findOrFail($threadId)`); crea el registro; despacha `IndexChatDocument` para documentos; responde `ChatAttachmentResource` con 201.
- `index`: `ids[]` (max 25) → `ChatAttachment::query()->forUser($user)->whereIn('id', $ids)->get()` → `ChatAttachmentResource::collection(...)` (usado por el polling de indexado del cliente).
- `show`: `authorize('view')`; `Storage::disk($attachment->disk)->path($attachment->path)`; `response()->file($path, ['Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"'])`.
- `destroy`: `authorize('delete')`; borra archivo + registro (chunks por cascade); `back()`.

`ChatAttachmentResource`:

```php
return [
    'id' => $this->id,
    'kind' => $this->kind,
    'name' => $this->original_name,
    'mime' => $this->mime,
    'size' => $this->size,
    'status' => $this->status,
    'error' => $this->error,
    'is_image' => $this->kind === 'image',
    'url' => route('ai.chat.attachments.show', $this),
];
```

Rutas (grupo auth+verified): `Route::post('ai/chat/attachments', …)->name('ai.chat.attachments.store')`, `Route::get('ai/chat/attachments/{attachment}', …)->name('ai.chat.attachments.show')`, `Route::delete(...)->name('ai.chat.attachments.destroy')`. `php artisan wayfinder:generate --with-form`.

- [ ] **Step 4: Verificar y commit**

Run: `php artisan test --compact --filter=ChatAttachmentUploadTest` → 5 passed
```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/ChatAttachmentPolicy.php app/Http/Requests/Ai/StoreChatAttachmentRequest.php app/Http/Resources/ChatAttachmentResource.php app/Http/Controllers/Ai/ChatAttachmentController.php routes/web.php tests/Feature/Ai/ChatAttachmentUploadTest.php
git commit -m "feat(ai): add chat attachment upload, streaming and delete endpoints"
```

---

### Task 7: F2d — Enviar imágenes con el mensaje

**Files:**
- Modify: `app/Http/Requests/Ai/SendChatMessageRequest.php`
- Modify: `app/Ai/Services/ChatService.php`
- Modify: `app/Http/Controllers/Ai/ChatController.php`
- Modify: `app/Models/ChatMessage.php`
- Modify: `app/Http/Resources/ChatMessageResource.php`
- Test: `tests/Feature/Ai/ChatStreamTest.php` (añadir casos)

**Interfaces:**
- Consumes: `ChatAttachment` (Task 4), endpoints (Task 6).
- Produces: `POST ai/chat` acepta `attachment_ids: string[]` (máx 5); el mensaje del usuario queda ligado (`ChatAttachment.message_id`) y `ChatMessageResource.attachments: ChatAttachmentResource[]`.

- [ ] **Step 1: Tests (fallarán)**

```php
test('an image attachment is sent to the provider and linked to the user message', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $path = 'ai-attachments/'.$user->id.'/foto.png';
    Storage::disk('local')->put($path, 'fake-image-bytes');
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id, 'kind' => 'image', 'status' => 'ready',
        'path' => $path, 'mime' => 'image/png', 'original_name' => 'foto.png',
    ]);

    Http::fake(['*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué ves?',
        'attachment_ids' => [$attachment->id],
    ])->streamedContent();

    Http::assertSent(function ($request) {
        return str_contains(json_encode($request->data()), 'image_url');
    });

    expect($attachment->refresh()->message_id)->not->toBeNull();
});

test('only own images can be attached', function () {
    $user = User::factory()->withAiProvider()->create();
    $foreign = ChatAttachment::factory()->create(['kind' => 'image']);

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'x', 'attachment_ids' => [$foreign->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachment_ids.0');
});
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=ChatStreamTest` → FAIL.

- [ ] **Step 3: Request + servicio**

`SendChatMessageRequest` reglas añadidas:

```php
            'attachment_ids' => ['nullable', 'array', 'max:5'],
            'attachment_ids.*' => ['string', 'size:36'],
```

`ChatService::streamTurn(User $user, ChatThread $thread, string $message, ?string $model = null, ?array $attachmentIds = null)` (mantener el `$toolsPolicy` que ya exista en la rama): resolver adjuntos imágenes propios y `ready`:

```php
        $attachments = ChatAttachment::query()
            ->forUser($user)
            ->whereIn('id', $attachmentIds ?? [])
            ->images()
            ->ready()
            ->get()
            ->map(fn (ChatAttachment $attachment) => StoredImage::fromStorage($attachment->disk, $attachment->path))
            ->all();

        return (new MegalomaniacAgent($user, $thread))
            ->continue($thread->id, as: $user)
            ->stream($message, attachments: $attachments, provider: $provider, model: $model ?: $defaultModel);
```

`ChatController::send`: validar que todos los ids existen/están listos (si no, 422 con `attachment_ids.0`), y pasar `$request->validated('attachment_ids')` a `streamResponse(...)` para ligarlos tras persistir.

- [ ] **Step 4: Ligar y exponer**

En `streamResponse(..., ?array $attachmentIds = null)`, tras el `try/catch`:

```php
            if ($attachmentIds !== null && $attachmentIds !== []) {
                $userMessage = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();

                if ($userMessage !== null) {
                    ChatAttachment::query()->whereIn('id', $attachmentIds)->update(['message_id' => $userMessage->id]);
                }
            }
```

`ChatMessage` gana `attachments(): HasMany` (`ChatAttachment`, `message_id`). `ChatMessageResource` añade:

```php
            'attachments' => ChatAttachmentResource::collection($this->whenLoaded('attachments')->loadMissing('attachments') ?? collect()),
```

mantenerlo simple: `'attachments' => ChatAttachmentResource::collection($this->attachments)` y en `ChatController::show` cargar `$thread->messages()->with('attachments')`.

- [ ] **Step 5: Verificar y commit**

Run: `php artisan test --compact --filter=Chat` → verde
```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Ai/SendChatMessageRequest.php app/Ai/Services/ChatService.php app/Http/Controllers/Ai/ChatController.php app/Models/ChatMessage.php app/Http/Resources/ChatMessageResource.php tests/Feature/Ai/ChatStreamTest.php
git commit -m "feat(ai): send image attachments with chat messages"
```

---

### Task 8: F2e — Inyección de documentos del hilo (middleware)

**Files:**
- Create: `app/Ai/Middleware/InjectThreadDocumentContext.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php`
- Modify: `app/Ai/Services/ChatService.php` (pasar el hilo al agente)
- Test: `tests/Feature/Ai/DocumentRetrievalTest.php`

**Interfaces:**
- Consumes: FTS5 + `ChatAttachment` (Tasks 4-5).
- Produces: `InjectThreadDocumentContext::__construct(?ChatThread $thread, string $query)`; el prompt que recibe el gateway incluye `--- Documentos del hilo (contexto) ---` cuando hay chunks relevantes; el mensaje persistido NO lo incluye.

- [ ] **Step 1: Tests (fallarán)**

```php
<?php

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function indexedDoc(ChatThread $thread, User $user, string $content): ChatAttachment
{
    $attachment = ChatAttachment::factory()->create([
        'user_id' => $user->id, 'thread_id' => $thread->id,
        'kind' => 'document', 'status' => 'indexed', 'original_name' => 'plan.txt',
    ]);
    ChatDocumentChunk::create(['attachment_id' => $attachment->id, 'position' => 0, 'content' => $content]);

    return $attachment;
}

test('thread documents are injected into the prompt without polluting the stored message', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);
    indexedDoc($thread, $user, 'El plan de hipertrofia usa press banca 4x8 y sentadilla 5x5.');

    Http::fake(['*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué dice el plan de hipertrofia?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Documentos del hilo'));

    $stored = $thread->messages()->where('role', 'user')->orderByDesc('id')->first();
    expect($stored->content)->toBe('¿Qué dice el plan de hipertrofia?');
});

test('documents from other threads are not injected', function () {
    Storage::fake('local');
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);
    $other = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);
    indexedDoc($other, $user, 'Secreto de otro hilo sobre criptomonedas.');

    Http::fake(['*' => Http::response("data: {\"choices\":[{\"delta\":{\"content\":\"ok\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])]);

    $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => '¿Qué dice sobre criptomonedas?',
        'thread_id' => $thread->id,
    ])->streamedContent();

    Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'criptomonedas'));
});
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=DocumentRetrievalTest` → FAIL.

- [ ] **Step 3: Middleware**

```php
<?php

namespace App\Ai\Middleware;

use App\Models\ChatThread;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectThreadDocumentContext
{
    public function __construct(protected ?ChatThread $thread, protected string $query) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        $context = $this->thread?->documentContext($this->query);

        if (blank($context)) {
            return $next($prompt);
        }

        return $next($prompt->append("\n\n--- Documentos del hilo (contexto) ---\n".$context));
    }
}
```

`ChatThread::documentContext(string $query, int $limit = 6): ?string` (en el modelo o en un servicio `ThreadDocumentSearch`): términos saneados del query (`preg_split('/[^\p{L}\p{N}]+/u')`, únicos, min 3 chars, unir con `OR` + `*` por término), consulta:

```php
$rows = DB::select("
    select c.content, a.original_name
    from chat_document_chunks_fts f
    join chat_document_chunks c on c.id = f.rowid
    join chat_attachments a on a.id = c.attachment_id
    where chat_document_chunks_fts match ?
      and a.thread_id = ?
      and a.user_id = ?
      and a.status = 'indexed'
    order by bm25(chat_document_chunks_fts)
    limit ?
", [$match, $this->id, $this->participant_id, $limit]);
```

Formatear como `### {original_name}\n{content}` unidos por `\n\n`. Si no hay términos útiles o resultados → null.

- [ ] **Step 4: Agente y servicio**

`MegalomaniacAgent`: ctor `public function __construct(public User $user, public ?ChatThread $thread = null) {}` + `implements HasMiddleware` + `middleware(): array { return $this->thread ? [new InjectThreadDocumentContext($this->thread, $this->lastMessage())] : []; }`. Como el middleware necesita el mensaje, la vía simple: `ChatService` construye el agente y el middleware con el mensaje:

```php
        $agent = new MegalomaniacAgent($user, $thread);
        $agent->withDocumentContext($message); // propiedad interna que middleware() usa
```

(método `withDocumentContext(string $message): static` que guarda `$this->documentQuery = $message`).

- [ ] **Step 5: Verificar y commit**

Run: `php artisan test --compact --filter=DocumentRetrievalTest` → 2 passed · `--filter=Chat` → verde
```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Middleware/InjectThreadDocumentContext.php app/Ai/Agents/MegalomaniacAgent.php app/Ai/Services/ChatService.php tests/Feature/Ai/DocumentRetrievalTest.php
git commit -m "feat(ai): inject thread document context into prompts"
```

---

### Task 9: F2f — UI de adjuntos (composer, chips, thumbnails)

**Files:**
- Create: `resources/js/hooks/use-attachment-upload.ts`
- Create: `resources/js/components/ai/chat/AttachmentChips.tsx`
- Modify: `resources/js/components/ai/chat/Composer.tsx`
- Modify: `resources/js/components/ai/chat/UserMessage.tsx`
- Modify: `resources/js/pages/ai/thread.tsx`, `resources/js/pages/ai/chat.tsx`
- Modify: `resources/js/types/chat.ts`
- Test: manual (sin framework JS) + types/build

**Interfaces:**
- Consumes: endpoints (Task 6), `attachment_ids` en send (Task 7).
- Produces: `useAttachmentUpload(threadId?)` → `{attachments, addFiles(files), remove(id), uploading, error}`; `Composer` gana `attachments`, `onAddFiles`, `onRemoveAttachment`, `uploading`.

- [ ] **Step 1: Tipos y hook**

`types/chat.ts`:

```ts
export interface ChatAttachment {
    id: string;
    kind: 'image' | 'document';
    name: string;
    mime: string;
    size: number;
    status: 'ready' | 'pending' | 'indexed' | 'failed';
    error: string | null;
    is_image: boolean;
    url: string;
}
```

`use-attachment-upload.ts` (fetch multipart con CSRF, mismo patrón que `chat-sse`):

```ts
const upload = async (file: File) => {
    const form = new FormData();
    form.append('file', file);
    if (threadId) form.append('thread_id', threadId);

    const response = await fetch(ChatAttachmentController.store().url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') },
        body: form,
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message ?? 'No se pudo subir el archivo.');
    }

    return (await response.json()) as ChatAttachment;
};
```

Con estado `attachments: ChatAttachment[]`, `addFiles` (valida máx 5 y peso en cliente), `remove(id)` (DELETE), `uploading` (algún `pending`), y polling de los documentos `pending` cada 3s contra `GET ai/chat/attachments?ids[]=…` (Task 6) hasta que pasen a `indexed|failed`.

- [ ] **Step 2: Chips**

`AttachmentChips.tsx`: lista de chips; imágenes muestran thumbnail (`<img src={attachment.url}>`, 40x40, `object-cover`, `rounded-lg`) y documentos icono `FileText`; nombre truncado + tamaño (`formatBytes`); estado `pending` → `Loader2` "Indexando…"; `failed` → icono `AlertCircle` `text-destructive` + botón reintentar (re-subir) y eliminar; botón `X` para quitar. Chips clicables para imágenes → `Dialog` con la imagen completa.

- [ ] **Step 3: Composer con dropzone**

- Botón clip (`Paperclip`) junto al `ModelPicker` que dispara un `<input type="file" multiple hidden accept="image/*,.txt,.md,.docx">`.
- `useDropzone({ onDrop: addFiles, noClick: true, maxFiles: 5 })` sobre el contenedor del composer; estado `isDragActive` → borde `border-primary/60` y fondo `bg-primary/5`.
- `AttachmentChips` encima del textarea.
- `onSubmit` del composer gana `attachmentIds: string[]` (los `ready`); deshabilitar enviar si `uploading` o hay `failed` sin resolver.
- Aviso de visión: si hay imágenes `ready` y el modelo actual no está en `VISION_MODELS` (`['deepseek-v4-flash-vision-exp','gpt-','gemini-','claude-','grok-']`), mostrar `p` pequeño `text-muted-foreground`: "El modelo seleccionado podría no soportar imágenes."

- [ ] **Step 4: Cableado en páginas**

- `thread.tsx`/`chat.tsx`: `const upload = useAttachmentUpload(thread?.id ?? null)`; pasar a `Composer`; en `submit` incluir `attachment_ids: upload.readyIds()`; tras envío exitoso, limpiar chips de imagen (los documentos del hilo permanecen listados bajo el composer como "Documentos del hilo" — lista compacta con estado de indexado y borrado).
- `UserMessage`: si `message.attachments.length > 0`, thumbnails/chips encima del texto.

- [ ] **Step 5: Verificar**

Run: `npm run types` → 0 · `npx eslint resources/js --fix` → 0 · `npm run build` → OK
QA manual: arrastrar imagen + txt, ver chips, enviar, ver thumbnails en el mensaje y respuesta usando el documento; borrar documento del hilo.

- [ ] **Step 6: Commit**

```bash
git add resources/js/hooks/use-attachment-upload.ts resources/js/components/ai/chat/AttachmentChips.tsx resources/js/components/ai/chat/Composer.tsx resources/js/components/ai/chat/UserMessage.tsx resources/js/pages/ai/thread.tsx resources/js/pages/ai/chat.tsx resources/js/types/chat.ts
git commit -m "feat(ai): add attachment composer, chips and message previews"
```

---

### Task 10: F3a — Aprobaciones de escritura y preguntas (backend)

**Files:**
- Modify: `app/Ai/Tools/ActionTool.php`
- Create: `app/Ai/Tools/AskUserTool.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (registrar `AskUserTool`)
- Modify: `app/Ai/Services/ChatService.php` (`decide()`)
- Modify: `app/Http/Controllers/Ai/ChatController.php` (`approve()`)
- Create: `app/Http/Requests/Ai/ApproveChatTurnRequest.php`
- Modify: `app/Http/Resources/ChatMessageResource.php` (`pending_approvals`)
- Modify: `routes/web.php` (`POST ai/chat/{thread}/approve`)
- Test: `tests/Feature/Ai/ChatApprovalTest.php`

**Interfaces:**
- Consumes: `ActionTool` existente; `Laravel\Ai\Approvals\{Approval,Decision,Decisions}`; `Laravel\Ai\Concerns\InteractsWithApprovals`.
- Produces: ruta `ai.chat.approve`; SSE `tool_approval_request` y reanudación por `Decisions`; `ChatMessageResource.pending_approvals: [{id, tool, arguments, reason, kind}]`.

- [ ] **Step 1: Tests (fallarán)**

```php
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function pausedToolSse(string $tool): string
{
    return implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['tool_calls' => [[
            'index' => 0, 'id' => 'call_1', 'type' => 'function',
            'function' => ['name' => $tool, 'arguments' => '{"action":"create_workout","started_at":"2026-09-25T10:00:00Z"}'],
        ]]], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]),
        'data: [DONE]',
    ])."\n\n";
}

test('write tools pause the turn with a tool approval request', function () {
    Http::fake(['*' => Http::response(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)->toContain('tool_approval_request')->toContain('ActionTool')->toContain('[DONE]');

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->approval_state['pending'])->toHaveKey('call_1');

    $resource = (new \App\Http\Resources\ChatMessageResource($assistant->refresh()))->resolve(request());
    expect($resource['pending_approvals'])->toHaveCount(1);
    expect($resource['pending_approvals'][0]['kind'])->toBe('approval');
    expect($resource['pending_approvals'][0]['tool'])->toBe('ActionTool');
});

test('approving resumes the run and executes the tool', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Listo\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->streamedContent();

    expect($content)->toContain('Listo');

    $assistant = $thread->messages()->where('role', 'assistant')->orderByDesc('id')->first();
    expect($assistant->approval_state)->toBeNull();
    expect($assistant->tool_results)->not->toBeEmpty();
});

test('rejecting records the denial and continues', function () {
    Http::fakeSequence()
        ->push(pausedToolSse('ActionTool'), 200, ['Content-Type' => 'text/event-stream'])
        ->push("data: {\"choices\":[{\"delta\":{\"content\":\"Vale, no lo hago\"},\"finish_reason\":\"stop\"}]}\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'loguea mi workout', 'thread_id' => $thread->id])->streamedContent();

    $content = $this->actingAs($user)
        ->post(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'reject']]])
        ->streamedContent();

    expect($content)->toContain('no lo hago');
});

test('ask user answers travel back as a rejected tool result', function () {
    Http::fake(['*' => Http::response(pausedToolSse('AskUserTool'), 200, ['Content-Type' => 'text/event-stream'])]);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $content = $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'hola', 'thread_id' => $thread->id])
        ->streamedContent();

    expect($content)->toContain('tool_approval_request')->toContain('AskUserTool');
});

test('another user cannot approve a thread', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.approve', $thread), ['decisions' => ['call_1' => ['action' => 'approve']]])
        ->assertForbidden();
});
```

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=ChatApprovalTest` → FAIL.

- [ ] **Step 3: `ActionTool` y `AskUserTool`**

`ActionTool`: `class ActionTool implements Tool, Approvable` + `use InteractsWithApprovals;` y

```php
    public function needsApproval(\Laravel\Ai\Tools\Request $request): \Laravel\Ai\Approvals\Approval|bool
    {
        return Approval::required('Va a ' . ($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus datos') . '.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_workout' => 'crear un entrenamiento',
            'log_meal' => 'registrar una comida',
            'add_purchase' => 'añadir una compra',
            // ... (un label por acción de write existente)
        ];
    }
```

`AskUserTool`:

```php
<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AskUserTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Pregunta al usuario cuando necesites una decisión o dato que no puedes inferir. La conversación se pausa hasta que responda.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->required(),
            'options' => $schema->array()->items($schema->string()),
        ];
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required((string) ($request['question'] ?? 'Necesito una decisión tuya.'));
    }

    public function handle(Request $request): Stringable|string
    {
        // La respuesta del usuario llega como resultado del rechazo (Decision::reject($texto));
        // este handle no se ejecuta en el flujo normal.
        return 'El usuario no respondió.';
    }
}
```

Registrar en `MegalomaniacAgent::tools()` (al final de la lista).

- [ ] **Step 4: Servicio y controller de reanudación**

`ChatService::decide(User $user, ChatThread $thread, Decisions $decisions): StreamableAgentResponse`:

```php
    public function decide(User $user, ChatThread $thread, Decisions $decisions): StreamableAgentResponse
    {
        $this->ensureConfigured($user);
        $this->configureUserProvider($user, $thread->id);

        [$provider, $defaultModel] = AiProviderResolver::for($user, $thread->id);

        return (new MegalomaniacAgent($user, $thread))
            ->continue($thread->id, as: $user)
            ->stream($decisions, provider: $provider, model: $thread->model ?: $defaultModel);
    }
```

`ApproveChatTurnRequest`: `decisions` required array min 1; cada entrada `array` con `action` in `approve,reject,edit`; `result` nullable string max 4000; `arguments` nullable array (solo si `action=edit`); o booleano. Normalizar a `Decision` en el controller:

```php
    public function approve(ApproveChatTurnRequest $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        $decisions = Decisions::from(collect($request->validated('decisions'))
            ->map(function ($decision) {
                if (is_bool($decision)) {
                    return $decision;
                }

                return match ($decision['action']) {
                    'approve' => Decision::approve(),
                    'edit' => Decision::edit($decision['arguments'] ?? []),
                    default => Decision::reject($decision['result'] ?? null),
                };
            })
            ->all());

        try {
            $stream = $this->service->decide($request->user(), $thread, $decisions);
        } catch (ApprovalMismatchException|ApprovalNotResumableException $exception) {
            abort(422, $exception->getMessage());
        }

        return $this->streamResponse($stream, $thread);
    }
```

Ruta: `Route::post('ai/chat/{thread}/approve', [ChatController::class, 'approve'])->middleware('throttle:30,1')->name('ai.chat.approve');` + `wayfinder:generate`.

- [ ] **Step 5: `pending_approvals` en el resource**

```php
            'pending_approvals' => collect(($this->approval_state['pending'] ?? []))
                ->map(function (string $reason, string $toolCallId) {
                    $call = collect($this->tool_calls ?? [])->firstWhere('id', $toolCallId);
                    $tool = data_get($call, 'name') ?? data_get($call, 'function.name') ?? 'tool';

                    return [
                        'id' => $toolCallId,
                        'tool' => $tool,
                        'arguments' => data_get($call, 'arguments') ?? data_get($call, 'function.arguments') ?? [],
                        'reason' => $reason,
                        'kind' => $tool === 'AskUserTool' ? 'question' : 'approval',
                    ];
                })
                ->values()
                ->all(),
```

- [ ] **Step 6: Verificar y commit**

Run: `php artisan test --compact --filter=ChatApprovalTest` → 5 passed · `--filter=Chat` → verde
```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/ActionTool.php app/Ai/Tools/AskUserTool.php app/Ai/Agents/MegalomaniacAgent.php app/Ai/Services/ChatService.php app/Http/Controllers/Ai/ChatController.php app/Http/Requests/Ai/ApproveChatTurnRequest.php app/Http/Resources/ChatMessageResource.php routes/web.php tests/Feature/Ai/ChatApprovalTest.php
git commit -m "feat(ai): require approval for writes and support interactive questions"
```

---

### Task 11: F3b — Tarjetas de aprobación y pregunta (UI)

**Files:**
- Modify: `resources/js/lib/chat-sse.ts`
- Modify: `resources/js/hooks/use-chat-stream.ts`
- Create: `resources/js/components/ai/chat/ApprovalCard.tsx` (variantes `approval` y `question`)
- Modify: `resources/js/components/ai/chat/AssistantMessage.tsx`, `MessageList.tsx`
- Modify: `resources/js/pages/ai/thread.tsx`
- Modify: `resources/js/types/chat.ts`
- Test: manual + types/build

**Interfaces:**
- Consumes: SSE `tool_approval_request`, resource `pending_approvals`, endpoint `ai.chat.approve`.
- Produces: `useChatStream` estado `awaiting_approval` + `pendingApprovals`; `ApprovalCard({approval, onDecide, disabled})`.

- [ ] **Step 1: Parser y hook**

- `StreamEvent` gana `approvals?: ApprovalPayload[]`; `ChatStreamHandlers` gana `onApprovalRequest?: (approvals: ApprovalPayload[]) => void`; `dispatch`:

```ts
        case 'tool_approval_request':
            if (Array.isArray(event.approvals)) handlers.onApprovalRequest?.(event.approvals);
            break;
```

- Hook: `status` gana `'awaiting_approval'`; estado `pendingApprovals: ApprovalPayload[]`; en `onApprovalRequest` → `setPendingApprovals(...)` + `setStatus('awaiting_approval')` y marcar `awaitingApprovalRef = true`; en el cierre del stream, si `awaitingApprovalRef` → **no** llamar `onComplete` (evita el reload que borraría la tarjeta) y limpiar el flag. `reset()` limpia `pendingApprovals`.

- [ ] **Step 2: ApprovalCard**

```tsx
interface ApprovalCardProps {
    approval: PendingApproval;
    disabled?: boolean;
    onDecide: (id: string, decision: 'approve' | 'reject' | 'edit', payload?: { result?: string; arguments?: Record<string, unknown> }) => void;
}
```

- `kind === 'question'`: icono `MessageCircleQuestion`, texto `approval.reason` (la pregunta), chips con `arguments.options` (si vienen) que rellenan el textarea, `Textarea` + botones `Responder` (envía `reject` con `result=texto`) y `Saltar` (`reject` sin resultado).
- `kind === 'approval'`: mapa `HUMAN_LABELS[tool]` para una frase (`ActionTool` → usa `arguments.action` con `ACTION_LABELS`; fallback `tool`), `reason` como subtítulo, argumentos en `<pre className="max-h-40 overflow-auto text-xs">` dentro de `Collapsible` (colapsado por defecto), botones `Aprobar` (primary), `Editar` (abre textarea JSON con `JSON.parse` validado; envía `edit` con `arguments`), `Denegar` (ghost/`text-destructive`).
- Botón `Aprobar todo` a nivel de lista cuando hay >1 approval (envía `approve` para cada id pendiente).

- [ ] **Step 3: Cableado**

- `AssistantMessage` gana `pendingApprovals?: PendingApproval[]` y `onDecide?`; renderiza `<ApprovalCard>` bajo el contenido (también en el bubble live).
- `MessageList` pasa `liveApprovals`/`onDecide` al bubble live y `message.pending_approvals` (persistidos) al último assistant.
- `thread.tsx`: `decide(id, action, payload)` construye el body `{decisions: {[id]: {action, result, arguments}}}` y llama `stream.start(ChatController.approve.url(thread.id), body)`; al `onComplete` → reload de `threads`+`messages` como hoy. Composer deshabilitado mientras `awaiting_approval` (salvo la tarjeta de pregunta, que tiene su propio textarea).
- Al recargar: tarjetas desde `pending_approvals` (mismo componente; `onDecide` idéntico).

- [ ] **Step 4: Verificar**

Run: `npm run types` → 0 · `npx eslint resources/js --fix` → 0 · `npm run build` → OK
QA manual: pedir "loguea un workout" → tarjeta → Aprobar (se ejecuta y persiste) / Denegar / Editar args; "pregúntame algo" → tarjeta pregunta → Responder y Saltar; recargar y ver tarjetas pendientes.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/chat-sse.ts resources/js/hooks/use-chat-stream.ts resources/js/components/ai/chat/ApprovalCard.tsx resources/js/components/ai/chat/AssistantMessage.tsx resources/js/components/ai/chat/MessageList.tsx resources/js/pages/ai/thread.tsx resources/js/types/chat.ts
git commit -m "feat(ai): add approval and question cards to chat"
```

---

### Task 12: QA final y review de rama

**Files:**
- Modify: `docs/qa/playwright-report.md` (sección nueva)

- [ ] **Step 1: Suite y estáticos**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan wayfinder:generate --with-form
npm run types && npm run build
```

Expected: suite verde salvo los 5 fallos pre-existentes de Grocery/Nutrition/Supplement; tipos y build OK.

- [ ] **Step 2: QA Playwright (`:8010`, `test@example.com/password` o la cuenta del usuario)**

1. Generación larga (>60s con fake lento) ya no se corta; el panel "Pensando…" muestra el razonamiento y se colapsa al llegar la respuesta; al recargar sigue el bloque "Pensó durante Xs".
2. Adjuntar imagen → chip/thumbnail, aviso de visión si aplica; el mensaje muestra la imagen; respuesta coherente con un modelo vision.
3. Adjuntar txt/docx → "Indexando…" → ✓; preguntar algo del documento → la respuesta lo usa; recargar y repetir pregunta → sigue disponible; borrar documento.
4. "Loguea un workout" → tarjeta; Aprobar ejecuta y persiste; Denegar no ejecuta; Editar cambia un argumento antes de ejecutar; recargar con pendiente → tarjeta reconstruida.
5. "Pregúntame algo" → tarjeta de pregunta; Responder continúa el turno usando la respuesta; Saltar continúa sin ella.
6. Cortar el fake a mitad → mensaje humanizado + Reintentar.

- [ ] **Step 3: Documentar y commit**

Añadir sección `## Chat enriquecido (F0–F3, 2026-09-25)` al reporte con resultados e incidencias.

```bash
git add docs/qa/playwright-report.md
git commit -m "docs(qa): record chat enrichment verification"
```

- [ ] **Step 4: Review final de rama + PR**

Review scoped por fase (como Spec A) y review final de la rama completa; push a `feat/ai-chat-core` (PR #1 se actualiza). Reportar rulnings y pendientes diferidos.

---

## Cobertura del spec

| Requisito | Task |
|---|---|
| F0 nginx + mensaje de corte | T1 |
| F1 driver de razonamiento | T2 |
| F1 persistencia + panel de pensamiento | T3 |
| F2a tablas + FTS5 | T4 |
| F2b extracción + indexado | T5 |
| F2c subida/descarga/borrado | T6 |
| F2d envío de imágenes | T7 |
| F2e inyección de documentos del hilo | T8 |
| F2f UI de adjuntos | T9 |
| F3a approvals + preguntas (backend) | T10 |
| F3b tarjetas (UI) | T11 |
| QA + review final | T12 |



