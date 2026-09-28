# MCPs Custom en Conexiones — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Extiende SP1 (Integraciones) con un conector `mcp` genérico.

## Problema

Solo hay conectores nativos (API key/OAuth propios). Muchos servicios ya exponen un **MCP server** (Notion, Linear, Sentry, GitHub remoto, etc.): agregarlo debería ser una conexión más, sin configurar API keys ni escribir un conector.

## Objetivo

Tipo de conexión **"MCP personalizado"** (HTTP remoto) que descubre las tools/resources/prompts del server, las expone al agente como acciones (lectura libre, escritura/destructivo con aprobación SP1) y soporta **OAuth con DCR+PKCE** (caso Notion) o bearer token.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Solo transporte **HTTP remoto** en v1 (stdio queda para fase 2). |
| 2 | Al descubrir, **todas las tools habilitadas** con toggles; escrituras siempre por aprobación. |
| 3 | Alcance v1: **tools + resources + prompts**. |
| 4 | Solo tipo **genérico** (sin preset Notion); se documenta el ejemplo. |
| 5 | Cero deps nuevas: se usa el **client de `laravel/mcp` v0.9** (`WebClient`, `HttpTransport`, OAuth/DCR/PKCE, `Tool`/`ToolResult`). |
| 6 | OAuth propio con rutas `integrations/mcp/{connection}/connect|callback` (no `OAuthRouteRegistrar`, que usa nombres estáticos). |
| 7 | `tools_policy.integrations` de SP2 acepta además **nombre/ID de conexión** (todos los MCP comparten kind `mcp`). |

## Arquitectura

```
Conexión kind=mcp ──► McpConnector ──► McpClientFactory ──► WebClient(HttpTransport)
                        │                    │
                        │                    └─ McpTokenStore (refresh OAuth, cifrado en credentials)
                        ├─ actionsFor(connection) → McpDiscoveryService (tools/resources/prompts, cache 10 min)
                        └─ execute() → callTool / readResource / getPrompt ──► executor SP1 (aprobaciones/auditoría)
```

- **`ConnectionAwareConnector`** (nueva interfaz opcional): `actionsFor(Connection): Action[]`. El executor/catálogo usan `actionsFor()` si el conector la implementa; el resto sigue con `actions()`. Evita tocar los 24 conectores existentes.
- **`McpConnector`** (`kind: mcp`, grupo `MCP`, HTTP): `actionsFor()` = genéricas (`mcp.info`, `resources.list`, `resources.read`, `prompts.list`, `prompts.get`) + una `Action` por tool habilitada (`tools.{name}`).
- **`McpToolMapper`**: JSON Schema → `Param[]`; `annotations.readOnlyHint` → Read, `destructiveHint` → Destructive, resto → Write (aprobación).
- **`McpDiscoveryService`**: `tools()/resources()/prompts()` con cache key que incluye `updated_at` de la conexión (auto-invalidación al guardar) + `forget()`.
- **`McpTokenStore`**: persiste `TokenSet` (access/refresh/expires/client_id/client_secret de DCR) en `connections.credentials` (cifrado) y refresca con margen de 60 s antes de cada request.
- **`McpOAuthController`**: connect (redirect a authorization_endpoint, con DCR si no hay client_id) y callback (`exchangeCallback()` → `McpTokenStore`).
- **Errores**: `AuthorizationRequiredException` (401/403 con `WWW-Authenticate`) → `test()` devuelve `authorization_required` con `resource_metadata`/`scope` para el botón Conectar; `SessionExpiredException` (404 post-sesión) → reconectar y reintentar una vez.

## Acciones dinámicas

| key | access | params |
|---|---|---|
| `tools.{name}` | según annotations | `Param[]` desde `inputSchema` |
| `mcp.info` | read | — |
| `resources.list` | read | — |
| `resources.read` | read | `uri*` |
| `prompts.list` | read | — |
| `prompts.get` | read | `name*`, `arguments` (array) |

Cap de resultado: 64 KB (texto) y `structuredContent` preferido; `isError` → failure.

## Gobernanza

- `options.tools`: allowlist de tools habilitadas (default: todas al descubrir); `PATCH` la actualiza e invalida cache.
- Escrituras/destructivas del MCP → `ApprovalRequest` (SP1) igual que cualquier conector.
- SP2: `tools_policy.integrations` acepta kind, **nombre o ID** de conexión.
- Seguridad: timeout 20 s default, tokens nunca en logs (redacción existente), URLs privadas permitidas por diseño (igual que el resto).

## UI

- **Wizard**: grupo MCP con "MCP personalizado" (URL = base_url; authFields: `token` opcional, `client_id`/`client_secret`/`scope` opcionales).
- **Card**: botón **Conectar** para kind `mcp` (además de `oauth2`); estado "Requiere autorización" con CTA.
- **Panel "Herramientas MCP"** (editar conexión): tools/resources/prompts descubiertos, toggles por tool, badge de acceso, botón Redescubrir; errores legibles.
- **Chat**: menú **Prompts** en el composer (prompts de MCPs conectados) → mini-diálogo de argumentos → inserta la plantilla renderizada en el composer.
- ui-radar: 2 consultas al catálogo libre sin resultados → sin referencias externas; se sigue el design system Ember y los componentes existentes.

## Testing

`tests/Feature/Integrations/Mcp/`: mapper (schema/annotations), discovery con `Http::fake` (fixtures MCP JSON-RPC), connector (tools/resources/prompts, cap, errores), token store (persist/refresh), OAuth connect/callback (DCR + state), executor/catálogo (`ConnectionAwareConnector`), allowlist por nombre (SP2), UI (panel/prompts) + types/build. QA real apuntando a **nuestro propio MCP server** (`/mcp/megalomaniac`) con un PAT.

## Fuera de alcance

stdio, OAuth de servidores sin DCR y sin client_id manual (se soporta cargando client_id/secret a mano), MCP UI apps (resources interactivos), prompts como slash-commands nativos (v1: inserción de plantilla), notificaciones de cambio de tools (`notifications/tools/list_changed`).

## Docs

`docs/modules/mcp.md` con ejemplo Notion (`https://mcp.notion.com/mcp`, OAuth, tools típicas).
