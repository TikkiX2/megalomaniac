# Chat: Tareas + Tools por Request — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** (A) que el agente in-app pueda leer/completar/actualizar tareas (hoy solo puede crearlas); (B) seleccionar tools por request (router heurístico + override por hilo) para no enviar todos los schemas en cada turno.

**Architecture:** `TaskQueryTool` nueva; `ActionTool` suma acciones de tarea. `ToolCatalog` centraliza grupos de tools; `MegalomaniacAgent`/`RuntimeAgent` construyen sus tools desde el catálogo. `ToolRouter` elige grupos por mensaje; `chat_threads.tools_policy` permite override manual por hilo; `ChatService` construye el agente por turno con el subconjunto y emite un evento SSE `tools`.

**Spec:** no hay spec nueva (decisión directa del usuario, 2026-09-25). Contexto: `docs/superpowers/specs/2026-09-24-ai-chat-core-design.md`.

## Global Constraints

- SDK `laravel/ai`: las tools salen solo de `$agent->tools()` (`GeneratesText.php:168-174`); no hay override por prompt → se construye el agente por turno.
- Default de `MegalomaniacAgent` sigue siendo **todas** (no rompe agentes background ni otros usos).
- Grupos válidos centralizados en `ToolCatalog`; un override inválido se ignora (se cae al router).
- Sin deps nuevas. Tests con agentes fake (`RuntimeAgent::fake`, `MegalomaniacAgent::fake`) y `Http::fake`.
- Pint por tarea; commit al final con autorización del plan aprobado.

---

### Task 1: TaskQueryTool + acciones de tarea en ActionTool

**Files:** `app/Ai/Tools/TaskQueryTool.php`; modificar `app/Ai/Tools/ActionTool.php`; `tests/Feature/Ai/TaskToolsTest.php`.

**Interfaces:**
- `TaskQueryTool(User)`: schema `scope` (`all|personal|freelance`), `status`, `priority`, `overdue` (bool), `due_within_days` (int), `due_before` (date), `project` (nombre/ID), `search`, `include_done` (bool), `limit` (1-50, default 20). Devuelve `{tasks:[{id,title,status,priority,due_date,is_done,project,scope}], summary:{pending,overdue,due_today}}`, ordenado por vencimiento y solo del usuario.
- `ActionTool`: `complete_task` (`task_id`), `update_task` (`task_id` + `title|description|status|priority|due_date`), `create_task` acepta `due_date`. Al setear `status` se sincroniza `is_done` vía `TaskBoardColumnService::columnsFor(...)->firstWhere('is_done', true)`.

- [ ] Tests: listado con resumen (personal+freelance+done excluida), scope, overdue/due_within_days/due_before, search+project, scoping (no ajenas), complete_task, update_task, create_task con due_date, error en tarea ajena → RED → implementar → GREEN.

---

### Task 2: ToolCatalog + refactor de agentes

**Files:** `app/Ai/Tools/ToolCatalog.php`; modificar `app/Ai/Agents/MegalomaniacAgent.php`, `app/Ai/Agents/RuntimeAgent.php`; `tests/Feature/Ai/ToolCatalogTest.php`.

**Interfaces:**
- `ToolCatalog::GROUPS` con label + tools por grupo: `tasks`, `workout`, `finance`, `nutrition`, `grocery`, `actions`, `integrations`, `agents`.
- `ToolCatalog::toolsFor(User, array $groups): array` (`['*']` = todas); `ToolCatalog::isValidGroup(string): bool`; `ToolCatalog::allGroups(): array`.
- `MegalomaniacAgent::__construct(User $user, array $toolGroups = ['*'])` → `tools()` desde el catálogo.
- `RuntimeAgent::tools()` usa `ToolCatalog` para los internos + allowlist de integraciones (misma semántica que hoy).

- [ ] Tests: catálogo expone todos los grupos y tools; agente con subconjunto solo esas tools; default todas; RuntimeAgent sigue filtrando por policy → RED → implementar → GREEN.

---

### Task 3: ToolRouter + config

**Files:** `app/Ai/Tools/ToolRouter.php`, `config/ai_tools.php`; `tests/Feature/Ai/ToolRouterTest.php`.

**Interfaces:**
- `ToolRouter::route(string $message): array` → grupos. Heurística por keywords (`config('ai_tools.keywords')`), verbos de escritura → `actions` (`config('ai_tools.write_verbs')`), fallback `config('ai_tools.fallback')` = `['tasks','integrations']`.
- Si detecta ≥1 grupo, devuelve detectados ∪ (`actions` si hay verbo) — nunca agrega `agents`/`integrations` salvo keyword.
- Mensajes ambiguos → fallback barato (sin `actions` si no hay verbo).

- [ ] Tests: cada grupo por keywords; verbo de escritura agrega actions; fallback; mensaje de integración/agente; normalización de acentos/mayúsculas → RED → implementar → GREEN.

---

### Task 4: Persistencia por hilo + ChatService

**Files:** migración `2026_09_25_000012_add_tools_policy_to_chat_threads_table.php`; modificar `app/Models/ChatThread.php` (cast), `app/Http/Resources/ChatThreadResource.php`, `app/Http/Requests/Ai/{SendChatMessageRequest,UpdateChatThreadRequest}.php`, `app/Ai/Services/ChatService.php`, `app/Http/Controllers/Ai/ChatController.php`; `tests/Feature/Ai/ChatToolsPolicyTest.php`.

**Interfaces:**
- `tools_policy` json nullable en `chat_threads`: `null` = auto (router); `{"mode":"manual","groups":["tasks","finance"]}`.
- `ChatService::streamTurn(User, ChatThread, string $message, ?string $model = null, ?array $toolsPolicy = null)`: resuelve grupos (override válido > router), persiste en el hilo, construye `MegalomaniacAgent($user, $groups)` (o `RuntimeAgent` si el hilo tiene agente custom, manteniendo su policy), y expone los grupos elegidos para el SSE.
- `ChatController@send` pasa `tools_policy` validado; evento SSE `tools` antes del stream (`{"type":"tools","groups":[...],"mode":"auto|manual"}`).

- [ ] Tests: send con policy manual la persiste y el evento `tools` la refleja; sin policy usa router (mensaje de finanzas → `finance`); override inválido → router; PATCH actualiza policy; agente custom mantiene su policy → RED → implementar → GREEN.

---

### Task 5: UI — ToolsPicker + chip de grupos

**Files:** `resources/js/components/ai/chat/ToolsPicker.tsx`, modificar `Composer.tsx`, `pages/ai/chat.tsx`, `pages/ai/thread.tsx`, `types/chat.ts`.

**Interfaces:** popover "Herramientas" con modo **Auto** o selección manual de grupos (labels desde props `toolGroups`); el estado se envía en `send` y se persiste; chip con los grupos del último turno (desde el evento SSE `tools`); `ChatThread` type suma `tools_policy`.

- [ ] `npm run types && npm run lint && npm run build` + QA Playwright.

---

### Task 6: QA, docs y commit

- [ ] Playwright: “¿Qué tareas tengo pendientes?” → respuesta con datos reales y chip `tasks`; “gasté 20 lucas en…” → grupos con `finance`; override manual a solo `tasks` persiste al recargar; “¿qué tareas…” sin tools de más en el evento.
- [ ] Suite completa + Pint + `docs/qa/playwright-report.md` + commit.

---

## Self-Review

**Cobertura:** gap de lectura (Task 1), token control (2-4), UI (5), QA (6). El router es heurístico y barato; el override manual es la válvula de escape. `manage_agents` queda en su grupo y no aparece por defecto (ahorro). Sin cambios en MCP server (los clientes externos eligen sus tools).
