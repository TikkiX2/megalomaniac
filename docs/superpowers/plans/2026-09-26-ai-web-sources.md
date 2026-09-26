# Spec B — Fuentes web, citaciones y biblioteca de fuentes — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir al chat búsqueda web y lectura de páginas con Tavily, citaciones en vivo y persistidas con snippet, gobierno de fuentes por hilo (web/local/ambos/off, opción "Buscar siempre") y una biblioteca de fuentes reutilizable entre hilos (pivote N:N).

**Architecture:** `TavilyClient` centraliza HTTP+errores; dos tools nuevas (`WebSearchTool`, `WebFetchTool`) en un grupo `web` del `ToolCatalog`; el modo del hilo (`agent_conversations.mode`) filtra el grupo `web` y el middleware `InjectThreadDocumentContext`; "Buscar siempre" pre-busca e inyecta contexto con un middleware dedicado; el controlador emite frames `citation` desde los `tool_result` y persiste `meta.citations` con snippet; la biblioteca mueve los documentos a un modelo con pivote `chat_thread_sources`, migrando los `thread_id` existentes.

**Tech Stack:** PHP 8.4 · Laravel 12 · laravel/ai v0.11 · Inertia v2 · React 19 · Tailwind v4 · SQLite FTS5 · Tavily API (search+extract) · Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-26-ai-web-sources-design.md` (se escribe al ejecutar).

## Decisiones aprobadas (resumen del spec)

1. Alcance: web (búsqueda + fetch + citas + modo) **y** biblioteca N:N.
2. Tavily `/search` y `/extract` (Bearer `tvly-…`), base URL configurable (`services.tavily.url`).
3. Key: `users.tavily_api_key` cifrada (Settings → IA) + fallback `TAVILY_API_KEY`.
4. Biblioteca: pivote `chat_thread_sources`; backfill desde `thread_id`; borrar hilo detacha fuentes y solo borra sus imágenes.
5. Modo por hilo `web|local|both|off` (default `both`), gobierna tools web y middleware de docs.
6. Disparo: tool decide + "Buscar siempre" por turno (`force_web`).
7. Citas: `meta.citations` `{url,title,snippet}` + frames SSE `citation` en vivo desde `tool_result`.
8. UI: menú Fuentes en composer, snippets/favicon en panel, "Fuentes adjuntas" del hilo, página `/ai/sources`.

## Global Constraints

- PHP 8.4, Laravel 12, Pest 4; tests `php artisan test --compact --filter=<name>`.
- `vendor/bin/pint --dirty --format agent` antes de cerrar cada task PHP.
- Frontend: `npm run types`, `npx eslint <archivos> --fix`, `npm run build`.
- Wayfinder: `php artisan wayfinder:generate --with-form` tras cambiar rutas (salida gitignored, no stagear).
- Convenciones: Eloquent `casts()`, Form Requests, `$this->authorize` + policies, sin `DB::` salvo migraciones/FTS/transacciones, tokens Ember (sin hex nuevos), sin `dangerouslySetInnerHTML`.
- Tools NUNCA lanzan excepción al stream: devuelven `{"error": "…"}` como resultado.
- Tavily: `search_depth=basic`, `max_results ≤ 8`; `urls ≤ 5` por extract; HTTP timeout 15s (search) / 20s (extract); truncado 15k/página; snippet 160 chars.
- Sin logs de contenido de chat, documentos ni resultados web.
- Trabajar sobre `main` y pushear por task (preferencia del usuario ya establecida); commits `feat(ai): …` / `fix(ai): …`.
- Stagear solo los archivos de cada task.

---

### Task 1: Biblioteca — pivote, migración y semántica de borrado

**Files:**
- Create: `database/migrations/<ts>_create_chat_thread_sources_table.php`
- Modify: `app/Models/ChatThread.php` (`sources()`)
- Modify: `app/Models/ChatAttachment.php` (`threads()`)
- Modify: `app/Ai/Services/ChatService.php` (`deleteThread`)
- Test: `tests/Feature/Ai/SourcesLibraryTest.php` (parte 1: pivote + borrado)

**Interfaces:**
- Produces: tabla `chat_thread_sources` (thread_id, attachment_id, unique compuesto) con backfill; `ChatThread::sources(): BelongsToMany<ChatAttachment>`; `ChatAttachment::threads(): BelongsToMany<ChatThread>`.
- Cambia: `deleteThread` borra imágenes del hilo (por `message_id`) y detacha documentos (pivote) sin borrar archivos de biblioteca.

- [ ] **Step 1: Test (fallará)**

```php
test('thread deletion detaches library sources but deletes thread images', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create(['participant_type' => $user->getMorphClass(), 'participant_id' => $user->id]);

    $doc = ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'document', 'status' => 'indexed', 'path' => 'ai-attachments/'.$user->id.'/doc.txt']);
    Storage::disk('local')->put($doc->path, 'contenido');
    $thread->sources()->attach($doc->id);

    $image = ChatAttachment::factory()->create(['user_id' => $user->id, 'kind' => 'image', 'path' => 'ai-attachments/'.$user->id.'/img.png']);
    Storage::disk('local')->put($image->path, 'bytes');
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'id' => '01a0ffff-0000-7000-8000-000000000001']);
    $image->update(['message_id' => '01a0ffff-0000-7000-8000-000000000001']);

    (new ChatService)->deleteThread($thread);

    expect(ChatAttachment::query()->whereKey($doc->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($doc->path);
    expect(DB::table('chat_thread_sources')->where('thread_id', $thread->id)->count())->toBe(0);
    expect(ChatAttachment::query()->whereKey($image->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($image->path);
});

test('migration backfills existing thread documents into the pivot', function () {
    // creado con ThreadDocument helper: se cubre en un test de migración que re-ejecuta el INSERT del backfill sobre un doc con thread_id
    $thread = ChatThread::factory()->create();
    $doc = ChatAttachment::factory()->create(['kind' => 'document', 'thread_id' => $thread->id]);

    // el backfill corre en la migración; aquí se valida el SQL equivalente
    DB::table('chat_thread_sources')->insert(['thread_id' => $thread->id, 'attachment_id' => $doc->id, 'created_at' => now(), 'updated_at' => now()]);

    expect($thread->sources()->count())->toBe(1);
});
```

Nota: para el backfill real, el test de migración se implementa ejecutando el fragmento SQL de la migración en un test separado (`tests/Feature/Ai/SourcesLibraryMigrationTest.php`) que cree la tabla sin el backfill, inserte filas legado y ejecute el INSERT del backfill copiado — o se valida en `migrate:fresh` sobre el esquema (elegir la vía realista y documentarla).

- [ ] **Step 2: Correr (falla)** — `php artisan test --compact --filter=SourcesLibraryTest` → FAIL.

- [ ] **Step 3: Migración + relaciones + deleteThread**

Migración: pivote con FKs cascade y unique; backfill con `DB::statement`/`insertUsing`. Relaciones BelongsToMany (con `withTimestamps()`). `deleteThread`:

```php
DB::transaction(function () use ($thread): void {
    $messageIds = $thread->messages()->pluck('id')->all();

    ChatAttachment::query()
        ->where('kind', 'image')
        ->whereIn('message_id', $messageIds)
        ->get()
        ->each(function (ChatAttachment $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });

    $thread->sources()->detach();
    $thread->messages()->delete();
    $thread->delete();
});
```

- [ ] **Step 4: Verde + pint + commit/push**

`php artisan test --compact --filter=Chat` verde · pint · commit `feat(ai): add sources library pivot and detach semantics` · `git push origin main`.

---

### Task 2: `TavilyClient` + tools web + grupo `web`

**Files:**
- Create: `app/Ai/Web/TavilyClient.php`
- Create: `app/Ai/Tools/WebSearchTool.php`, `app/Ai/Tools/WebFetchTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupo `web` + factory), `config/ai_tools.php` (keywords), `config/services.php` (tavily)
- Test: `tests/Feature/Ai/TavilyClientTest.php`, `tests/Feature/Ai/WebToolsTest.php`

**Interfaces:**
- `TavilyClient::for(User): self` (key propia o config; null si no hay), `search(string $query, array $opts = []): array` → `['results' => [['n','title','url','content','published_date','favicon']], 'query' => string]`; `extract(array $urls, ?string $query = null): array` → `['pages' => [...], 'failed' => [...]]`. Errores HTTP se devuelven como `['error' => 'mensaje']` (nunca excepción).
- `WebSearchTool`/`WebFetchTool` implementan `Tool` con `new WebSearchTool($user)` (patrón del catálogo).
- Grupo `'web' => ['label' => 'Web', 'tools' => [WebSearchTool::class, WebFetchTool::class]]`.

- [ ] **Step 1-2: Tests RED** (`Http::fake` con `services.tavily.url` apuntando a `https://tavily.test`): search parsea y numera; 401/429/432 → `error`; max_results se capea a 8; extract valida `http(s)`, separa `failed`, trunca a 15k; tools devuelven el JSON con la instrucción de citar; `ToolCatalog::toolsFor($user, ['web'])` devuelve ambas.

- [ ] **Step 3: Implementación**

`TavilyClient` (Http facade, `withToken`, timeout, mapeo):

```php
public function search(string $query, array $options = []): array
{
    $response = Http::baseUrl(config('services.tavily.url'))
        ->withToken($this->key)
        ->timeout(15)
        ->post('/search', array_filter([
            'query' => $query,
            'search_depth' => 'basic',
            'max_results' => min(8, max(1, (int) ($options['max_results'] ?? 6))),
            'topic' => $options['topic'] ?? null,
            'time_range' => $options['time_range'] ?? null,
            'include_favicon' => true,
        ], fn ($value) => $value !== null));

    if ($response->failed()) {
        return ['error' => $this->errorMessage($response->status())];
    }

    return ['query' => $query, 'results' => collect($response->json('results', []))
        ->values()->map(fn ($r, $i) => [
            'n' => $i + 1, 'title' => $r['title'] ?? '', 'url' => $r['url'] ?? '',
            'content' => mb_substr((string) ($r['content'] ?? ''), 0, 4000),
            'published_date' => $r['published_date'] ?? null, 'favicon' => $r['favicon'] ?? null,
        ])->all()];
}
```

Descripción de `WebSearchTool` incluye: "Devuelve resultados numerados; cita las fuentes en tu respuesta como [n] usando ese número y nunca inventes URLs."

- [ ] **Step 4: Verde + pint + commit/push** `feat(ai): add tavily web search and fetch tools`.

---

### Task 3: Modo de fuentes por hilo

**Files:**
- Modify: `app/Ai/Services/ChatService.php` (gate del grupo `web` y del middleware), `app/Http/Requests/Ai/UpdateChatThreadRequest.php` (`mode`), `app/Http/Controllers/Ai/ChatController.php` (`update` persiste `mode`), `app/Http/Resources/ChatThreadResource.php` (`mode`)
- Test: `tests/Feature/Ai/SourceModeTest.php`

**Interfaces:**
- `ChatService::sourceMode(?ChatThread): string` (default `both`).
- `mode ∈ web|local|both|off`; `web|off` excluyen el grupo `web`; `local|both` activan `InjectThreadDocumentContext`.

- [ ] **Steps:** tests RED (policy/middleware por modo, PATCH persiste y autoriza, default both) → implementación (en `prepareToolPolicy`: tras `normalizeToolPolicy`, si modo no incluye web y el resultado contiene `web`, quitarlo; el middleware se añade solo si modo incluye local) → verde → pint → commit `feat(ai): add per-thread source mode`.

---

### Task 4: "Buscar siempre" por turno

**Files:**
- Create: `app/Ai/Middleware/InjectWebSearchContext.php`
- Modify: `app/Http/Requests/Ai/SendChatMessageRequest.php` (`force_web` bool), `app/Ai/Services/ChatService.php` (pre-búsqueda + middleware + expone fuentes), `app/Http/Controllers/Ai/ChatController.php` (aviso recoverable en fallo)
- Test: `tests/Feature/Ai/ForceWebSearchTest.php`

**Interfaces:**
- `ChatService::streamTurn(..., bool $forceWeb = false)`; cuando aplica, ejecuta `TavilyClient::search($message)` y añade `InjectWebSearchContext::__construct(array $results)` que hace `$next($prompt->append("--- Resultados web (contexto) ---\n…"))`.
- En fallo: `$this->webWarning = 'menú'` legible por el controlador para emitir `error` recoverable.

- [ ] **Steps:** tests RED (request del proveedor contiene el bloque; mensaje persistido limpio; sin key no busca y avisa; con `force_web` y modo `local` no busca) → implementación → verde → pint → commit `feat(ai): add force-web search per turn`.

---

### Task 5: Citaciones web en vivo y persistidas

**Files:**
- Modify: `app/Http/Controllers/Ai/ChatController.php` (`streamResponse`), `resources/js/types/chat.ts` (`snippet?`)
- Test: `tests/Feature/Ai/WebCitationsTest.php`

**Interfaces:**
- De cada `tool_result` de `WebSearchTool`/`WebFetchTool`: parsea JSON, extrae `{title,url,snippet}` (snippet = `content`/`raw_content` recortado a 160), dedupe por URL con orden de aparición, emite frames `{"type":"citation","citation":{title,url}}` y acumula para `meta.citations` (merge con citas nativas, cada una `{url,title,snippet}`).

- [ ] **Steps:** test RED (stream contiene frames `citation` y `meta.citations` con snippet deduplicado; merge con citas de proveedor) → implementación en `streamResponse` (helper `collectWebCitations(array $event)` + `storeCitations($thread, array $citations)`, guard de mensaje assistant como en `storeReasoning`) → verde → `npm run types`/build (tipo `snippet?`) → commit `feat(ai): stream and persist web citations`.

---

### Task 6: Biblioteca backend — endpoints, adjuntar/detach y retrieval por pivote

**Files:**
- Create: `app/Http/Controllers/Ai/SourcesController.php`
- Modify: `app/Http/Controllers/Ai/ChatAttachmentController.php` (store auto-adjunta por pivote; destroy guardas de biblioteca), `app/Ai/Services/ChatService.php`/`ChatThread::documentContext()` (join por pivote), `app/Http/Controllers/Ai/ChatController.php` (`show` prop `sources`), `routes/web.php`
- Test: `tests/Feature/Ai/SourcesLibraryTest.php` (parte 2), `tests/Feature/Ai/DocumentRetrievalTest.php` (ajustar a pivote)

**Interfaces:**
- `GET /ai/sources` → Inertia `ai/sources` con `documents` (`ChatAttachmentResource` + `threads_count`, `threads` títulos) y `ai`.
- `POST /ai/chat/{thread}/sources {attachment_id}` → attach (422 duplicado/no listo; autoriza hilo y doc).
- `DELETE /ai/chat/{thread}/sources/{attachment}` → detach.
- `DELETE /ai/sources/{attachment}` (o `ai.chat.attachments.destroy` con guard extendida) → borra archivo+row.
- `show` prop `sources` = `$thread->sources()->where('kind','document')->orderByDesc('chat_thread_sources.created_at')->get()`.

- [ ] **Steps:** tests RED (props, attach/detach con autorización y duplicados, borrado cascade, retrieval usa pivote, store auto-adjunta, destroy de doc no ligado a mensaje permitido) → implementación + `wayfinder:generate` → verde → pint → commit `feat(ai): add sources library endpoints and pivot retrieval`.

---

### Task 7: Settings → IA — key de Tavily

**Files:**
- Modify: `app/Http/Controllers/Settings/AiSettingsController.php`, `resources/js/pages/settings/ai.tsx`
- Test: `tests/Feature/Settings/AiSettingsTest.php` (extender)

**Interfaces:**
- `has_tavily_key: bool`; `tavily_api_key` nullable string max 500; blank conserva; tarjeta "Búsqueda web (Tavily)" con placeholder `tvly-…` / `•••• (guardada)` y ayuda (link tavily.com + coste/créditos).

- [ ] **Steps:** tests RED (cifrada en DB, no expuesta, blank conserva) → implementación (controller + página) → verde → types/build → commit `feat(ai): add tavily key to ai settings`.

---

### Task 8: UI — menú "Fuentes" en el composer

**Files:**
- Create: `resources/js/components/ai/chat/SourceModeMenu.tsx`
- Modify: `resources/js/components/ai/chat/Composer.tsx`, `resources/js/pages/ai/thread.tsx`, `resources/js/pages/ai/chat.tsx`, `resources/js/types/chat.ts`

**Interfaces:**
- `SourceModeMenu({ mode, forceWeb, onChangeMode, onChangeForceWeb, hasTavilyKey, disabled })`: dropdown con radios (Web · Mis fuentes · Ambos · Off), separador, checkbox "Buscar siempre (esta pregunta)", y aviso "Sin key de Tavily → Settings" (link) cuando el modo incluye web y `!hasTavilyKey`.
- `thread.tsx`: `onChangeMode` → `router.patch(update, { mode }, { preserveScroll, preserveState })`; `forceWeb` en el body del siguiente send; se resetea tras enviar.
- `chat.tsx` (home): el modo de un hilo nuevo se envía como `mode` en el primer send? — decisión: el modo por defecto es `both`; en home el menú solo permite "Buscar siempre" (el modo se fija al crear el hilo con el default). Documentarlo en el componente.

- [ ] **Steps:** tipos + componente + wiring; `npm run types`/eslint/build; sin tests JS (verificación manual en T11).

---

### Task 9: UI — snippets en Fuentes + panel "Fuentes adjuntas"

**Files:**
- Modify: `resources/js/components/ai/chat/SourcesPanel.tsx` (snippet + favicon), `resources/js/components/ai/chat/MessageList.tsx` (pasa citations con snippet), `resources/js/pages/ai/thread.tsx` (panel de adjuntas con attach/detach)
- Create: `resources/js/components/ai/chat/ThreadSourcesPanel.tsx` (lista del pivote + dialog "Adjuntar de la biblioteca" + subir)

**Interfaces:**
- `SourcesPanel`: si `citation.snippet` existe, mostrarlo (`line-clamp-2`); favicon opcional con `onError` para ocultar.
- `ThreadSourcesPanel({ sources, onAttach, onDetach, uploading })` debajo del composer; dialog con búsqueda client-side sobre las fuentes de la biblioteca (prop `library` de `show` o fetch a `ai.sources`).

- [ ] **Steps:** implementación + types/build.

---

### Task 10: UI — página `/ai/sources`

**Files:**
- Create: `resources/js/pages/ai/sources.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (ítem "Fuentes" bajo Asistente IA), `resources/js/pages/ai/thread.tsx` (link desde el panel)

**Interfaces:**
- Página con dropzone reutilizado, tabla/lista (nombre, tamaño, estado, hilos, descargar, eliminar con confirm), empty state, skeletons; props `documents`, `ai`.

- [ ] **Steps:** implementación + types/eslint/build; `wayfinder:generate` si hay rutas nuevas.

---

### Task 11: QA final + review

- [ ] Suite completa + pint + wayfinder + types + build.
- [ ] Fake local de Tavily (`:9997`) + `TAVILY_URL` para QA Playwright: búsqueda con citas en vivo y persistidas (snippet), modo web/local/off, "Buscar siempre", biblioteca (subir desde /ai/sources, adjuntar a un hilo, detach, borrar hilo y verificar que la fuente sobrevive), sin key → aviso.
- [ ] Documentar en `docs/qa/playwright-report.md` (sección nueva) + commit `docs(qa): record spec b verification`.
- [ ] Review final de rama (SDD) + push.

---

## Cobertura del spec

| Requisito | Task |
|---|---|
| Pivote + backfill + semántica de borrado | T1 |
| Tavily search/extract + tools + grupo web | T2 |
| Modo por hilo web/local/both/off | T3 |
| "Buscar siempre" + aviso recoverable | T4 |
| Citas en vivo + `meta.citations` con snippet | T5 |
| Biblioteca: endpoints, attach/detach, retrieval, deleteThread | T6 (y T1) |
| Settings → IA: Tavily key | T7 |
| Menú Fuentes en composer | T8 |
| Snippets en panel + Fuentes adjuntas | T9 |
| Página `/ai/sources` | T10 |
| QA + review | T11 |
