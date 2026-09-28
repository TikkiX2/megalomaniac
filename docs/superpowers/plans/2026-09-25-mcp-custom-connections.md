# MCPs Custom en Conexiones — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Conector genérico `mcp` (HTTP) con tools/resources/prompts dinámicos, OAuth DCR+PKCE, toggles y gobernanza SP1.

**Architecture:** `ConnectionAwareConnector` + `McpConnector` + `McpDiscoveryService` + `McpClientFactory` + `McpTokenStore` + `McpOAuthController`; UI en el wizard/card existentes + panel de herramientas + menú de prompts en el chat.

**Spec:** `docs/superpowers/specs/2026-09-25-mcp-custom-connections-design.md`

## Global Constraints

- Cero deps nuevas; se usa el client de `laravel/mcp` (`Laravel\Mcp\WebClient`, `Client\Transport\HttpTransport`, `Client\OAuth\*`, `Client\Primitives\Tool`, `Client\Schema\ToolResult`).
- `HttpTransport` usa la facade `Http` → tests con `Http::fake()`.
- No romper conectores existentes: `actionsFor()` es una interfaz **opcional**.
- Escrituras/destructivas → aprobaciones SP1; tokens cifrados y nunca logueados.
- Pint por tarea; commits locales (sin push) hasta que el usuario lo pida.

---

### Task 1: Contratos + mapper + discovery + factory + token store

**Files:** `app/Integrations/Contracts/ConnectionAwareConnector.php`; `app/Integrations/Mcp/{McpToolMapper,McpDiscoveryService,McpClientFactory,McpTokenStore}.php`; `tests/Feature/Integrations/Mcp/McpCoreTest.php`.

**Interfaces:**
- `ConnectionAwareConnector { public function actionsFor(Connection $connection): array; }`
- `McpToolMapper::toAction(Tool): Action` (key `tools.{name}`, access por annotations) y `::params(array $schema): Param[]`.
- `McpDiscoveryService::tools(Connection): Collection`, `::resources()`, `::prompts()`, `::forget(Connection)`; cache 10 min con `updated_at` en la key.
- `McpClientFactory::base(Connection): WebClient` (HttpTransport + headers + timeout) y `::for(Connection): WebClient` (+ token bearer).
- `McpTokenStore::store(Connection, TokenSet): Connection`, `::ensureFresh(Connection): Connection`.

- [ ] Tests: mapper (tipos/enum/required/annotations), factory (token/headers/timeout), token store (persist cifrado, refresh con `Http::fake`), discovery cache → RED → implementar → GREEN.

---

### Task 2: McpConnector (tools + resources + prompts)

**Files:** `app/Integrations/Connectors/Mcp/McpConnector.php`; `tests/Feature/Integrations/Mcp/McpConnectorTest.php`.

**Interfaces:** kind `mcp`, grupo `MCP`, authFields (`token`, `client_id`, `client_secret`, `scope`). `actionsFor()` = genéricas + tools habilitadas (`options.tools`); `actions()` = genéricas. `execute()`:
- `tools.{name}` → `callTool()` (respeta allowlist; `isError` → failure; `structuredContent`/texto con cap 64 KB).
- `resources.list/read`, `prompts.list/get`, `mcp.info`.
- `ensureFresh` antes; `AuthorizationRequiredException` → failure con `authorization_required`; `SessionExpiredException` → reintento único.
- `test()`: counts o `authorization_required` con `query()` del challenge.

- [ ] Tests con `Http::fake` (handshake initialize + tools/list + tools/call + resources + prompts; 401 challenge; sesión expirada; cap) → RED → implementar → GREEN.

---

### Task 3: OAuth connect/callback + rutas

**Files:** `app/Http/Controllers/Integrations/McpOAuthController.php`; `routes/integrations.php` (+2 rutas); `tests/Feature/Integrations/Mcp/McpOAuthTest.php`.

- [ ] Tests: connect redirige al authorization_endpoint con PKCE/state/DCR (`Http::fake` de well-known + register), callback exchange persiste tokens cifrados, error OAuth → flash, 404 ajeno → RED → implementar → GREEN.

---

### Task 4: Integración executor/catálogo + allowlist SP2 por nombre

**Files:** modificar `app/Integrations/IntegrationExecutor.php`, `app/Ai/Tools/{IntegrationCatalogTool,IntegrationCallTool}.php`, `app/Http/Controllers/Integrations/ConnectionController.php` (`actions` JSON usa `actionsFor`), `app/Integrations/Connectors/Mcp/McpConnector.php` (registro en config); tests en `tests/Feature/Integrations/Mcp/McpGovernanceTest.php`.

- [ ] Tests: executor resuelve acciones dinámicas y encola aprobación para write; catálogo lista tools del MCP; allowlist por nombre/ID; `GET .../actions` devuelve dinámicas → RED → implementar → GREEN.

---

### Task 5: UI (wizard/card + panel de herramientas)

**Files:** `resources/js/components/integrations/McpToolsPanel.tsx`; modificar `ConnectionCard.tsx` (Conectar para `mcp`), `ConnectionWizard.tsx` (panel en edición), `ConnectionController` (+`discover`, +`PATCH tools`), tipos; `tests/Feature/Integrations/Mcp/McpUiTest.php`.

- [ ] Tests backend (discover JSON, PATCH tools persiste e invalida, 404) → RED → implementar → GREEN; `npm run types && npm run build`.

---

### Task 6: Prompts MCP en el chat

**Files:** `app/Http/Controllers/Ai/McpPromptController.php` (+`index`, `render`), `routes/ai.php` o `web.php`, `resources/js/components/ai/chat/PromptsMenu.tsx`, `Composer.tsx`, `pages/ai/{chat,thread}.tsx`; tests.

- [ ] Tests: index lista prompts de MCPs conectados; render devuelve texto con argumentos; 404 ajeno → RED → implementar → GREEN; types/build.

---

### Task 7: QA, docs y commit

- [ ] QA Playwright: crear conexión MCP apuntando a **nuestro server** `http://127.0.0.1:8010/mcp/megalomaniac` con un PAT → Probar (counts) → panel de herramientas con toggles → (si aplica) invocar una tool de lectura vía executor; error legible con server caído.
- [ ] `docs/modules/mcp.md` (ejemplo Notion) + `docs/qa/playwright-report.md`; suite completa + Pint; commit local.

---

## Self-Review

**Cobertura:** contrato opcional, mapper, discovery, factory/tokens, connector completo, OAuth, gobernanza, UI y prompts. Sin deps. Riesgo principal: nombres de tools largos → se valida longitud ≤ 80; `actions()` sin conexión devuelve solo genéricas (el executor/catálogo siempre usan `actionsFor`).
