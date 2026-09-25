# Integraciones Core (MCP/APIs) — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Alcance: Sub-proyecto 1 de 4 (1 Integraciones → 2 Agentes background → 3 Feed de noticias → 4 Storage multi-proveedor)

## Problema

El cockpit no puede operar los servicios externos del usuario (GitHub, Google, infra, media, comunicación, contenido, música, archivos). Cada servicio nuevo hoy implicaría un servicio + tools a mano (patrón que no escala a ~26 integraciones) y no existe una política de escrituras ni auditoría de acciones externas.

## Objetivo

Framework de integraciones nativas que permita: conectar servicios con credenciales cifradas, descubrir y ejecutar acciones desde el agente IA, exigir aprobación humana para escrituras/destructivas, auditar todo, y agregar un conector nuevo con una sola clase. Lectura libre; escritura con aprobación.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Orden del programa: 1 Integraciones → 2 Agentes background → 3 Feed → 4 Storage. |
| 2 | Conectores **nativos Laravel** (no cliente MCP, no solo harness). Sin dependencias nuevas de Composer. |
| 3 | Catálogo v1 de **26 kinds** con olas de implementación (ver §Alcance). |
| 4 | Autonomía: **lectura libre; escritura/destructivo con aprobación** en bandeja UI. Cada conector declara `access` por acción. |
| 5 | `opencode` = **orquestador de código** contra `opencode serve` (sessions, prompts async, diffs, permisos). No lo spawnea la app en v1. |
| 6 | WhatsApp = **bridge no oficial (Baileys)** como microservicio Node, con throttling y sin envíos masivos. |
| 7 | Música = **Tidal lectura (API no oficial, aislada) + ListenBrainz** (scrobbles/top). **Spotify fuera de v1.** |
| 8 | Topología: **mixta por conexión** (`direct`, `local_socket`, `ssh_tunnel`, `ssh_exec`). SSH solo con llave (sin password). |
| 9 | Enfoque arquitectónico **A**: registro de conectores + acciones declarativas + executor central + 2 tools de IA. |
| 10 | Aprobaciones de escritura siempre (sin "recordar" en v1); expiran a las 72 h. |
| 11 | Notion se conecta con **internal integration token** (no OAuth). Reddit OAuth con client creds por conexión. Google OAuth con client creds de app (`.env`). |
| 12 | Sin commit automático del spec: queda en working tree para revisión. |

## Alcance

### Framework (no es conector)

`Connector` / `Action` / `Param` / `Connection` / `ConnectorRegistry` / `IntegrationExecutor` / `IntegrationActionLog` / `ApprovalRequest` + transportes + broker OAuth2 + UI de conexiones/aprobaciones/actividad.

### Catálogo v1 (26 kinds)

| Grupo | Kinds | Auth | Acciones núcleo | Access |
|---|---|---|---|---|
| Dev | `github`, `opencode` | PAT / server + basic | repos, issues, PRs, Actions / sesiones, prompts async, diffs, permisos | read + write |
| Google | `google` (Drive + Gmail + Calendar) | OAuth2 (app creds) | list/search/read, upload/move, enviar mail, eventos | read + write + destructivo |
| Infra | `docker`, `proxmox`, `cloudflare`, `tailscale`, `truenas`, `ssh` | token/socket/llave | contenedores y logs, VMs/LXC, DNS, devices, pools, exec | read + write + destructivo |
| Media | `sonarr`, `radarr`, `prowlarr`, `jellyseerr` (driver `arr` común), `qbittorrent` (cookie login propio), `jellyfin` (API key propia) | API key | biblioteca, cola, calendario, agregar/pausar/borrar | read + write + destructivo |
| Hogar | `home_assistant` | token | estados, llamar servicios, escenas | read + write |
| Comunicación | `telegram`, `whatsapp` (bridge Node/Baileys) | bot token / QR bridge | enviar mensaje/media, recibir, comandos | write |
| Contenido | `notion`, `rss`, `reddit`, `youtube` | token/OAuth/key | query/páginas, feeds, subreddits, suscripciones | read + write |
| Música | `tidal` (no oficial, aislado), `listenbrainz` | device OAuth / token | búsqueda + playlists / scrobbles y top | solo lectura |
| Archivos | `nextcloud` (WebDAV), `caldav` | basic/token | listar, subir, bajar, calendario | read + write |

### Olas de implementación

Cada ola termina en software funcionando y testeado, con su propio plan de implementación.

- **Ola 0:** framework completo + `github` (token) + `google` (OAuth) + `docker` (socket/SSH). Prueban los 3 ejes de auth/transporte.
- **Ola 1:** familia `arr` + `proxmox` + `home_assistant`.
- **Ola 2:** `telegram`, `notion`, `rss`, `reddit`, `youtube`, `listenbrainz`.
- **Ola 3:** `whatsapp` (bridge Node), `opencode`, `tidal`, `caldav`/`nextcloud`.
- **Ola 4:** `cloudflare`, `tailscale`, `truenas`, `ssh`.

Este spec cubre el framework y el catálogo; el plan de `writing-plans` que sigue implementa la **Ola 0**. Las olas 1-4 tendrán planes propios sobre este mismo spec.

## Arquitectura (Enfoque A)

```
Agente IA ──► IntegrationCallTool ─┐
UI (click) ────────────────────────┼──► IntegrationExecutor ──► Connector::execute()
Scheduler (sub-proyecto 2) ────────┘         │                       │
                                             │                       ▼
                                  read ──────┤                Transport (direct /
                                             │                 local_socket /
                                  write ─────▼                 ssh_tunnel / ssh_exec)
                                  ApprovalRequest ──► UI bandeja ──► RunIntegrationActionJob
                                             │
                                             ▼
                                  IntegrationActionLog (auditoría redactada)
```

### Contratos

```php
namespace App\Integrations\Contracts;

interface Connector
{
    public function kind(): string;                 // 'github'
    public function label(): string;                // 'GitHub'
    /** @return AuthField[] */
    public function authFields(): array;            // form dinámico de credenciales
    /** @return string[] */
    public function transports(): array;            // ['direct','ssh_tunnel']
    /** @return Action[] */
    public function actions(): array;
    public function execute(Connection $connection, string $key, array $params): ActionResult;
    public function test(Connection $connection): ConnectionTestResult;
}
```

```php
namespace App\Integrations\Actions;

final readonly class Action
{
    public function __construct(
        public string $key,              // 'issues.create' (namespace: kind desde el conector)
        public string $label,            // 'Crear issue'
        public string $description,      // texto que ve el LLM
        public ActionAccess $access,     // Read|Write|Destructive
        /** @var Param[] */ public array $params = [],
        public array $examples = [],
        public ?string $returns = null,  // descripción del retorno para el LLM
    ) {}
}

final readonly class Param
{
    public function __construct(
        public string $name,
        public string $type,             // string|integer|number|boolean|array
        public bool $required,
        public string $description,
        public ?array $enum = null,
        public mixed $default = null,
        public bool $sensitive = false,  // se redacta en logs
    ) {}
}

enum ActionAccess: string
{
    case Read = 'read';
    case Write = 'write';
    case Destructive = 'destructive';

    public function requiresApproval(): bool { return $this !== self::Read; }
}
```

- `Param[]` genera **reglas de validación** (`ParamRules`) y **JSON-schema** (`ParamSchema`) para el tool del agente. El conector no depende de `laravel/ai` ni de `laravel/mcp`.
- `ActionResult`: `ok` bool, `summary` string, `data` ?array, `error` ?string.
- `ConnectionTestResult`: `ok` bool, `message` string, `meta` array.
- `AuthField`: `name`, `type` (`text|password|url|select|oauth|qr`), `label`, `required`, `help`, `options`.
- `ExecutionContext`: morph `actor` + `source` (`chat|ui|approval|schedule`) + `bool $preApproved`.
- Enums: `ActionAccess`, `ApprovalStatus`, `ConnectionStatus`, `AuthType`, `TransportKind`.

### Registro e inyección

- `ConnectorRegistry` mapea `kind → Connector` (singleton; los conectores `resolve` reciben `Connection` por parámetro, no por constructor).
- Registro declarado en `config/integrations.php` (`connectors` => [GithubConnector::class, ...]) para que un kind nuevo sea una línea + una clase.

## Modelo de datos

### Migración `create_connections_table`

```
id, user_id FK cascade
kind            string(50)  index
name            string(100)
auth_type       string(30)              // api_token|basic|oauth2|none|qr
credentials     text        nullable    // encrypted:array
base_url        string(500) nullable
transport       string(20)  default 'direct'
transport_config text       nullable    // encrypted:array
options         json        nullable
enabled         boolean     default true
status          string(20)  default 'unknown'
status_message  text        nullable
last_tested_at  timestamp   nullable
last_used_at    timestamp   nullable
timestamps
index (user_id, kind, enabled)
```

### Migración `create_approval_requests_table`

```
id, connection_id FK cascade, user_id FK cascade
action_key      string(100)
params          json
access          string(15)
summary         string(255)
rationale       text        nullable
status          string(20)  default 'pending'   // pending|approved|rejected|expired|executed|failed
requested_by_type / requested_by_id  nullableMorphs
decided_at      timestamp   nullable
decided_by      FK users    nullable
decision_note   text        nullable
executed_at     timestamp   nullable
expires_at      timestamp   nullable
timestamps
index (user_id, status, created_at)
```

### Migración `create_integration_action_logs_table`

```
id, connection_id FK cascade, user_id FK cascade
approval_request_id FK approval_requests nullable cascadeOnDelete
action_key      string(100)
access          string(15)
actor_type / actor_id              nullableMorphs
source          string(20)         // chat|ui|approval|schedule
params          json               // redactado
result_summary  text        nullable
status          string(20)          // success|failed|denied|pending|running
error           text        nullable
duration_ms     unsignedInteger nullable
timestamps
index (user_id, created_at), index (connection_id, created_at), index (action_key, status)
```

> Sin FK circular: la relación aprobación↔log se navega por `integration_action_logs.approval_request_id`.

### Modelos

- `App\Models\Connection`: `casts()` → `credentials`/`transport_config` `encrypted:array`, `options` array, `enabled` bool, fechas datetime, `status`/`auth_type`/`transport` enums. Relaciones `user()`, `actionLogs()`, `approvals()`. Scopes `forUser`, `enabled`.
- `App\Models\ApprovalRequest`: casts `params` array, `status` enum, fechas. Relaciones `connection()`, `user()`, `log()`, `requester()` morph. Scopes `pending`, `forUser`.
- `App\Models\IntegrationActionLog`: casts `params` array, fechas. Relación `connection()`, `approval()`.

### Config `config/integrations.php`

`connectors` (registro), `approval.ttl_hours` (72), `limits.read_per_minute` (60), `limits.write_per_minute` (10), `ssh.connect_timeout` (5), `ssh.command_timeout` (15), `ssh.output_cap_bytes` (262144), `retention_days` (90), `redaction.patterns`.

## Transportes

`App\Integrations\Transports\Transport` (interface): `request(Connection, HttpCall): HttpResult`, `exec(Connection, string $command): ExecResult`, `health(Connection): ConnectionTestResult`.

| Transporte | Uso | Implementación |
|---|---|---|
| `DirectTransport` | APIs HTTP/públicas y LAN | `Http` client (token/basic/headers desde credentials) |
| `LocalSocketTransport` | Docker local | HTTP sobre unix socket vía cURL `CURLOPT_UNIX_SOCKET_PATH` |
| `SshTunnelTransport` | Cualquier HTTP detrás de un host SSH | `ssh -N -L 127.0.0.1:{free}:{remote}` por request vía Symfony Process; poll de readiness (5 s); `finally` mata el proceso. `BatchMode=yes`, `ExitOnForwardFailure=yes`, `ConnectTimeout=5`, `StrictHostKeyChecking` (`accept-new` o `strict` + `known_hosts_path`) |
| `SshExecTransport` | CLI remoto (Docker CLI, SSH host, TrueNAS) | `ssh host -- comando` con escape de args; allowlist por conector; timeout y cap de salida |

- Llave SSH: `key_path` (ruta en el host de la app) o `private_key` (contenido cifrado → archivo temporal `0600`, borrado en `finally`).
- `ssh_tunnel` reutiliza el transporte `direct` una vez abierto el forward (misma pila HTTP).
- Timeouts y caps desde config; errores mapeados a `ActionResult`/`ConnectionTestResult`, nunca excepciones crudas hacia el agente.

## OAuth broker

- `OAuthBroker` + `OAuthPreset` (`google`, `reddit`, `tidal`, `youtube` comparte preset google).
- Flujo: `GET integrations/oauth/{connection}/redirect` → authorize URL con `state` (cache 10 min) + PKCE donde aplique → callback → exchange → `OAuthToken` (access, refresh, expires_at, scopes) cifrado en `connections.credentials`.
- Refresh automático con margen de 60 s; fallo → `status=expired` + `status_message`.
- Google: scopes `drive`, `gmail.readonly`, `gmail.send`, `calendar.events`; client id/secret de app en `config/services.php` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) y redirect `integrations/oauth/google/callback`. Modo Testing con test users (sin verificación).
- Reddit: client id/secret **por conexión** (`authFields`); Tidal: device flow con client id de app; Notion: token interno (sin OAuth).

## Executor y aprobaciones

`IntegrationExecutor::execute(Connection $c, string $actionKey, array $params, ExecutionContext $ctx): ActionResult`

1. Resuelve acción en el conector; 404/`invalid_action` si no existe; `disabled` si la conexión está apagada.
2. Valida `params` (reglas desde `Param[]`); error de validación → `ActionResult(ok:false)` con detalle (no excepción).
3. `access = Read` → ejecuta ya (sync) y responde.
4. `access = Write|Destructive`:
   - `ctx->source === 'ui'` → aprobación implícita (`decided_by = user`) y despacha `RunIntegrationActionJob`.
   - agente/schedule → crea `ApprovalRequest` (`pending`) + `IntegrationActionLog` (`pending`) y devuelve `{status: pending_approval, approval_id, summary}`.
5. Ejecución real (sync o en job): aplica rate limit por conexión (cache), llama `Connector::execute`, mide `duration_ms`, redacta (`sensitive` + patrones), escribe log (`success|failed`), actualiza `status`/`last_used_at`.
6. `ApprovalRequest::approve()` → despacha job y marca `approved`; al terminar `executed|failed`. `reject()` → log `denied`.

**Comandos programados** (registrados en `routes/console.php`):
- `integrations:expire-approvals` cada 10 min (pending vencidas → `expired`).
- `integrations:prune-activity` diario (logs > `retention_days`).

## Superficie IA

Dos tools en `app/Ai/Tools/`, agregadas a `MegalomaniacAgent::tools()`:

| Tool | Contrato |
|---|---|
| `IntegrationCatalogTool` | Lista conexiones habilitadas + acciones disponibles (`key`, `label`, `description`, `access`, params). Filtros opcionales `connection` y `search`. |
| `IntegrationCallTool` | Schema `{connection: string, action: string, params: object}`. Ejecuta vía executor con `source=chat`. Read → resultado inline. Write/destructive → `pending_approval` con `approval_id` y `summary`. |

- El catálogo se genera por usuario desde el registry; no hay prompts hardcodeados por conector.
- Registro de la acción en el log con `actor = agente`.
- Espejo en el MCP server propio (`MegalomaniacServer`) para clientes externos: **fuera de alcance v1** (se evalúa tras la Ola 2).

### Flujo end-to-end (ejemplo)

1. "Reiniciá el contenedor de jellyfin".
2. Agente → `integration_call(connection: "docker-local", action: "containers.restart", params: {name: "jellyfin"})`.
3. Executor crea `ApprovalRequest #42` (destructive) → tool devuelve `pending_approval`.
4. Agente: "Preparé la acción; aprobala en Aprobaciones".
5. UI Aprobaciones → Aprobar → job → `executed`; Rechazar → `denied` con nota.

## UI

### Rutas y páginas

- `settings/connections.tsx` (Settings → **Conexiones**): lista por grupo del catálogo, `StatusBadge` (ok/error/expired/unknown), última prueba/uso, acciones Probar/Editar/Activar/Eliminar; CTA "Nueva conexión". Wizard en `Sheet`/`Dialog`: paso 1 elegir kind (grid con ícono), paso 2 credenciales dinámicas (`AuthField` + botón Conectar OAuth), paso 3 transporte (`TransportKind` + campos), paso 4 Probar y Guardar.
- `integrations/approvals.tsx` (sidebar **Aprobaciones**, icono `ShieldCheck`, badge con pendientes): cards con `summary`, conexión, `rationale`, tabla params, Aprobar/Rechazar con nota, expiración visible; tabs Pendientes/Historial.
- `integrations/activity.tsx`: tabla densa filtrable (conexión, acción, access, status, duración, actor, fecha) + detalle con params/result redactados.

### Componentes (`resources/js/components/integrations/`)

`ConnectionCard`, `ConnectionWizard`, `AuthFieldsForm`, `TransportFields`, `StatusBadge`, `ApprovalCard`, `ActionLogTable`, `EmptyState`, `GroupSection`.

### Convenciones y estados

- `MainLayout` + `SettingsLayout` (agregar "Conexiones" a `sidebarNavItems`), `Heading`, `Button`, `Input`, `Label`, `InputError`, `Separator`, `Dialog`/`Sheet`/`Badge` de `components/ui`; iconos `lucide-react`; tokens Ember (`bg-card`, `border-border`, `text-muted-foreground`, `text-primary`).
- Estados: loading (skeletons), vacío (copy + CTA), error (banner reintentar), success (flash), disabled (test en curso / sin credenciales), recovery (reconectar OAuth vencido). Responsive: wizard full-screen sheet en mobile, lista apilada; sin overlays clipeados.
- **ui-radar:** 2 consultas al catálogo libre de UIZZE (`integrations settings connections list`, `connected apps accounts`) devolvieron `results: []` → sin referencias externas; se sigue el design system existente (Ember + componentes shadcn del repo).

## Seguridad

- Credenciales y `transport_config` con cast `encrypted:array`; nunca en respuestas ni logs (redacción central + flag `sensitive`).
- Solo esquemas `http`/`https`; bloqueo de metadata link-local `169.254.169.254`; IPs privadas permitidas por diseño (LAN), documentado.
- SSH: solo llave (sin password), `BatchMode`, timeout, cap de salida, allowlist de comandos por conector.
- OAuth: tokens cifrados, refresh con margen, `state` + PKCE.
- Aprobación obligatoria para write/destructive desde agente/schedule; sin "recordar" en v1; TTL 72 h.
- Notificaciones del sistema (ej. run terminado → Telegram, sub-proyecto 2) no pasan por el tool del agente y por lo tanto no piden aprobación.
- Policies `ConnectionPolicy` y `ApprovalRequestPolicy` (owner); rutas en grupos `auth` (+`verified` para bandeja/actividad).
- Throttle `throttle:30,1` en approve/reject; rate limit por conexión en el executor.

## Testing

Pest 4 · `tests/Unit/Integrations/` y `tests/Feature/Integrations/`:

- **Contratos:** cada conector implementa `Connector`; `kind` único; `actions()` con keys únicas; `Param[]` → reglas/schema válidos; `access` declarado; `test()` sin red (fake).
- **Executor:** read ejecuta; write/destructive de agente encola aprobación; write desde UI ejecuta; aprobar ejecuta y rechazar deniega; validación de params; conexión deshabilitada; acción desconocida; redacción de `sensitive`; rate limit; actualización de `status`/`last_used_at`.
- **Transportes:** `direct` con `Http::fake`; `local_socket` arma opciones cURL; `ssh_tunnel`/`ssh_exec` con `Process::fake` (comando, timeouts, kill); cap de salida.
- **OAuth:** redirect (URL + state + PKCE), callback (exchange fake + cifrado), refresh, expiración.
- **Conectores Ola 0:** `github` y `google` con `Http::fake` + fixtures; `docker` con socket/ssh fake; `test()` de cada uno.
- **Comandos:** expiración de aprobaciones y prune de actividad.
- **Inertia:** `connections` index/store/update/test/destroy, `approvals` index/approve/reject, `activity` index (props + scoping + 404 ajeno).
- **Frontend:** `npm run types`, `npm run lint`, `npm run build`.
- **QA manual:** Playwright MCP en `:8010` (login `test@example.com/password`) → wizard de conexión, probar GitHub/Docker, aprobar/rechazar un write, ver actividad.

## Rutas y archivos

```
routes/settings.php             + settings/connections (index/create/store/edit/update/destroy/test)
routes/integrations.php (nuevo, require desde web.php)
                                GET  integrations/approvals
                                POST integrations/approvals/{approval}/approve|reject
                                GET  integrations/activity
                                GET  integrations/oauth/{connection}/redirect|callback
app/Integrations/Contracts/Connector.php
app/Integrations/Actions/{Action,Param,ActionResult,AuthField,ConnectionTestResult,ExecutionContext}.php
app/Integrations/Enums/{ActionAccess,ApprovalStatus,ConnectionStatus,AuthType,TransportKind}.php
app/Integrations/{ConnectorRegistry,IntegrationExecutor}.php
app/Integrations/Connectors/AbstractConnector.php
app/Integrations/Support/SecretRedactor.php
app/Integrations/OAuth/{OAuthBroker,OAuthPreset,OAuthToken}.php
app/Integrations/Transports/{Transport,TransportFactory,HttpCall,HttpResult,ExecResult,DirectTransport,LocalSocketTransport,SshTunnelTransport,SshExecTransport}.php
app/Integrations/Connectors/{Github,Google,Docker}/
app/Ai/Tools/{IntegrationCatalogTool,IntegrationCallTool}.php
app/Jobs/RunIntegrationActionJob.php
app/Console/Commands/{ExpireApprovalsCommand,PruneIntegrationActivityCommand}.php
app/Models/{Connection,ApprovalRequest,IntegrationActionLog}.php
app/Http/Controllers/Integrations/{ConnectionController,ApprovalController,ActivityController,OAuthController}.php
app/Http/Requests/Integrations/{StoreConnectionRequest,UpdateConnectionRequest,DecideApprovalRequest}.php
app/Policies/{ConnectionPolicy,ApprovalRequestPolicy}.php
config/integrations.php
resources/js/pages/settings/connections.tsx
resources/js/pages/integrations/{approvals,activity}.tsx
resources/js/components/integrations/*
```

## Fuera de alcance (sub-proyecto 1)

- Espejo de integraciones en el MCP server propio para clientes externos.
- Aprobaciones "recordar por 1 h", acciones programadas (llegan con el sub-proyecto 2).
- Transferencia binaria de archivos (llega con el sub-proyecto 4: storage); en v1 los downloads devuelven texto/metadata.
- Conectores definidos por el usuario (HTTP genérico), marketplace de conectores, webhooks entrantes de terceros (excepto callbacks OAuth y el bridge de WhatsApp en Ola 3).
- Spotify.
- Playback de Tidal (DRM).

## Compatibilidad con sub-proyectos 2-4

- `ApprovalRequest`, `IntegrationExecutor` y el log son la base para que los agentes background ejecuten acciones (sub-proyecto 2) con `source=schedule`.
- El conector `rss` y los tokens OAuth (Google/Reddit/YouTube/ListenBrainz) alimentan el feed de noticias (sub-proyecto 3).
- El broker OAuth y `Connection` se reutilizan como credenciales de los proveedores de storage (Google Drive, Nextcloud) del sub-proyecto 4.

## Verificación / QA

- `php artisan test --compact` (suite completa) tras cada tarea; `vendor/bin/pint --dirty --format agent`.
- `npm run types && npm run lint && npm run build`.
- QA con Playwright MCP en `:8010` (login `test@example.com/password`).

## Referencias

- ui-radar: catálogo libre de UIZZE consultado (2 queries) sin resultados → sin referencias visuales externas; se sigue el design system Ember (`docs/design-tokens.md`) y los componentes shadcn/radix del repo.
- `laravel/ai` v0.11 (`Illuminate\Contracts\JsonSchema\JsonSchema`, tools `Laravel\Ai\Contracts\Tool`), patrón existente de `ActionTool`/`WorkoutQueryTool`.
- `laravel/mcp` v0.9 (servidor propio ya existente en `routes/ai.php`).
- opencode server HTTP (`https://opencode.ai/docs/server/`): sessions, messages, diffs, permissions.
- Specs previos: `2026-08-25-megalomaniac-ai-api-mcp-design.md`, `2026-09-24-ai-chat-core-design.md` (Spec A; B/C pendientes).
