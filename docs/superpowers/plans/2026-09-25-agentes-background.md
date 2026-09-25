# SP2 Agentes Background — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** `AgentDefinition` unificado (chat + background) con runs programados, informe, sugerencias, notificación opcional y escrituras vía aprobaciones de SP1.

**Architecture:** Modelos `AgentDefinition`/`AgentRun`; `AgentDefinitionService` (CRUD/límites/schedule), `AgentScheduler` (due + `next_run_at`), `RuntimeAgent` (instrucciones + tools filtradas por `tools_policy`, salida JSON), `AgentRunner` (guardas → prompt → persistencia → notificación), comando `agents:dispatch-due` cada minuto + `RunAgentJob`, tool `manage_agents` (solo agente main), UI `/agents`, selector de agente en el chat.

**Spec:** `docs/superpowers/specs/2026-09-25-agentes-background-design.md`

## Global Constraints

- Sin deps nuevas. SDK `laravel/ai`: `RuntimeAgent::fake([...])`, structured output = `StructuredTextResponse` (ArrayAccess), `usage->toArray()`.
- Writes de un run pasan por `ApprovalRequest` (`source=schedule`) — no tocar SP1.
- Convenciones del repo: `casts()`, scopes, Form Requests, Wayfinder/URLs directas, tokens Ember.
- Pint por tarea; commits solo con autorización.

---

### Task 1: Migraciones, modelos y factories

**Files:** migrations `2026_09_25_000004_create_agent_definitions_table.php`, `..._000005_create_agent_runs_table.php`; `app/Models/AgentDefinition.php`, `AgentRun.php`; factories; Test `tests/Feature/Agents/AgentModelsTest.php`.

**Interfaces:** columnas exactas del spec. `AgentDefinition` casts `tools_policy` array, `schedule_type` string, fechas; scopes `forUser`, `enabled`, `due` (`enabled` + `next_run_at <= now`); `agentRuns()` HasMany. `AgentRun` casts fechas, `usage` array; scopes `forUser`, `running`; relación `definition()`.

- [ ] Test (encryption no aplica; scopes, casts, unique `(user_id,key)`, relaciones) → RED → implementar → GREEN.

---

### Task 2: AgentDefinitionService + AgentScheduler

**Files:** `app/Integrations/…` no: `app/Ai/Agents/AgentDefinitionService.php`, `AgentScheduler.php`; Test `tests/Feature/Agents/AgentScheduleTest.php`.

**Interfaces:**
- `AgentDefinitionService::create(User, array, ?Model $createdBy = null)`, `update()`, `toggle()`, `delete()`, `validateSchedule(string $type, string $value): void`.
- Intervalos válidos: `15m,30m,1h,6h,12h,daily,weekly`. Cron validado con `CronExpression::isValidExpression`.
- Límite 10 agentes/usuario; `key` slug único por usuario (auto desde `name` si falta).
- `AgentScheduler::nextRunAt(AgentDefinition): CarbonInterface` (intervalo o `getNextRunDate`) y `dispatchDue(): int` (due + sin run `queued|running` + presupuesto diario → `RunAgentJob::dispatch(id,'schedule')`, fija `next_run_at`).

- [ ] Tests: schedule inválido, límite, `nextRunAt` con `travel`, `dispatchDue` respeta disabled/running/presupuesto (Queue::fake) → RED → implementar → GREEN.

---

### Task 3: RuntimeAgent + AgentRunner

**Files:** `app/Ai/Agents/RuntimeAgent.php`, `AgentRunner.php`; Test `tests/Feature/Agents/AgentRunnerTest.php`.

**Interfaces:**
- `RuntimeAgent implements Agent, HasTools, HasStructuredOutput`; constructor `(AgentDefinition $definition, array $context = [])`. `instructions()` = definition + contexto JSON acotado + contrato de salida. `tools()` = internos permitidos por `tools_policy.internal` + `IntegrationCatalogTool`/`IntegrationCallTool` con allowlist `integrations` (`["*"]` o kinds). Schema: `report` (string, required), `suggestions` (string JSON opcional), `notify` (string opcional).
- `AgentRunner::run(AgentDefinition, string $triggeredBy): AgentRun`:
  1. guardas (enabled, sin running, presupuesto, proveedor IA) → `skip()`.
  2. `RuntimeAgent->prompt(...)` con provider/model de `AiProviderResolver`.
  3. persistir `AgentRun` (`success|failed`, `report`, `usage`, `suggestions_created`, `approvals_created`), upsert `AgentSuggestion` (`type=agent:{key}`), `next_run_at`, `failure_count`/backoff.
  4. notificación Telegram si `notify` y hay conexión `telegram` habilitada (fuera del tool path; `Http::fake` en tests).
- `RunAgentJob(int $definitionId, string $triggeredBy)`.

- [ ] Tests: success con `RuntimeAgent::fake([[['report'=>'...','suggestions'=>'[{"title":"x","content":"y"}]','notify'=>'hola']]])`, skip sin proveedor, failed por excepción, backoff, sugerencias upsert, notificación, policy (tools permitidas/denegadas) → RED → implementar → GREEN.

---

### Task 4: Tools con allowlist + manage_agents

**Files:** modify `IntegrationCatalogTool`/`IntegrationCallTool` (+ `?array $allowedKinds = null`); create `app/Ai/Tools/ManageAgentsTool.php`; modify `MegalomaniacAgent::tools()`; Tests en `tests/Feature/Integrations/IntegrationToolsTest.php` + `tests/Feature/Agents/ManageAgentsToolTest.php`.

- [ ] Tests: catalog filtra kinds fuera de allowlist; call rechaza conexión no permitida; `manage_agents` create/update/list/delete/enable/disable, límite 10, y `RuntimeAgent` NO expone `manage_agents` → RED → implementar → GREEN.

---

### Task 5: Controladores, rutas y UI `/agents`

**Files:** `app/Http/Controllers/Agents/{AgentController}.php`, `app/Http/Requests/Agents/{StoreAgentRequest,UpdateAgentRequest}.php`, `routes/agents.php` (require desde web.php), `resources/js/pages/agents/{index,show}.tsx`, `resources/js/components/agents/AgentWizard.tsx`, sidebar + `tests/Feature/Agents/AgentPagesTest.php`.

**Rutas:** GET `agents`, GET `agents/{agent}`, POST `agents`, PATCH `agents/{agent}`, DELETE, POST `agents/{agent}/run` (`throttle:6,1`), POST `agents/{agent}/toggle`. Scoping `forUser()->findOrFail` (404).

- [ ] Tests Inertia (index props, store valida, toggle, run dispatch con `Queue::fake`, 404 ajeno) → RED → implementar → GREEN; `npm run types && npm run build`.

---

### Task 6: Selector de agente en el chat

**Files:** modify `app/Ai/Services/ChatService.php` (resolver `RuntimeAgent` si `ChatThread.agent` corresponde a un definition), `ChatController` (props `agents`), `resources/js/components/ai/chat/Composer.tsx`/`ModelPicker` (selector simple), Test `tests/Feature/Ai/ChatAgentSelectionTest.php`.

- [ ] Test: hilo con `agent` custom usa las instrucciones del definition (`RuntimeAgent::fake` + `assertPrompted` sobre el definition) → RED → implementar → GREEN.

---

### Task 7: QA, docs y cierre

- [ ] `agents:dispatch-due` registrado en `routes/console.php` (`everyMinute`).
- [ ] Suite completa + Pint + `npm run types/build`.
- [ ] QA Playwright: `/agents` lista vacía + wizard crea un agente (schedule 1h, tools internas), badge/estado, run manual con proveedor no configurado → `skipped` visible; sidebar; chat muestra el selector.
- [ ] `docs/qa/playwright-report.md` + commit condicional.

---

## Self-Review

**Cobertura:** modelo, scheduler, runner, tools, UI, chat y QA. Guardas de presupuesto/concurrencia cubiertas por tests. La salida JSON (en vez de schema anidado) se debe a que el contrato `JsonSchema` no expresa arrays de objetos; se parsea tolerante (fences/JSON) y se documenta.
