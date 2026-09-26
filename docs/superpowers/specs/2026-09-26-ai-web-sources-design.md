# Spec B — Fuentes web, citaciones y biblioteca de fuentes — Diseño

> Fecha: 2026-09-26 · Estado: aprobado · Alcance: Spec B sobre el chat (Spec A + enriquecimiento F0–F3, en `main`)

## Contexto

El chat ya tiene: streaming con razonamiento, adjuntos (imágenes + documentos txt/md/docx con FTS5 ligados al hilo), middleware de contexto documental, aprobaciones/preguntas, UI de citas (`[n]` + panel Fuentes) y grupos de tools extensibles (`ToolCatalog` + router por keywords). La columna `agent_conversations.mode` quedó reservada para este spec.

Falta: **búsqueda web real y lectura de páginas** (la búsqueda nativa del SDK no la soporta el proveedor BYO `openai-compatible`), **citaciones web** (hoy el backend nunca produce `meta.citations`), **gobierno de fuentes por hilo** (web/local/ambos/off) y una **biblioteca de documentos reutilizable** entre hilos (hoy los documentos se borran con su hilo).

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Alcance: web (búsqueda + fetch + citas + modo por hilo) **y** biblioteca de fuentes reutilizable (antesala de espacios). |
| 2 | Proveedor: **Tavily** para `/search` y `/extract` (misma API key; pensada para LLMs/RAG). |
| 3 | Key de Tavily: campo cifrado `users.tavily_api_key` en **Settings → IA** + fallback `TAVILY_API_KEY` del servidor; sin ninguna, web deshabilitada con aviso. |
| 4 | Fuentes locales: **biblioteca del usuario + pivote N:N** `chat_thread_sources`; los documentos existentes se migran; borrar un hilo **detacha** fuentes (no las destruye) y borra solo sus imágenes ligadas a mensajes. |
| 5 | Modo por hilo (persistido en `mode`): `web` · `local` · `both` · `off`; default `both` para hilos nuevos. Gobierna tools web y middleware de documentos; los grupos de datos no cambian. |
| 6 | Disparo de búsqueda: la tool decide + opción **"Buscar siempre"** por turno (pre-búsqueda + inyección de contexto). |
| 7 | Citas: persistir en `meta.citations` `{url, title, snippet}` (dedupe por URL, orden de aparición) **y** emitir eventos SSE `citation` en vivo alimentados desde los `tool_result` de las tools web (el parser/UI existentes no cambian). |
| 8 | UI: menú "Fuentes" en el composer (4 modos + Buscar siempre), snippets/favicons en el panel, panel "Fuentes adjuntas" del hilo y página `/ai/sources` de biblioteca. |

## Evidencia técnica (verificada)

- **Tavily Search**: `POST https://api.tavily.com/search`, `Authorization: Bearer tvly-…`, body `{query, search_depth, max_results (≤20), topic, time_range, include_favicon, include_answer, include_raw_content…}`; respuesta `{results: [{title,url,content,score,favicon?,published_date?,id}], response_time, usage, request_id}`; errores 401 (key), 429 (rate, `Retry-After`), 432/433 (límites de plan/paygo), 500.
- **Tavily Extract**: `POST https://api.tavily.com/extract`, `{urls: 1–20, query?, chunks_per_source?, extract_depth, format (markdown|text), timeout 1–60}`; respuesta `{results: [{url, raw_content, favicon?}], failed_results: [{url,error}]}` (HTTP 200 puede traer fallos por URL).
- **Eventos SSE** existentes: el parser ya entiende `citation` (`{citation:{title,url}}`), `tool_call`/`tool_result` (`{tool_id,tool_name,result,…}`), `error` (`{message,recoverable}`); el hook muestra la cita en vivo y el panel Fuentes. Con eventos `citation` propios no hace falta tocar el cliente.
- **Persistencia**: `ChatMessage.meta` (array cast) ya alberga `citations` del proveedor; `remark-citations` + `SourcesPanel` leen `citations` del resource. Añadir `snippet` es aditivo.
- **Grupos**: `ToolCatalog::groups()` + `config/ai_tools.php` (`keywords`, `write_verbs`, `fallback`); `ChatService::prepareToolPolicy` normaliza `{mode: auto|manual, groups}` y `ChatController` lo persiste en `agent_conversations.tools_policy`.
- **Retrieval local**: `ChatThread::documentContext()` ya busca en FTS5 con `status='indexed'` y scoping de usuario; pasa a usar el pivote.
- **Borrado**: el fix final de F3 hace que `deleteThread` borre attachments por `thread_id`; con la biblioteca debe **detachar** documentos y borrar solo imágenes del hilo (cambio de comportamiento explícito en este spec).

## Modelo de datos

- Migración `create_chat_thread_sources_table`:
  ```
  chat_thread_sources:
    id (pk), thread_id (string 36, FK agent_conversations cascade),
    attachment_id (string 36, FK chat_attachments cascade),
    timestamps, unique(thread_id, attachment_id), index(attachment_id)
  ```
  Backfill: `INSERT INTO chat_thread_sources (thread_id, attachment_id) SELECT thread_id, id FROM chat_attachments WHERE kind='document' AND thread_id IS NOT NULL`.
- `users.tavily_api_key` (text, `encrypted`, nullable).
- `config/services.php` gana `'tavily' => ['url' => env('TAVILY_URL', 'https://api.tavily.com'), 'key' => env('TAVILY_API_KEY')]` (URL configurable para tests/QA).
- Relaciones: `ChatThread::sources()` y `ChatAttachment::threads()` (BelongsToMany vía pivote).

## Backend

### Tools web (grupo `web`)

- `App\Ai\Tools\WebSearchTool(User $user)`:
  - schema `{query: string, max_results?: int 1..8, topic?: general|news|finance, time_range?: day|week|month|year}`;
  - resuelve key (`$user->tavily_api_key` o `config('services.tavily.key')`); sin key → resultado `{"error": "…configura tu key de Tavily…"}` (el modelo lo comunica, sin romper el turno);
  - `POST /search` (`search_depth=basic`, `include_favicon=true`);
  - devuelve JSON `{results: [{n, title, url, content, published_date, favicon}], query}` con instrucción embebida en la descripción de la tool: citar `[n]` alineado a los resultados y no inventar URLs;
  - mapea errores: 401 → "key inválida", 429 → "demasiadas peticiones", 432/433 → "límite de tu plan Tavily", otros → mensaje genérico; siempre como resultado de tool (nunca excepción que tumbe el stream).
- `App\Ai\Tools\WebFetchTool(User $user)`:
  - schema `{urls: array 1..5 de string, query?: string}`; valida `http(s)` con `filter_var`;
  - `POST /extract` (`format=markdown`, `extract_depth=basic`, `timeout=20`);
  - devuelve `{pages: [{url, content (truncado 15k chars), favicon}], failed: [{url, error}]}`;
  - mismos mapeos de error.
- `ToolCatalog`: grupo nuevo `'web' => ['label' => 'Web', 'tools' => [WebSearchTool::class, WebFetchTool::class]]` (constructor con `User`, como los demás).
- `config/ai_tools.php`: keywords del grupo (`busca`, `internet`, `web`, `noticias`, `google`, `última hora`, `actualidad`…) y **no** en `fallback` (no buscar por defecto sin intención).

### Modo de fuentes por hilo

- `ChatService`:
  - `sourceMode(ChatThread): string` (default `both` si `mode` null);
  - el modo no toca grupos de datos; solo:
    - `web`/`both` → el grupo `web` está permitido (el router decide dentro de él);
    - `local`/`off` → grupo `web` nunca se añade;
    - `local`/`both` → middleware `InjectThreadDocumentContext` activo;
    - `web`/`off` → sin middleware.
- Endpoint `PATCH /ai/chat/{thread}` ya existe (title/pinned): añadir `mode` nullable in `web|local|both|off` (mismo request) y persistirlo.
- UI: `SourceModeMenu` en el composer lo cambia con `router.patch` (optimista).

### "Buscar siempre"

- `SendChatMessageRequest` gana `force_web: boolean` (default false, por turno).
- En `ChatController::send`, si `force_web` y modo incluye web y hay key: ejecutar búsqueda con el mensaje antes del stream (`TavilyClient`, timeout 15s):
  - éxito → middleware `InjectWebSearchContext` (append a copia del prompt con bloque `--- Resultados web (contexto) ---`) + las fuentes alimentan las citas;
  - fallo → el turno continúa sin contexto y se emite un `error` SSE `recoverable: true` ("No se pudo buscar en la web; respondo sin resultados.") que el cliente muestra durante el turno.
- `App\Ai\Web\TavilyClient::search()/extract()` centraliza HTTP+mapeo de errores para tools, pre-búsqueda y tests (`Http::fake`).

### Citaciones

- En `ChatController::streamResponse`, al recibir `tool_result` con `tool_name ∈ {WebSearchTool, WebFetchTool}`:
  - parsea el JSON del resultado, extrae `{title, url, snippet (content recortado a ~160 chars), favicon}`;
  - dedupe por URL manteniendo el orden de aparición;
  - emite por cada fuente nueva `data: {"type":"citation","citation":{"title":…,"url":…}}` (parser/hook/UI existentes);
  - acumula para persistir al cierre: `meta.citations` = merge (citas del proveedor nativo si las hubiera) + web; cada cita `{url, title, snippet}`, dedupe por URL, con guard de mensaje assistant (mismo patrón que `storeReasoning`).
- `ChatMessageResource.citations` sigue igual; el tipo `Citation` gana `snippet?: string | null` (aditivo).

### Biblioteca de fuentes

- Upload de documentos (`ChatAttachmentController@store`): ya no exige hilo; si viene `thread_id`, crea además la fila del pivote (auto-adjuntar). `chat_attachments.thread_id` queda como legado (no se usa para documentos nuevos).
- Endpoints:
  - `GET /ai/sources` (Inertia `ai/sources`): documentos del usuario (`kind=document`), orden desc, con `threads_count` y `threads` (títulos) para la tabla; props `ai` para el aviso de key.
  - `POST /ai/chat/{thread}/sources` `{attachment_id}` → adjunta (autoriza hilo+propiedad; 422 si ya está o no está `indexed`/`pending`).
  - `DELETE /ai/chat/{thread}/sources/{attachment}` → detach.
  - `DELETE /ai/sources/{attachment}` (o reutilizar `ai.chat.attachments.destroy` con guardas nuevas): borra archivo+row (chunks y pivote por cascade).
- `ChatController@show` props: `sources` = documentos adjuntos del hilo (pivote) con resource.
- Retrieval: `ChatThread::documentContext()` join por `chat_thread_sources` + `chat_attachments` (status indexed, user scoping).
- `ChatService::deleteThread`: dentro de la transacción, borra las **imágenes** cuyo `message_id` pertenezca al hilo (archivo+row) y **detacha** (delete del pivote) los documentos de biblioteca; los documentos y sus archivos sobreviven.

### Settings → IA (Tavily)

- `AiSettingsController@edit/update`: `tavily_api_key` (nullable string max 500), cifrada por el cast del modelo; `has_tavily_key` al frontend; blank conserva la existente. La página `settings/ai.tsx` añade una tarjeta "Búsqueda web (Tavily)" con input password + placeholder `tvly-…`/`•••• (guardada)` y texto de ayuda (link a tavily.com).

## Contrato SSE

Sin eventos nuevos: se reutilizan `citation` (en vivo, desde tool_results web) y `error` con `recoverable: true` (aviso de Buscar siempre fallido). El resto del contrato (Spec A + F0–F3) no cambia.

## Seguridad y coste

- `tavily_api_key` cifrada, nunca expuesta (solo `has_tavily_key`); tampoco se loguea.
- Límites: `max_results ≤ 8`; `urls ≤ 5` por fetch; timeout HTTP 15s (search) / 20s (extract); contenido truncado (15k por página, 160 chars de snippet).
- El fetch lo ejecuta Tavily (no nuestro servidor) → sin SSRF propio; aun así se validan URLs `http(s)` en la tool.
- Scoping: biblioteca y documentos por `user_id`; adjuntar exige propiedad del hilo y del documento; un documento ajeno → 404/422.
- Coste Tavily visible en la ayuda de Settings (1 crédito por búsqueda básica; el free tier ~1000/mes).

## Testing

- `TavilyClientTest`: search/extract con `Http::fake` (URL configurable), parseo, límites y mapeo 401/429/432/433/500→resultado de tool.
- `WebToolsTest`: schemas, validación `http(s)`, respuesta JSON numerada, instrucciones de citación presentes en la descripción, resultado de error sin excepción.
- `SourceModeTest`: policy/middleware según `mode` (web/local/both/off), default `both`, `PATCH` persiste `mode` y autoriza.
- `ForceWebSearchTest`: con `force_web` el request del proveedor contiene "Resultados web (contexto)" y el mensaje persistido queda limpio; sin key o con fallo continúa y emite `error recoverable`.
- `WebCitationsTest`: `tool_result` de `WebSearchTool` produce frames `citation` en el stream y `meta.citations` persistido con snippet deduplicado; merge con citas nativas.
- `SourcesLibraryTest`: backfill de la migración, `GET /ai/sources` props, attach/detach con autorización, borrado con cascade chunks+archivo, `deleteThread` detacha documentos y borra imágenes del hilo, retrieval por pivote (adjunto sí / no adjunto no).
- `AiSettingsTest` (extender): `tavily_api_key` cifrada, no expuesta, blank conserva.
- Frontend: `npm run types`, eslint scoped, build; QA Playwright con fake local de Tavily (`TAVILY_URL=http://127.0.0.1:9997`).

## Riesgos y mitigaciones

- **Calidad de Tavily**: si la extracción devuelve boilerplate, el truncado y el prompt de citación lo mitigan; `extract_depth=advanced` queda como ajuste futuro.
- **Citas del modelo**: el modelo puede no citar `[n]` con exactitud; las instrucciones de la tool lo fuerzan y el panel lista igualmente las fuentes consultadas.
- **Coste/latencia de "Buscar siempre"**: por turno y explícito; default off.
- **Migración del pivote**: backfill cubierto por test; el retrieval cambia en el mismo deploy.
- **Borrado de hilo cambia de semántica** (docs sobreviven): documentado; el panel deja claro que las fuentes son de la biblioteca.

## Fuera de alcance

Crawl/Map de Tavily, imágenes web en respuestas, biblioteca compartida entre usuarios/espacios (Spec C), embeddings/RAG vectorial, búsqueda nativa del proveedor, historial de uso de Tavily, re-ranking propio.
