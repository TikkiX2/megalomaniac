# Chat IA: fix de cuelgue por OOM + Skills — Design

**Fecha:** 2026-09-27
**Autor:** sesión opencode (aprobado por admin@tikkix2.space)
**Estado:** aprobado

## Problema

El chat IA de Megalomaniac se queda "pensando" (tildado) y hay que reintentar; 6 reintentos
sobre el mismo mensaje (hilo `01a0df71-9024-7331-8210-8d8948f77dbc`, usuario 5).

## Causa raíz (evidencia)

1. **OOM de PHP en prod.** `memory_limit=128M` (default de `php:8.4-fpm`; `docker/php/uploads.ini` no lo sube).
   `Allowed memory size of 134217728 bytes exhausted (tried to allocate 47198208 bytes) at
   vendor/guzzlehttp/guzzle/src/Handler/StreamHandler.php:503` — 6 veces entre 02:34:07 y 02:34:21
   (2026-09-27), en sincronía con 6 `POST /ai/chat` de 197 bytes en el access log de nginx.
2. **Re-embebido de imágenes en cada turno.** El hilo tiene 13 imágenes = 51 MB en
   `storage/app/private/ai-attachments/5/` (hasta 5.7 MB c/u). El SDK re-arma el historial y
   base64-ea cada `StoredImage` en cada request (`Laravel\Ai\Files\StoredImage::content()` →
   `Files/Image.php:55`). 51 MB → ~68 MB base64 + copias de Guzzle → fatal.
3. **Fatal no capturable mid-stream.** El `catch (Throwable)` de `ChatController::streamResponse`
   no puede atrapar un fatal OOM; el stream muere sin evento `error`/`[DONE]` y el fetch del
   navegador queda colgado → "se tilda pensando".
4. **Segundo modo de fallo:** adjuntos con `data:image/png;base64,` vacío → upstream 400
   (visto en el log de QA `ai-proxy`). `ChatService::pruneMissingStoredImages` solo verifica
   existencia, no tamaño 0.
5. **Modelo de razonamiento.** `deepseek-v4.1-flash` (vía `https://opencode.ai/zen/go/v1/`)
   emite thinking largo: respuestas SSE de hasta 4 MB (nginx) — legítimo, pero agrava la
   percepción de cuelgue cuando además falla el punto 3.

## Diseño

### Workstream A — fix

1. **Memoria:** `docker/php/limits.ini` con `memory_limit=512M`; `Dockerfile.prod` lo copia a
   `conf.d/zz-limits.ini`.
2. **Optimizador de imágenes:** `App\Support\Images\ChatImageOptimizer` (GD) reescala a máx.
   1600 px lado mayor y re-encodea (JPEG q80; PNG si hay alpha). Se aplica en
   `ChatAttachmentController::store` para cada imagen subida.
3. **Legacy:** comando `megalomaniac:shrink-chat-images` (idempotente, `--user=`, `--dry-run`)
   que re-optimiza en sitio los adjuntos existentes.
4. **Podado robusto:** `pruneMissingStoredImages` descarta también archivos ausentes o de 0 bytes.
5. **Recorte de historial:** `MegalomaniacAgent::messages()` conserva adjuntos solo en el último
   `UserMessage`; en los anteriores los reemplaza por `[n imágenes omitidas del historial]`.
   Así el payload no crece sin techo aunque queden imágenes grandes.
6. **Fail-loud:** preflight en `ChatService::streamTurn` (suma de bytes > 20 MB → 422 con mensaje)
   y `register_shutdown_function` en `ChatController::streamResponse` que emite un evento `error`
   + `[DONE]` si el stream murió por fatal. Validación de 8 MB por imagen en
   `SendChatMessageRequest`.

### Workstream B — Skills

- **Modelo `Skill`** por usuario: `key` (slug), `name`, `description`, `instructions`, `enabled`,
  `source` (`manual|import`), `metadata`. Límite 30 por usuario. UUID7 como el resto del chat.
- **`SkillCatalog`**: listar/crear/editar/borrar/toggle + `importFromMarkdown` (frontmatter YAML
  `---` con `symfony/yaml`, fallback `# H1` + primer párrafo).
- **`LoadSkillTool`** (contrato `Laravel\Ai\Contracts\Tool`): el modelo ve `key — description` en
  las instructions y carga las instrucciones completas bajo demanda. Grupo `skills` en
  `ToolCatalog` (se puede apagar desde ToolsPicker).
- **Invocación explícita:** `skill_keys` (máx. 5) en `SendChatMessageRequest`;
  `ChatService::streamTurn` las resuelve y `MegalomaniacAgent::withSkills()` las inyecta al
  system prompt del turno.
- **Admin:** Settings → Skills (index/create/edit/delete/toggle/import) siguiendo el patrón de
  Settings → IA.
- **Picker:** `SkillsPicker` multi-select en el composer.
- **Import inicial:** las 5 skills pedidas (brainstorming, using-superpowers, writing-plans,
  anti-ui-slop, ui-radar) se copian a `database/seeders/skills/*.md` y el comando
  `skills:import --user=<id>` (invocado también por `SkillSeeder`) las carga en DB.

## No objetivos

- Cambiar `RemembersConversations` a contexto manual.
- MCP/agentes externos para skills.
- Arreglar el `ai-proxy` de QA (solo herramienta de inspección).

## Testing

Pest, siguiendo `tests/Feature/Ai/*`: optimizador, podado de 0 bytes, recorte de historial,
preflight, error de shutdown, CRUD/import de skills, tool y `skill_keys`. Smoke de UI con
Playwright MCP.
