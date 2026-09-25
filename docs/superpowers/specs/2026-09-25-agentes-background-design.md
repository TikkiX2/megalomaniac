# Agentes Background — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Alcance: Sub-proyecto 2 de 4 (1 Integraciones ✅ Ola 0 → 2 Agentes background → 3 Feed → 4 Storage)

## Problema

El cockpit tiene un único agente de chat sin estado durable. No existen agentes con instrucciones propias que el usuario (o el agente main) cree y que corran solos cada cierto tiempo. Las sugerencias actuales (`SuggestionService`) son reglas fijas, no agentes.

## Objetivo

Un modelo **unificado** `AgentDefinition` que sirva para (a) el chat como selector de agente (cierra el Spec C pendiente del chat) y (b) ejecuciones programadas en background. Cada run es auditable, produce un informe, sugerencias y notificación opcional; las escrituras a servicios externos pasan por las aprobaciones de SP1.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Modelo unificado `AgentDefinition` para chat + background; `ChatThread.agent` guarda el `key`. |
| 2 | Creación: tool `manage_agents` del agente main desde el chat + UI `/agents`. Los agentes background **no** pueden crear/editar agentes (política v1, evita loops). |
| 3 | Resultados: `AgentRun.report` (markdown) + `AgentSuggestion` (existente) + notificación Telegram opcional (Ola 2). |
| 4 | Escrituras de un run → `ApprovalRequest` de SP1 con `source=schedule` y rationale "Agente {name}". |
| 5 | Schedule: intervalos predefinidos (`15m, 30m, 1h, 6h, 12h, daily, weekly`) o cron. |
| 6 | Presupuesto y guardas: `max_runs_per_day`, `max_tokens_per_run`, una ejecución concurrente por agente, backoff exponencial en fallos (`failure_count`). |
| 7 | Sin meta-agentes, sin dashboard de costos (solo `usage` por run). |

## Arquitectura

```
Scheduler (cada minuto)
  └─ agents:dispatch-due ──► RunAgentJob (unique por agente)
                                └─ AgentRunner::run(definition, 'schedule')
Chat (tool manage_agents) ──► AgentDefinitionService (CRUD + validación)
UI /agents ─────────────────► idem + run manual
```

- **`RuntimeAgent`** (implementa `Agent`, `HasTools`, `HasStructuredOutput`): se construye con la `AgentDefinition` y el contexto; sus `instructions()` son las del definition + contexto acotado (últimos runs, aprobaciones pendientes, sugerencias abiertas).
- **`AgentRunner`**: guardas → prompt → persistencia → notificación.
- **`AgentScheduler`**: calcula `next_run_at` (intervalo o cron con `Cron\CronExpression`, ya incluido en Laravel).
- Proveedor IA vía `AiProviderResolver` (BYO del usuario). Sin proveedor configurado → run `skipped` con motivo, sin romper el scheduler.

## Modelo de datos

### `agent_definitions`

```
id, user_id FK cascade
key             string(50)            // slug único por usuario
name            string(100)
description     string(255) nullable
instructions    text
tools_policy    json                  // {"internal":[...],"integrations":["github","docker"]|["*"]|[]}
schedule_type   string(10)            // interval|cron
schedule_value  string(50)            // '1h' | '0 8 * * *'
timezone        string(50) default 'UTC'
enabled         boolean default true
max_runs_per_day unsignedSmallInteger default 24
max_tokens_per_run unsignedInteger default 2000
last_run_at     timestamp nullable
next_run_at     timestamp nullable
failure_count   unsignedSmallInteger default 0
created_by_type / created_by_id       nullableMorphs
timestamps
unique (user_id, key) · index (user_id, enabled, next_run_at)
```

### `agent_runs`

```
id
agent_definition_id FK cascade
user_id FK cascade
status          string(15)            // queued|running|success|failed|skipped
triggered_by    string(15)            // schedule|manual|chat
started_at, finished_at               nullable
report          text nullable
suggestions_created unsignedSmallInteger default 0
approvals_created   unsignedSmallInteger default 0
usage           json nullable          // tokens in/out del SDK
error           text nullable
context         json nullable          // snapshot de contexto (redactado)
notified_at     timestamp nullable
timestamps
index (agent_definition_id, created_at) · index (user_id, status)
```

## Contratos

```php
final class AgentDefinitionService
{
    public function create(User $user, array $data, ?Model $createdBy = null): AgentDefinition;
    public function update(AgentDefinition $definition, array $data): AgentDefinition;
    public function toggle(AgentDefinition $definition, bool $enabled): AgentDefinition;
    public function delete(AgentDefinition $definition): void;
    /** @throws ValidationException */
    public function validateSchedule(string $type, string $value): void;
}

final class AgentRunner
{
    public function run(AgentDefinition $definition, string $triggeredBy): AgentRun;
    public function skip(AgentDefinition $definition, string $reason, string $triggeredBy): AgentRun;
}

final class AgentScheduler
{
    public function nextRunAt(AgentDefinition $definition): CarbonInterface;
    public function dispatchDue(): int;   // devuelve cantidad despachada
}
```

**Salida estructurada del run** (`HasStructuredOutput`):

```json
{
  "report": "markdown",
  "suggestions": [{"title": "...", "content": "...", "data": {"action_url": "/..."}}],
  "notify": "mensaje corto opcional"
}
```

**`tools_policy`** → filtrado de herramientas:
- `internal`: subconjunto de `WorkoutQueryTool`, `FinanceQueryTool`, `NutritionQueryTool`, `GroceryQueryTool`, `ActionTool` (por defecto `[]`).
- `integrations`: kinds de SP1 permitidos (`["github","docker"]`, `["*"]` o `[]`). Los tools de integración pasan a aceptar una allowlist (`IntegrationCatalogTool` filtra; `IntegrationCallTool` rechaza conexiones fuera de la lista).

## Backend

### Runner

1. Guardas: agente habilitado, sin run `running`, `runs_hoy < max_runs_per_day`, proveedor IA configurado.
2. `RuntimeAgent` con `#[MaxTokens(max_tokens_per_run)]`; contexto: últimas 3 runs (título+report truncado), aprobaciones pendientes (conteo), sugerencias activas (títulos).
3. `prompt()` con provider/model resueltos; medir `usage`.
4. Persistir run (`success|failed`), upsert de sugerencias (`updateOrCreate` por `user_id+type+title`, `type = "agent:{key}"`), `next_run_at`, `failure_count` (reset en éxito, +1 y backoff `min(2^failures, 1440)` minutos en fallo).
5. Notificación: si `notify` y hay conexión Telegram habilitada → enviar fuera del tool path (sin aprobación); `notified_at`.

### Tool `manage_agents` (solo agente main)

Acciones: `list`, `create`, `update`, `delete`, `enable`, `disable`. Params: `action` (enum), `key`, `name`, `description`, `instructions`, `schedule_type`, `schedule_value`, `tools_policy`, `enabled`. Límites: máx. 10 agentes por usuario; `key` slug; schedule validado. Es una escritura **interna** (config del cockpit): no pasa por aprobaciones, igual que `ActionTool`. El `RuntimeAgent` no expone esta tool (política).

### Comandos programados

- `agents:dispatch-due` → `everyMinute()` en `routes/console.php`.
- (existente) `integrations:expire-approvals`, `integrations:prune-activity`.

### Rutas y UI

| Método | URI | Action |
|---|---|---|
| GET | `agents` | index (Inertia `agents/index`) |
| GET | `agents/{agent}` | show (Inertia `agents/show`, runs + sugerencias) |
| POST | `agents` | store |
| PATCH | `agents/{agent}` | update |
| DELETE | `agents/{agent}` | destroy |
| POST | `agents/{agent}/run` | run manual (`throttle:6,1`, dispatch `TriggeredBy=manual`) |
| POST | `agents/{agent}/toggle` | enable/disable |

- Páginas: lista de agentes (estado, próximo run, último resultado, toggle) + wizard (nombre, instrucciones, schedule, herramientas permitidas) + detalle con historial de runs (report, usage, sugerencias, aprobaciones generadas).
- Sidebar: item **Agentes** (`Bot`) en la sección "Asistente IA".
- Chat: selector de agente en el composer (default `megalomaniac`); `ChatService` resuelve `RuntimeAgent` si el key es de un `AgentDefinition` del usuario.

## Testing

Pest 4, `tests/Feature/Agents/`:
- Modelos: casts, scopes (`due`, `enabled`), unique por usuario, policies (404 ajeno).
- `AgentDefinitionService`: validación de schedule (intervalos válidos, cron inválido), límite de 10, slug `key`.
- `AgentScheduler`: `nextRunAt` por intervalo y cron (con `travel`), `dispatchDue` solo despacha due/habilitados/no running, respeta presupuesto diario.
- `AgentRunner`: `RuntimeAgent::fake()` + structured output fake; success/failed/skipped (sin proveedor), backoff, upsert de sugerencias, `approvals_created`, notificación Telegram con `Http::fake`.
- Tool `manage_agents`: create/update/list/delete, límites, y que el `RuntimeAgent` **no** la expone.
- UI Inertia: index/store/update/toggle/run/destroy, scoping, forms.
- Chat: hilo con agente custom usa sus instrucciones (fake del SDK).

## Fuera de alcance (SP2)

Meta-agentes, branching de runs, dashboard de costos, mensajería agente-a-agente, aprobaciones configurables por herramienta (rige SP1), edición de agentes desde agentes background, ejecución paralela del mismo agente.

## Compatibilidad

- **SP1**: aprobaciones (`source=schedule`), tools de integración con allowlist, auditoría existente.
- **SP3**: el feed puede alimentar el contexto del runner en el futuro (hoy no).
- **SP4**: si un agente tiene permitido `storage`, sus tools aparecen cuando SP4 exista (misma allowlist de kinds).
- **Chat Spec C**: este spec lo implementa (selector de agente en el chat) sin tocar `space_id`.
