# Chat enriquecido: razonamiento, adjuntos y confirmaciones — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Alcance: Fases F0–F3 sobre el chat de Spec A (`feat/ai-chat-core`)

## Contexto y problema

1. **Cortes de generación**: la nginx del contenedor (`/opt/dev/nginx/conf.d/megalomaniac.conf`) usa el `fastcgi_read_timeout` por defecto (60s) y buffering activo. Además, el gateway `openai-compatible` de laravel/ai v0.11 **no parsea `reasoning_content`**, así que durante la fase de razonamiento de `deepseek-v4.1-flash` (vía OpenCode Go) el SSE queda mudo; al superar 60s nginx corta (`upstream timed out`, evidencia en `docker logs dev-nginx` 2026-09-25 21:34:04), Firefox falla la lectura del body con `Error in input stream` y la UI muestra ese texto crudo. No hay excepción PHP (nada en logs) y el turno no se persiste.
2. **Fase de pensamiento invisible**: el usuario quiere ver el razonamiento según llega y poder releerlo después.
3. **Sin adjuntos**: se quieren imágenes (visión) y documentos (txt/md/docx) que el modelo pueda consultar; los documentos quedan ligados al hilo y se recuperan por FTS5.
4. **La IA escribe sin permiso**: herramientas como `ActionTool` crean/actualizan datos. Se quiere aprobación explícita en escrituras y que el agente pueda hacer preguntas interactivas.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Razonamiento: driver propio `reasoning-compatible` (subclase de gateway que sí parsea `reasoning_content`), **persistido** en `meta.reasoning` del mensaje assistant. |
| 2 | Adjuntos: **imágenes** (visión, por mensaje) y **documentos txt/md/docx** (ligados al **hilo**, retrieval FTS5). **Sin PDF** en esta fase (queda para Spec B). **Sin dependencias nuevas.** |
| 3 | Documentos ligados al hilo: una vez adjuntados quedan disponibles para todos los turnos; en cada turno se inyectan los fragmentos más relevantes. |
| 4 | Aprobaciones: **todas las tools de escritura piden permiso siempre** (lecturas no); sin settings por ahora. |
| 5 | Preguntas interactivas: `AskUserTool` que pausa vía approvals; la respuesta libre viaja al modelo como `Decision::reject($texto)`. |
| 6 | UI: panel de pensamiento colapsable con auto-colapso; chips/thumbnails con drag&drop; tarjetas Aprobar/Editar/Denegar y Responder/Saltar. |
| 7 | Rama: se amplía `feat/ai-chat-core` (PR #1), con checkpoint del WIP local ya commiteado (`f5574bd`). |
| 8 | ui-radar: catálogo libre de UIZZE consultado 4 veces sin resultados → sin referencias externas; se sigue el design system Ember y convenciones de agentes (ChatGPT/Claude) sin copiar branding. |

## Evidencia técnica (laravel/ai v0.11) — ganchos verificados

- `Laravel\Ai\Providers\Concerns\HasTextGateway::useTextGateway(StepTextGateway)` permite inyectar un gateway propio; `OpenAiCompatibleProvider::textGateway()` construye `OpenAiCompatibleGateway`.
- Los providers se crean por driver en `AiManager` (público `createOpenaiCompatibleDriver`); `Ai::extend($driver, $callback)` de `MultipleInstanceManager` permite registrar un driver propio (p. ej. `reasoning-compatible`) sin tocar vendor.
- `Laravel\Ai\Prompts\AgentPrompt::{append,prepend,revise}()` devuelven **copias** → un middleware puede inyectar contexto sin contaminar el prompt que `RememberConversation` persiste.
- Contrato `HasMiddleware` (`middleware(): array`) en agentes; middleware corre entre `RememberConversation` y el gateway.
- `Gateway/OpenAiCompatible/Concerns/HandlesTextStreaming` parsea SSE y emite `TextDelta`; ignora `delta.reasoning_content`. Existen eventos `ReasoningStart/ReasoningDelta/ReasoningEnd` y `StreamableAgentResponse` los serializa igual que el resto.
- Approvals: interfaz `Contracts\Approvable` (`requireApproval/withoutApproval/shouldRequestApproval`) + trait `Concerns\InteractsWithApprovals` (hook `needsApproval(Request)`). El agente solo necesita ser `Conversational` para ser resumible (`GeneratesText::agentCanResumeApprovals`). `ToolApprovalRequest` emite `{approvals: [{id, tool, arguments, reason}]}`. `Approvals\Decision` soporta `approve()`, `reject(?string $result)` y `edit(array $arguments)`; `Decisions::from([...])`; `stream(Decisions|string $prompt, ...)` reanuda.
- Persistencia de pausa: `approval_state = {"pending": {toolCallId: reason}}` en el mensaje assistant + `tool_calls` con las llamadas pendientes (permite reconstruir la tarjeta al recargar).
- Attachments: `Promptable::stream($prompt, array $attachments, ...)`; `Gateway/OpenAiCompatible/Concerns/MapsAttachments` mapea **solo imágenes** (`Base64Image|RemoteImage|LocalImage|StoredImage|UploadedFile` imagen → `image_url`) y lanza `InvalidArgumentException` para cualquier otro tipo. El SDK persiste `attachments` en la fila del mensaje.
- SQLite con **FTS5 disponible** (verificado con `pragma compile_options`), tanto en runtime como en tests `:memory:`. No hay binario `pdftotext` ni librería de extracción instalada.

## F0 — Hotfix de cortes

### nginx (infra, `/opt/dev/nginx/conf.d/megalomaniac.conf`)
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
Recarga: `docker exec dev-nginx nginx -s reload`. Copia de referencia versionada en `docker/nginx/conf.d/megalomaniac.conf` (el compose monta `/opt/dev/nginx/conf.d`; el archivo del repo documenta el cambio).

### Cliente
`resources/js/lib/chat-sse.ts`: envolver el bucle de lectura para que un corte de transporte (Firefox: "Error in input stream"; Chrome: "network error") se convierta en `La conexión con la IA se interrumpió antes de terminar. Reinténtalo.` sin tocar la semántica de `AbortError` (stop).

## F1 — Razonamiento en vivo y persistido

### Driver propio
- `App\Ai\Gateway\ReasoningOpenAiCompatibleGateway extends OpenAiCompatibleGateway`: sobreescribe el parseo SSE del trait `HandlesTextStreaming` para, además del comportamiento actual, detectar `delta.reasoning_content` y emitir `ReasoningStart` (una vez) → `ReasoningDelta` por fragmento → `ReasoningEnd` al cambiar a `delta.content` o al terminar. Mantiene `TextDelta`, `ToolCall`, `Error`, `StreamStart/End` idénticos.
- `App\Ai\Providers\ReasoningOpenAiCompatibleProvider extends OpenAiCompatibleProvider`: `#[Override] public function textGateway(): StepTextGateway { return $this->textGateway ??= new ReasoningOpenAiCompatibleGateway($this->events); }` (usa `HasTextGateway`).
- Registro en `App\Providers\AiChatServiceProvider` (nuevo, registrado en `bootstrap/providers.php`):
  ```php
  Ai::extend('reasoning-compatible', fn ($app, array $config) => new ReasoningOpenAiCompatibleProvider($config, $app['events']));
  ```
- `config/ai.php`: el provider `'user'` pasa a `'driver' => 'reasoning-compatible'` (mismos `url`/`key` null + escritura runtime). El driver vendor `openai-compatible` queda intacto para usos server-side.
- Riesgo: el gateway copia ~100 líneas del parser del SDK v0.11. Mitigación: test de contrato (`ReasoningGatewayTest`) que falla si el formato SSE del SDK cambia, y PR upstream propuesto al final.

### Persistencia
- `ChatController::streamResponse` recopila `reasoning_delta` (texto) y marca el inicio para calcular `duration_ms`.
- Al terminar la iteración (los `then()` del SDK ya persistieron el assistant), actualiza el **último mensaje assistant del hilo**: `meta = [...meta, 'reasoning' => ['text' => ..., 'duration_ms' => ...]]`. Solo si hay texto de razonamiento.
- `ChatMessageResource` expone `reasoning: {text, duration_ms}|null`.

### UI
- `resources/js/components/ai/chat/ReasoningPanel.tsx` (nuevo): `Collapsible` (componente existente), cabecera `Brain` + `Pensando…` en vivo / `Pensó durante Xs` al terminar, chevron; contenido `text-sm text-muted-foreground` con `max-h-52 overflow-auto` y fade inferior.
- Auto-colapso: se expande mientras `streaming && text === ''`; al llegar el primer `text_delta` se colapsa (una vez).
- `useChatStream`: acumula `reasoning` (string) y `reasoningStartedAt`; expone `reasoning` y `reasoningMs`. Parser: `reasoning_delta` → acumula; `reasoning_start/end` → estado.
- `AssistantMessage` renderiza `<ReasoningPanel>` con `reasoning` (live) o `message.reasoning` (persistido). Types en `@/types/chat`.

## F2 — Adjuntos (imágenes + documentos txt/md/docx)

### Datos
Migración `create_chat_attachments_and_document_chunks_tables`:
```
chat_attachments:
  id (uuid7, pk), user_id (FK cascade), thread_id (FK agent_conversations nullable, cascade),
  message_id (string 36 nullable, index), kind (string 10: image|document),
  disk (string 30), path (string 500), original_name (string 255), mime (string 100),
  size (unsigned int), status (string 12: ready|pending|indexed|failed, default ready),
  error (text nullable), timestamps, index (user_id, thread_id)

chat_document_chunks:
  id (pk), attachment_id (FK cascade), position (unsigned int), content (text),
  index (attachment_id, position)
```
FTS5 (migración propia, `DB::statement`):
```sql
CREATE VIRTUAL TABLE chat_document_chunks_fts USING fts5(content, content='chat_document_chunks', content_rowid='id', tokenize='unicode61 remove_diacritics 2');
-- triggers INSERT/UPDATE/DELETE para sincronizar el índice
```

### Modelos y extracción
- `App\Models\ChatAttachment` (`casts()`, relaciones `thread()`, `chunks()`, scopes `images/documents`, helper `isIndexed()`), `App\Models\ChatDocumentChunk`.
- `App\Ai\Documents\TextExtractor` (txt/md: `Storage::get`, valida UTF-8 con `mb_check_encoding`), `App\Ai\Documents\DocxExtractor` (ZipArchive → `word/document.xml` → strip tags/entities, sin deps), `App\Ai\Documents\ExtractorFactory` (mime/ext → extractor; `null` para no soportado → `status=failed`).
- `App\Ai\Documents\DocumentIndexer`: extrae, normaliza, chunkea ~1000 chars con solape de 200 respetando párrafos/frases, guarda chunks y sincroniza FTS (insert en `chat_document_chunks_fts` con `rowid`).
- Job `App\Jobs\IndexChatDocument(attachmentId)` en cola `database` (ya configurada): extrae/indexa, actualiza `status`. Idempotente (borra chunks previos antes de reindexar).

### Endpoints
- `POST ai/chat/attachments` (multipart): valida `file` (`mimes:jpg,jpeg,png,webp,txt,md,docx,vnd.openxmlformats-officedocument.wordprocessingml.document`, max 25MB; imágenes max 10MB), `thread_id` (nullable, propia); guarda en `local` (`ai-attachments/{user}/{uuid}.{ext}`); imagen → `status=ready`; documento → `status=pending` + despacha `IndexChatDocument`; responde `ChatAttachmentResource`.
- `GET ai/chat/attachments/{attachment}`: sirve el archivo con `$this->authorize('view', $attachment)` (StreamedResponse, `Content-Disposition: inline`).
- `DELETE ai/chat/attachments/{attachment}`: elimina fila+archivo (y chunks por cascade); solo si no está ligado a un mensaje enviado o si es documento del hilo.
- `POST ai/chat` (send/edit/regenerate) gana `attachment_ids: [uuid]` (máx 5, imágenes `ready` del usuario y del hilo o sin hilo): al construir el turno se mapean a `StoredImage::fromStorage($disk, $path)` y se pasan a `stream($mensaje, attachments: [...])`; los ids se ligan al mensaje del usuario tras persistir (post-stream: `ChatAttachment::whereIn('id', $ids)->update(['message_id' => $userMessageId])`).
- `ChatService::streamTurn(..., ?array $attachmentIds = null)`.

### Inyección de documentos (middleware)
- `App\Ai\Middleware\InjectThreadDocumentContext(?ChatThread $thread, string $query)`: si el hilo no tiene documentos indexados, no hace nada; si sí, busca top-6 chunks con FTS5 `MATCH` (query = últimos ~200 chars del mensaje, saneada a términos con `OR` + prefijos) y hace `$next($prompt->append($bloque))`, donde el bloque lleva el delimitador `--- Documentos del hilo (contexto) ---` + nombre del documento + fragmento. **No muta `$prompt` original** (append devuelve copia) → el mensaje persistido queda limpio.
- `MegalomaniacAgent` gana un constructor `?ChatThread $thread = null` y `implements HasMiddleware`; `middleware()` devuelve `[new InjectThreadDocumentContext($this->thread, ...)]` solo si hay hilo. `ChatService` construye el agente con el hilo.
- Nunca se indexan ni se inyectan archivos de otros usuarios (scoping por `user_id`/`thread_id`).

### UI
- `Composer`: botón clip + drag&drop (react-dropzone) sobre el contenedor; subida inmediata al soltar/seleccionar; chips con thumbnail (imágenes) o icono+nombre+tamaño (documentos); spinner "Indexando…" → ✓ / error con reintentar; máx 5 por mensaje. Bloquea envío si hay subidas en curso.
- `UserMessage`: thumbnails (clic → `Dialog` con la imagen completa) y chips de documento (nombre + tamaño).
- Aviso no bloqueante si se adjunta imagen y el modelo configurado no está en una lista local de modelos con visión (`deepseek-v4-flash-vision-exp`, `gpt-*`, `gemini-*`, `claude-*`, `grok-*`).

## F3 — Aprobaciones y preguntas

### Backend
- `ActionTool implements Approvable` con `use InteractsWithApprovals` y `needsApproval(Request $request): Approval|bool` → **siempre** `Approval::required('Va a ' . $accionHumana)` (mapa acción→frase en español; fallback genérico).
- `App\Ai\Tools\AskUserTool implements Approvable`: schema `{question: string, options?: array<string>}`; `needsApproval()` → `Approval::required($question)`; `handle()` devuelve `'El usuario no respondió.'` (nunca se ejecuta en el flujo normal: la respuesta llega como `reject(result)`).
- Agente: registrar `AskUserTool` en `tools()`; no necesita trait extra (ya es `Conversational`).
- SSE: `tool_approval_request` con `{approvals: [{id, tool, arguments, reason}]}`; al pausar, el SDK persiste `approval_state` + `tool_calls` y el controller cierra con `[DONE]`.
- `POST ai/chat/{thread}/approve`: valida `decisions: {toolCallId: {action: approve|reject|edit, result?: string, arguments?: object}}` (o `true/false`), `authorize('update')`, `Decisions::from(...)`, y `ChatService::decide(User, ChatThread, Decisions)` → `$agent->continue($thread->id, as: $user)->stream($decisions)` → mismo envoltorio SSE. Errores del SDK (`ApprovalMismatchException`, `ApprovalNotResumableException`) → 422 con mensaje claro.
- `ChatMessageResource` gana `pending_approvals: [{id, tool, arguments, reason, kind}]` derivado de `approval_state.pending` + `tool_calls` (kind `question` si `tool === 'AskUserTool'`).

### UI
- Parser: `tool_approval_request` → handler `onApprovalRequest(approvals)`; hook gana estado `awaiting_approval` con `pendingApprovals`; `onComplete` NO se dispara cuando el stream cierra con aprobaciones pendientes (evita reload que borraría la tarjeta).
- `ApprovalCard.tsx`: por cada approval, frase humana + argumentos (mapa `tool`→render; fallback `<pre>` JSON colapsable); botones Aprobar / Editar (JSON editable en `Textarea` con validación) / Denegar; `Aprobar todo` si >1. `QuestionCard.tsx` (o variante `kind=question` en el mismo componente): pregunta + chips de `options` (clic rellena el textarea) + `Responder` / `Saltar`.
- Decidir → `POST approve` → `stream.start(approveUrl, {decisions})` (mismo SSE) → al completar, reload de `messages`.
- Reconstrucción al recargar: tarjetas desde `pending_approvals` del último mensaje; mismos handlers.

## Contrato SSE ampliado

| Evento | Campos | Consumidor |
|---|---|---|
| `reasoning_start` | `reasoning_id`, `timestamp` | hook (estado "pensando") |
| `reasoning_delta` | `reasoning_id`, `delta`, `summary` | acumula texto |
| `reasoning_end` | `reasoning_id`, `summary` | cierra fase |
| `tool_approval_request` | `approvals: [{id, tool, arguments, reason}]` | tarjetas |
| (resto igual que Spec A) | | |

Cierre con `[DONE]` siempre, incluido el caso pausado.

## Seguridad

- Autorización en subida/descarga/borrado de adjuntos (policy `ChatAttachmentPolicy`: propietario) y scoping por hilo/usuario.
- Extracción y FTS solo de documentos del propio hilo del usuario; el middleware filtra por `thread_id` y `user_id`.
- Límites de tamaño/tipo/número de adjuntos; nombres saneados; nunca se sirve un archivo por ruta del cliente.
- El contenido de documentos y razonamiento no se loguea.
- `AskUserTool` no ejecuta nada; la respuesta se persiste como tool result (parte del contexto del hilo).

## Testing

- **F0**: verificación manual (generación >60s con proveedor fake lento) + `types/build`.
- **F1**: `ReasoningGatewayTest` (unit: SSE fake con `reasoning_content` → eventos `reasoning_*` + `text_delta`), test de persistencia `meta.reasoning` en `ChatStreamTest`, `types`.
- **F2**: `ChatAttachmentUploadTest` (validaciones, límites, auth), `DocumentIndexingTest` (txt/md/docx + FTS5 + idempotencia), `DocumentRetrievalTest` (FTS devuelve chunks relevantes; middleware inyecta solo del hilo propio), test "el mensaje persistido no contiene el bloque de contexto", `ChatAttachmentServeTest`.
- **F3**: `ChatApprovalTest` (pausa con `tool_approval_request` + `approval_state` persistido; resume `approve`/`reject`/`edit`/respuesta de `AskUserTool`; 422 en mismatch; autorización), test de `pending_approvals` en el resource.
- Por fase: `php artisan test --compact --filter=Chat`, `vendor/bin/pint`, `npm run types`, eslint scoped, `npm run build`, y QA Playwright (cortes largos, panel de pensamiento, drag&drop, indexado, aprobar/denegar/preguntar).

## Riesgos y mitigaciones

- **Acoplamiento al parser del SDK** (F1): test de contrato + comentario de versión + PR upstream propuesto.
- **FTS5 en tests**: verificado disponible; el plan incluye un test que crea la tabla virtual en `:memory:`.
- **docx variado**: si el XML no trae texto, `status=failed` visible con reintento/eliminar.
- **Tokens de contexto**: los documentos inyectan top-6 chunks (~6k chars); límite duro y truncado documentado.
- **UX de aprobaciones**: pausa en cada escritura puede cansar; se acepta por decisión #4 (settings queda para después).
- **Cortes de proveedor**: F0 los hace visibles y recuperables, no los elimina; el botón Reintentar ya existe.

## Fuera de alcance

PDF/OCR, embeddings/RAG vectorial, biblioteca de documentos por espacio (Spec B/C), settings de auto-aprobación por tool, streaming de razonamiento por SSE con resumen del proveedor, PR upstream efectivo, multi-archivo por mensaje >5.
