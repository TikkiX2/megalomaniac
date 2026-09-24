# AI Chat Core (estilo Perplexity) — Diseño

> Fecha: 2026-09-24 · Estado: aprobado · Alcance: Spec A de 3 (A Chat core → B Fuentes+citaciones → C Espacios+Agentes)

## Problema

El módulo de chat actual está incompleto y roto en varios puntos:

1. El sidebar enlaza a `/ai/chat`, pero **esa ruta GET no existe** (solo `POST ai/chat`) → link roto.
2. `ChatPanel.tsx` parsea `event.type === 'text.delta'`, pero el SDK emite **`text_delta`** (guion bajo) → el texto nunca se pinta.
3. `AiChatController@chat` **no pasa provider/model** al agente → usa el provider default (`openai`), ignorando el provider BYO del usuario (`openai-compatible` con URL/key en DB) → falla con cualquier configuración real.
4. El handler `event.type === 'meta'` del panel **nunca se dispara**: el SDK no emite un evento `meta` en el stream; los hilos nuevos no comunican su `conversationId`.
5. `loadConversation()` no carga mensajes (solo limpia estado).
6. No hay página completa, ni búsqueda, ni rename/pin/delete, ni markdown, ni citaciones, ni regenerate/edit.

## Objetivo

Página completa `/ai/chat` con: home con composer centrado, rail de hilos (buscar, agrupar, renombrar, fijar, borrar), vista de conversación con streaming SSE real, markdown + código, chips de citación + panel de fuentes, selector de modelo dinámico, regenerate/edit, y toggle FAB que navega al módulo. Deja el modelo de datos preparado para Specs B (fuentes) y C (espacios/agentes).

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Descomposición en 3 specs: A Chat core → B Fuentes+citaciones → C Espacios+Agentes (este documento cubre A). |
| 2 | "Hilo" = conversación; "sesión" es sinónimo. Una sola entidad persistida. |
| 3 | Superficie: página completa `/ai/chat` + `/ai/chat/{thread}`; el panel flotante se elimina y el FAB navega a `/ai/chat`. |
| 4 | Markdown: `react-markdown` + `remark-gfm` + `rehype-highlight` (deps aprobadas). Sin `dangerouslySetInnerHTML`. |
| 5 | Selector de modelo: lista dinámica desde `GET {provider_url}/models` (cache 5 min) con fallback al modelo de Settings; override por hilo persistido en `agent_conversations.model`. |
| 6 | Arquitectura de persistencia: tablas del SDK (`agent_conversations` + `agent_conversation_messages`) + columnas de extensión + modelos propios (`ChatThread`, `ChatMessage`). |
| 7 | Se incluye fix de seguridad: Settings → IA deja de enviar la API key descifrada al frontend. |
| 8 | Se incluye regenerate y edit/resend; el hilo es lineal (editar trunca desde ese mensaje). |
| 9 | Rail de hilos con búsqueda client-side (hasta 100 hilos), agrupado por fecha, sin secciones placeholder hasta Spec C. |

## Arquitectura

### Persistencia (Enfoque 1 aprobado)

El SDK ya persiste por mensaje: `meta.citations` (proveedor/modelo/citaciones), `tool_calls`, `tool_results`, `usage`, `attachments`. Reutilizamos esas tablas y añadimos columnas propias a `agent_conversations`:

- `space_id` (nullable, index) — Spec C.
- `agent` (varchar 100) — clave corta del agente; hoy `'megalomaniac'`.
- `model` (nullable) — override por hilo; `null` = modelo del resolver.
- `mode` (nullable) — Spec B (`web|local|both|off`).
- `pinned_at`, `archived_at` (nullable).

Modelos: `App\Models\ChatThread extends Laravel\Ai\Models\Conversation`, `App\Models\ChatMessage extends Laravel\Ai\Models\ConversationMessage`. El SDK consulta por query builder, no por Eloquent, así que extender los modelos no interfiere.

**No se usa la creación perezosa de conversación del SDK** (título IA con `cheapestTextModel`): el controlador pre-crea el hilo para (a) conocer el id durante el stream, (b) fijar título determinista y columnas propias. El SDK sigue persistiendo los mensajes vía `RemembersConversations` al continuar el hilo con `continue($id, as: $user)`.

### Contrato SSE

`POST` de chat responde `text/event-stream` con este orden:

1. Evento propio: `{"type":"thread","threadId":"<uuid>"}` (permite al cliente conocer el hilo nuevo).
2. Eventos del SDK serializados tal cual (`data: {json}\n\n`): `stream_start`, `text_start`, `text_delta` (campo `delta`), `text_end`, `reasoning_*`, `tool_call` (`tool_id`, `tool_name`, `arguments`, `successful` en su par), `tool_result` (`tool_name`, `result`, `successful`, `error`, `denied`), `citation` (`citation: {title, url}`), `stream_end` (`reason`, `usage`), `error` (`message`, `recoverable`).
3. Cierre: `data: [DONE]`.

**Stop**: el cliente aborta el `fetch` (AbortController). El servidor no cancela la generación: termina y persiste el mensaje completo. Documentado y aceptado (el SDK v0.11 no expone cancelación server-side fiable).

**Persistencia de citas**: en vivo llegan como eventos `citation`; al recargar se leen de `meta.citations` (el SDK las guarda al cerrar el stream). Formato por cita: `{url, title, start_index, end_index}`.

### Por qué evento `thread`

El SDK crea/actualiza la conversación en un callback `then()` que corre **después** de emitir todos los eventos; ningún evento contiene el `conversationId` para hilos nuevos. Sin el evento propio, el cliente no puede cambiar la URL ni recargar el rail.

## Modelo de datos

### Migración `add_chat_thread_columns_to_agent_conversations_table`

```
space_id    string(36)  nullable, index
agent       string(100) nullable
model       string(100) nullable
mode        string(20)  nullable
pinned_at   timestamp   nullable
archived_at timestamp   nullable
```

Nombre de tabla desde `config('ai.conversations.tables.conversations', 'agent_conversations')`.

### `ChatThread`

- `casts()`: `pinned_at`, `archived_at` → `datetime`.
- `messages(): HasMany<ChatMessage>` (sin orden por defecto; ordenar explícito).
- Scopes: `forUser(User)` (participant morph), `active()` (`archived_at` null), `withMessages()` (`whereHas`), `ordered()` (`pinned_at DESC`, `updated_at DESC`), `search(string)` (LIKE con escape).
- `belongsToUser(User): bool` (participant_type + participant_id).

### `ChatMessage`

- `citations(): array` → `meta['citations'] ?? []`.
- `isUser(): bool`.
- `$casts` heredados del SDK (`meta`, `tool_calls`, `tool_results`, `usage`, `attachments`).

### Factories

`ChatThreadFactory` (id uuid7, participant morph `User`, título fake) y `ChatMessageFactory` (id uuid7, conversation_id, role, content, `'[]'` en columnas JSON), con estados `assistant()` y `withCitations(array)`.

### Resources

- `ChatThreadResource`: `id`, `title`, `model`, `is_pinned`, `created_at`, `updated_at` (ISO8601).
- `ChatMessageResource`: `id`, `role`, `content`, `citations`, `created_at`.

## Backend

### `App\Ai\Services\ChatService`

| Método | Contrato |
|---|---|
| `isConfigured(User): bool` | `ai_enabled && ai_provider_url && ai_provider_key` |
| `createThread(User, string $firstMessage, ?string $model): ChatThread` | uuid7, participant morph, título `Str::limit(strip_tags, 60, preserveWords)`, `agent='megalomaniac'` |
| `streamTurn(User, ChatThread, string $message, ?string $model): StreamableAgentResponse` | `AiProviderResolver::for()` + `(new MegalomaniacAgent($user))->continue($thread->id, as: $user)->stream($message, provider: $provider, model: $model ?: $resolverModel)`; `RuntimeException` si no configurado |
| `regenerate(User, ChatThread): ?StreamableAgentResponse` | Llama `dropLastExchange()`, re-stream del mismo texto; `null` si no hay intercambio |
| `dropLastExchange(ChatThread): ?string` | Borra último assistant (si existe) y último user; devuelve el texto del user |
| `editAndResend(User, ChatThread, string $messageId, string $content): ?StreamableAgentResponse` | Valida mensaje user del hilo, trunca desde ahí (inclusive) y re-stream; `null` si no es válido |
| `truncateFrom(ChatThread, ChatMessage): void` | Borra el mensaje y todos los posteriores (por orden de id uuid7) |
| `deleteThread(ChatThread): void` | Borra mensajes + hilo en transacción |
| `availableModels(User): array` | `GET {url}/models`, cache 5 min, fallback `[ai_model ?: 'gpt-4o-mini']` |

`AiProviderResolver` se mantiene como única fuente de provider/model (mismo patrón que `InsightService`).

### Rutas (grupo `auth` + `verified` de `routes/web.php`)

| Método | URI | Action | Nombre | Notas |
|---|---|---|---|---|
| GET | `ai/chat` | `index` | `ai.chat.index` | Inertia `ai/chat` |
| GET | `ai/chat/{thread}` | `show` | `ai.chat.show` | Inertia `ai/thread` |
| POST | `ai/chat` | `send` | `ai.chat.send` | SSE, `throttle:30,1` |
| POST | `ai/chat/{thread}/regenerate` | `regenerate` | `ai.chat.regenerate` | SSE, `throttle:30,1` |
| POST | `ai/chat/{thread}/edit` | `edit` | `ai.chat.edit` | SSE, `throttle:30,1` |
| PATCH | `ai/chat/{thread}` | `update` | `ai.chat.update` | title / pinned |
| DELETE | `ai/chat/{thread}` | `destroy` | `ai.chat.destroy` | |
| GET | `ai/models` | `models` | `ai.models` | refresh del picker |

Se elimina `GET ai/conversations` y `App\Http\Controllers\AiChatController`.

### Controlador `App\Http\Controllers\Ai\ChatController`

- `index`: props `threads` (limit 100, `forUser→active→withMessages→ordered`), `models`, `ai` (`enabled`, `configured`, `defaultModel`), `suggestedPrompts`.
- `show`: `authorize('view')`; props `thread`, `messages` (orden id asc, con citations), `threads`, `models`, `ai`.
- `send`: valida config (422 JSON si no), resuelve/crea hilo, persiste override de `model`, envuelve el stream con el evento `thread` + `[DONE]`, `set_time_limit(0)` y headers `text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`.
- `regenerate` / `edit`: `authorize('update')`; 422 JSON si no hay nada que regenerar / mensaje inválido.
- `update`: `authorize('update')`; aplica `title` y/o `pinned` (`pinned_at = now()` o `null`); `back()`.
- `destroy`: `authorize('delete')`; `to_route('ai.chat.index')`.
- `models`: JSON `availableModels()`.

Errores de espera al usuario: `abort(422, 'Configura tu proveedor de IA en Settings → IA.')`.

### Policy

`App\Policies\ChatThreadPolicy` con `view`/`update`/`delete` = `$thread->belongsToUser($user)`. Auto-discovery de Laravel 12 (`App\Models\ChatThread` → `App\Policies\ChatThreadPolicy`).

### Form Requests

- `SendChatMessageRequest`: `message` required string max 4000; `thread_id` nullable string size 36; `model` nullable string max 100.
- `EditChatMessageRequest`: `message_id` required string size 36; `content` required string max 4000.
- `UpdateChatThreadRequest`: `title` sometimes string max 120; `pinned` sometimes boolean.

## Frontend

### Rutas y layout

- `layouts/chat-layout.tsx`: envuelve `MainLayout`; renderiza rail (280px, desktop), top bar con trigger móvil (`Sheet`) + slot `header`, y contenido. Props: `threads`, `activeThreadId`, `header`, `children`.
- `pages/ai/chat.tsx` (home): hero centrado con slogan, `Composer` grande, chips de sugerencias (estáticas), grid "Recientes" (si hay hilos). Si `!ai.configured` → `ProviderNotice` y composer deshabilitado.
- `pages/ai/thread.tsx`: header con título editable inline + menú (renombrar, fijar, borrar con confirmación), `MessageList`, composer sticky. Estado local de mensajes inicializado desde props.
- `main-layout.tsx`: el FAB pasa a ser `<Link href="/ai/chat">`; se eliminan `ChatPanel.tsx` y `MessageBubble.tsx`.

### Flujo de envío

1. Home: `send` sin `thread_id`. El evento `thread` guarda el id; al completar el stream, `router.visit(show, {replace: true})` (monta con mensajes persistidos).
2. Hilo: `send` con `thread_id`; al completar, append local del mensaje assistant (texto + citas) y `router.reload({ only: ['threads'], preserveScroll: true, preserveState: true })` para refrescar el rail.
3. Regenerate: `dropLastExchange` server-side y re-stream; el cliente reemplaza el último par.
4. Edit: textarea inline en el mensaje user; al guardar, el servidor trunca desde ahí y re-stream; el cliente trunca local desde ese mensaje.

### Componentes (`resources/js/components/ai/chat/`)

| Componente | Responsabilidad |
|---|---|
| `ThreadRail` | Nuevo hilo, búsqueda client-side, grupos (Fijados/Hoy/Ayer/7 días/Anteriores), lista |
| `ThreadItem` | Título truncado, activo, menú `⋯` (renombrar inline, fijar, borrar) |
| `Composer` | Textarea autosize 1–6 filas, Enter envía / Shift+Enter salto, límite 4000, stop, slot de modelo |
| `ModelPicker` | Dropdown Radix con `models`, check del activo, link "Gestionar en Settings" |
| `MessageList` | Lista + skeletons + auto-scroll con detección de "pegado abajo" + botón ir al final |
| `AssistantMessage` | Markdown, `SourcesPanel`, acciones copiar/regenerar (último), estado de stream |
| `UserMessage` | Burbuja derecha, acciones copiar/editar (textarea inline) |
| `SourcesPanel` | Fuentes numeradas (nº, dominio, título, link externo), resaltado al citar |
| `CitationChip` | Chip `[n]` clicable → scroll + flash en `SourcesPanel` |
| `StreamStatus` | "Pensando…", chips de tool calls (running/done/failed) |
| `Markdown` | react-markdown + remark-gfm + rehype-highlight + plugin de citas; estilos con tokens |
| `CodeBlock` | `pre` estilizado con botón copiar |
| `ProviderNotice` | Card CTA a Settings → IA cuando no hay provider |

### Libs y hooks

- `lib/chat-sse.ts`: parser SSE (buffer por `\n\n`, `data:` JSON, `[DONE]`), CSRF desde cookie, headers `Accept: application/json` + `X-Requested-With`, dispatch tipado de eventos.
- `hooks/use-chat-stream.ts`: estado `{status, text, citations, tools, error}` + `start(url, body)` + `stop()`.
- `lib/remark-citations.ts`: convierte `[n]` en nodos link `#cite-n` (sin deps nuevas); `Markdown` los renderiza como `CitationChip`.
- `lib/relative-date.ts`: etiquetas de agrupación del rail (Hoy/Ayer/7 días/Anteriores).
- `types/chat.ts`: `ChatThread`, `ChatMessage`, `Citation`, `ChatModelOption`, `ToolActivity`.

### Estados requeridos

loading (skeletons rail/hilo), vacío (home con sugerencias, rail con copy), error (banner con reintentar; 422 provider → notice), success (feedback "Copiado"), disabled (composer sin provider, enviar durante stream), recovery (regenerar). Responsive: rail → `Sheet` en `<lg`, composer sticky, ancho de lectura máx. ~72ch, sin clipping.

### Markdown y citas

- Estilos manuales con tokens Ember (sin `@tailwindcss/typography`); bloques `hljs` con clases mínimas en `app.css` mapeadas a la paleta.
- Citas inline: plugin remark convierte `[1]` → link `#cite-1`; override del componente `a` renderiza chip si `href` empieza por `#cite-`; si no, link externo `target="_blank" rel="noopener noreferrer"`.
- Spec B refinará la posición usando `start_index`/`end_index` persistidos.

## Seguridad

- Todo acceso a hilos/mensajes scoped por `participant_type`/`participant_id` + policy.
- Fix: `AiSettingsController@edit` deja de exponer `ai_provider_key`; envía `has_provider_key: bool`; `update()` conserva la key si el campo llega vacío; se invalida la cache de modelos al guardar.
- Throttle `30,1` en send/regenerate/edit.
- 404 para hilos ajenos (no leak de existencia). Sin logs de prompts/contenido.

## Testing

Pest 4, `tests/Feature/Ai/`:

- `ChatModelTest` — relación, scopes (`forUser`, `active`, `withMessages`, `ordered`, `search`), `citations`, `belongsToUser`.
- `ChatServiceTest` — `createThread` (título/columnas), `dropLastExchange`, `truncateFrom`, `availableModels` (`Http::fake`, cache, fallback), `isConfigured`.
- `ChatIndexTest` / `ChatThreadTest` — props Inertia (`assertInertia`), scoping, 404 ajeno, rename, pin, delete.
- `ChatStreamTest` — `MegalomaniacAgent::fake()`: crea hilo, título truncado, primer evento `thread`, `text_delta`, persistencia user+assistant y columnas `agent`/`model`.
- `ChatRegenerateTest` / `ChatEditTest` — sin duplicados, truncado correcto.
- `ChatModelsTest` — endpoint JSON, cache, fallback, 422 sin provider.
- `tests/Feature/Settings/AiSettingsTest.php` — no filtra la key, conserva la key en update vacío.

Streaming se verifica con `TestResponse::streamedContent()`.

## Compatibilidad con Specs B y C

- `space_id` y `mode` ya en schema (C y B sin migración destructiva).
- `agent` permite selección de agente (C) sin tocar rutas.
- `SourcesPanel` + parser de citas quedan listos para B; las citas persistidas ya se muestran al recargar.
- `ChatThreadPolicy` y `ChatService` reutilizables.

## Fuera de alcance (Spec A)

Fuentes web/locales y retrieval (B), espacios y agentes configurables (C), branching/ramas de conversación, compartir hilos, export, búsqueda server-side, cancelación server-side del stream, generación de título por IA, feedback (thumbs), SSR.

## Verificación / QA

- `php artisan test --compact` (suite completa), `vendor/bin/pint --dirty --format agent`.
- `npm run types`, `npm run lint`, `npm run build`.
- QA con Playwright MCP en `:8010` (login `test@example.com/password`): home, envío, streaming, citas (si el provider las emite), regenerate, edit, rename/pin/delete, rail móvil.

## Referencias

- ui-radar: catálogo libre de UIZZE consultado (2 queries) sin resultados → sin referencias visuales externas; se sigue el design system Ember (`docs/design-tokens.md`) y convenciones de chat con citas.
- `laravel/ai` v0.11: `RemembersConversations`, `DatabaseConversationStore`, eventos SSE en `Laravel\Ai\Streaming\Events`.
