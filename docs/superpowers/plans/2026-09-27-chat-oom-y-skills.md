# Chat IA: fix OOM + Skills — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar el cuelgue del chat IA (OOM por imágenes base64 re-enviadas) y agregar skills invocables (modelo + usuario) administrables en Settings.

**Architecture:** Optimización de imágenes en upload (GD) + recorte de adjuntos históricos en `MegalomaniacAgent::messages()` + guards de payload/errores en el streaming. Skills como modelo `Skill` por usuario, expuestas al modelo con `LoadSkillTool` (grupo `skills` de `ToolCatalog`), inyectables por turno con `skill_keys`, y administradas en Settings → Skills.

**Tech Stack:** Laravel 12, laravel/ai v0.11, Inertia v2 + React 19, Wayfinder, Pest 4, GD, symfony/yaml (ya presente).

**Spec:** `docs/superpowers/specs/2026-09-27-chat-oom-y-skills-design.md`

## Global Constraints

- `memory_limit=512M` en prod (nuevo `docker/php/limits.ini`).
- Máx. imágenes por envío: 5 (`attachment_ids`), 8 MB por imagen, payload preflight 20 MB.
- Skills: máx. 30 por usuario, `skill_keys` máx. 5 por turno.
- Sin dependencias nuevas de composer/npm.
- Tests con Pest (`php artisan test --compact --filter=...`), Pint `--dirty` antes de cerrar.

---

## Workstream A — Fix OOM

### Task A1: memoria PHP

**Files:** Create `docker/php/limits.ini`; Modify `docker/Dockerfile.prod`

- [ ] Crear `docker/php/limits.ini`: `memory_limit = 512M`, `max_input_time = 300`.
- [ ] En `Dockerfile.prod` (stage runtime) agregar
      `COPY docker/php/limits.ini /usr/local/etc/php/conf.d/zz-limits.ini`.
- [ ] Verificación (post-build): `docker exec megalomaniac-app php -r 'echo ini_get("memory_limit");'` → `512M`.

### Task A2: optimizador + upload

**Files:** Create `app/Support/Images/ChatImageOptimizer.php`; Modify `app/Http/Controllers/Ai/ChatAttachmentController.php`; Test `tests/Feature/Ai/ChatImageUploadOptimizationTest.php`

- [ ] Test: imagen GD 4000×3000 → el adjunto guardado tiene lado ≤ 1600 y `size` menor al original.
- [ ] Implementar `ChatImageOptimizer::optimize(string $absolutePath): array{width,height,size,mime}`
      con GD (`imagecreatefrom*`, `imagescale`, `imagejpeg` q80; PNG si alpha).
- [ ] Integrar en `store()`: guardar original temporal, optimizar, persistir optimizado; actualizar
      `size`/`mime`; si la optimización falla, conservar el original.

### Task A3: shrink legacy

**Files:** Create `app/Console/Commands/ShrinkChatImages.php`; (optimizador de A2)

- [ ] Comando `megalomaniac:shrink-chat-images` con `--user=` y `--dry-run`.
- [ ] Recorre `chat_attachments` kind=image, re-optimiza en sitio, actualiza `size`, e informa ahorro.
- [ ] Test ligero de dry-run/no-op con `Storage::fake`.

### Task A4: podado de vacíos

**Files:** Modify `app/Ai/Services/ChatService.php`; Test `tests/Feature/Ai/StaleImageAttachmentTest.php`

- [ ] Extender el test: archivo de 0 bytes se poda del historial.
- [ ] `pruneMissingStoredImages`: exigir `exists($path) && size($path) > 0`.

### Task A5: recorte de historial

**Files:** Modify `app/Ai/Agents/MegalomaniacAgent.php`; Test `tests/Feature/Ai/ChatImageHistoryTrimTest.php`

- [ ] Test: historial con 2 imágenes viejas + 1 última → el body al proveedor no contiene base64 de
      las viejas y sí de la última.
- [ ] Override `messages()` (alias del trait): conservar adjuntos solo en el último `UserMessage`;
      reemplazar en el resto con `[n imágenes omitidas del historial]`.

### Task A6: preflight + shutdown

**Files:** Modify `app/Ai/Services/ChatService.php`, `app/Http/Controllers/Ai/ChatController.php`, `app/Http/Requests/Ai/SendChatMessageRequest.php`; Tests `tests/Feature/Ai/ChatPayloadGuardTest.php`

- [ ] Preflight: si Σ bytes (adjuntos del turno + último mensaje) > 20 MB → `ValidationException`/422 legible.
- [ ] `streamResponse`: `register_shutdown_function` que ante `error_get_last()` fatal emita
      `data: {"type":"error",...}` + `data: [DONE]`.
- [ ] Validación `attachment_ids` máx. 5 (ya está) + 8 MB por imagen.

---

## Workstream B — Skills

### Task B1: modelo

- [ ] `php artisan make:model Skill -mf`
- [ ] Migración `skills`: uuid7 pk, `user_id` FK, `key`, `name`, `description` (text),
      `instructions` (longText), `enabled` bool, `source` string, `metadata` json, timestamps,
      `unique(user_id,key)`.
- [ ] `casts()`, scopes `forUser`/`enabled`, factory.

### Task B2: SkillCatalog

**Files:** Create `app/Ai/Skills/SkillCatalog.php`

- [ ] `summariesFor(User): array<int,array{key,name,description}>`
- [ ] `instructionsFor(User,string): ?string`
- [ ] `create/update/delete/toggle` con límite 30 y slug único.
- [ ] `importFromMarkdown(User,string,string $filename): Skill` (frontmatter YAML o heurística).

### Task B3: tool + catálogo en prompt

**Files:** Create `app/Ai/Tools/LoadSkillTool.php`; Modify `app/Ai/Tools/ToolCatalog.php`, `app/Ai/Agents/MegalomaniacAgent.php`

- [ ] Tool con schema `{ skill: string }` y `handle` que devuelve instrucciones o error.
- [ ] Grupo `skills` en `ToolCatalog::groups()` + `make()`.
- [ ] `MegalomaniacAgent::instructions()`: bloque "Skills disponibles" (key — description) cuando existan.

### Task B4: invocación explícita

**Files:** Modify `SendChatMessageRequest`, `ChatController::send`, `ChatService::streamTurn`, `MegalomaniacAgent`

- [ ] `skill_keys` nullable array max:5, cada uno existente y del usuario.
- [ ] `withSkills(array $instructions)` en el agente (bloque "Para este turno seguí estas skills").
- [ ] `ChatService::streamTurn(..., array $skillKeys = [])`.

### Task B5: Settings → Skills

**Files:** Create `app/Http/Controllers/Settings/SkillController.php`, `app/Http/Requests/Skills/{Store,Update,Import}SkillRequest.php`, `resources/js/pages/settings/skills.tsx`; Modify `routes/settings.php`, `resources/js/layouts/settings/layout.tsx`

- [ ] Rutas index/store/update/destroy/toggle/import.
- [ ] Página con listado, form (name/description/instructions/enabled), import de `.md`, borrar.
- [ ] Item "Skills" en el nav de settings (junto a "AI").

### Task B6: picker

**Files:** Create `resources/js/components/ai/chat/SkillsPicker.tsx`; Modify `resources/js/pages/ai/chat.tsx`, `resources/js/pages/ai/thread.tsx`, `app/Http/Controllers/Ai/ChatController.php`

- [ ] `skills` en props de `index`/`show`.
- [ ] Picker multi-select en el composer; `skill_keys` en el body del stream.

### Task B7: importar las 5 skills

**Files:** Create `database/seeders/skills/{brainstorming,using-superpowers,writing-plans,anti-ui-slop,ui-radar}.md`, `database/seeders/SkillSeeder.php`, `app/Console/Commands/ImportSkills.php`

- [ ] Copiar los SKILL.md (superpowers desde el paquete opencode; anti-ui-slop/ui-radar desde `~/.agents/skills`).
- [ ] `skills:import --user=` / `--all-ai` + `SkillSeeder` que usa el mismo servicio.

### Task B8: tests de skills

- [ ] CRUD + autorización + límite; import frontmatter; tool; catálogo en instructions;
      `skill_keys` inyecta; comando import.

---

## Verificación

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=ChatImage` / `--filter=Skill` / `--filter=Stale` / `--filter=Chat`
- [ ] `npm run build`
- [ ] Deploy: sync a `/root/docker/megalomaniac/app`, `docker compose build app && up -d`,
      `megalomaniac:shrink-chat-images`, `skills:import --user=5`, verificar `memory_limit`,
      reproducir hilo con imágenes.
- [ ] Smoke Playwright: Settings → Skills y chat con picker.
