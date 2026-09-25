# Integraciones Ola 1 (arr · Proxmox · Home Assistant) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sumar 9 conectores (Sonarr, Radarr, Prowlarr, Jellyseerr, qBittorrent, Jellyfin, Proxmox, Home Assistant) con el framework de la Ola 0, sin tocar el executor.

**Architecture:** Cada conector extiende `AbstractConnector` y declara `Action[]`; `execute()` mapea a `HttpCall` vía el transporte `direct` (o el que corresponda). El executor de SP1 ya aporta validación, rate limit, auditoría y aprobaciones. qBittorrent necesita login por cookie y Proxmox/HA suelen usar TLS self-signed → se agrega la opción `verify` por conexión.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 4, `laravel/ai` (tools ya existentes), Symfony Process (no se usa acá).

**Spec:** `docs/superpowers/specs/2026-09-25-integraciones-core-design.md`

## Global Constraints

- Sin dependencias nuevas. Tests con `Http::fake()`; nunca red real.
- Convenciones de la Ola 0: `Action`/`Param`, `AbstractConnector::result()`, `defaultBaseUrl()`, `authFields()`, `group()`, `transports()`.
- Registro en `config/integrations.php` (una línea por conector).
- Al cerrar cada tarea: `vendor/bin/pint --dirty --format agent`.
- Commits: solo cuando el usuario lo autorice explícitamente.
- Acceso: `read` libre; `write`/`destructive` pasan por la bandeja de aprobaciones (ya implementado).

---

### Task 1: Opción `verify` (TLS) por conexión

**Files:**
- Modify: `app/Integrations/Transports/DirectTransport.php`
- Test: `tests/Feature/Integrations/HttpTransportsTest.php`

**Interfaces:**
- Consumes: `Connection.options`.
- Produces: `DirectTransport::clientOptions(Connection): array` (público, testeable); si `options['verify'] === false` devuelve `['verify' => false]`.

- [ ] **Step 1: Test que falla**

```php
it('honors a per-connection verify=false option', function () {
    $connection = Connection::factory()->make(['options' => ['verify' => false]]);

    expect((new DirectTransport)->clientOptions($connection))->toBe(['verify' => false]);
});

it('verifies tls by default', function () {
    $connection = Connection::factory()->make(['options' => null]);

    expect((new DirectTransport)->clientOptions($connection))->toBe([]);
});
```

- [ ] **Step 2: Correr y ver fallar** — `php artisan test --compact --filter="verify"` → FAIL (método no existe).
- [ ] **Step 3: Implementar**

```php
public function clientOptions(Connection $connection): array
{
    return ($connection->options['verify'] ?? true) === false ? ['verify' => false] : [];
}
```

Y en `client()`: `$request = $request->withOptions($this->clientOptions($connection));`

- [ ] **Step 4: Verde** — `php artisan test --compact tests/Feature/Integrations/HttpTransportsTest.php` → PASS.
- [ ] **Step 5:** Pint. **Commit condicional.**

---

### Task 2: `AbstractArrConnector` + Sonarr

**Files:**
- Create: `app/Integrations/Connectors/Arr/AbstractArrConnector.php`, `app/Integrations/Connectors/Arr/SonarrConnector.php`
- Test: `tests/Feature/Integrations/Connectors/SonarrConnectorTest.php`

**Interfaces:**
- Consumes: `AbstractConnector`.
- Produces: kind `sonarr`, group `Media`, auth `api_token` (campo `api_key`, header `X-Api-Key`), default base URL `http://localhost:8989`. `AbstractArrConnector` agrega `protected function arrRequest(Connection, string $method, string $path, array $query = []): HttpResult` con header `X-Api-Key`.

- [ ] **Step 1: Test que falla** (patrón de `GithubConnectorTest`)

```php
it('lists series', function () {
    Http::fake(['sonarr.local/api/v3/series*' => Http::response([['id' => 1, 'title' => 'Severance']], 200)]);

    $result = (new SonarrConnector)->execute(sonarr(), 'series.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['title'])->toBe('Severance');
    Http::assertSent(fn ($r) => $r->hasHeader('X-Api-Key', 'arr_key'));
});
```

- [ ] **Step 2: Correr y ver fallar.**
- [ ] **Step 3: Implementar** — acciones de Sonarr:

| key | método | endpoint | params | access |
|---|---|---|---|---|
| `series.list` | GET | `/api/v3/series` | — | read |
| `series.get` | GET | `/api/v3/series/{id}` | `id*` | read |
| `series.search` | GET | `/api/v3/series/lookup` | `term*` | read |
| `series.add` | POST | `/api/v3/series` | `tvdb_id*`, `quality_profile_id*`, `root_folder*`, `monitored` | write |
| `series.delete` | DELETE | `/api/v3/series/{id}` | `id*`, `delete_files` | destructive |
| `queue.list` | GET | `/api/v3/queue` | `page_size` | read |
| `calendar.list` | GET | `/api/v3/calendar` | `start`, `end` | read |
| `health.list` | GET | `/api/v3/health` | — | read |
| `system.status` | GET | `/api/v3/system/status` | — | read |
| `commands.search` | POST | `/api/v3/command` | `name*` (`MissingEpisodeSearch`, `RssSync`, …) | write |

`test()`: `GET /api/v3/system/status` → meta `version`.

- [ ] **Step 4: Verde** (contrato + 3-4 acciones + error 401).
- [ ] **Step 5:** Pint.

---

### Task 3: Radarr

**Files:** Create: `app/Integrations/Connectors/Arr/RadarrConnector.php` · Test: `tests/Feature/Integrations/Connectors/RadarrConnectorTest.php`

**Interfaces:** kind `radarr`, base `http://localhost:7878`, mismas cabeceras.

- [ ] **Step 1: Test que falla** (`movies.list` + `movies.add`).
- [ ] **Step 2-3: Implementar** — acciones:

| key | endpoint | params |
|---|---|---|
| `movies.list` | GET `/api/v3/movie` | — |
| `movies.get` | GET `/api/v3/movie/{id}` | `id*` |
| `movies.search` | GET `/api/v3/movie/lookup` | `term*` |
| `movies.add` | POST `/api/v3/movie` | `tmdb_id*`, `quality_profile_id*`, `root_folder*` |
| `movies.delete` (d) | DELETE `/api/v3/movie/{id}` | `id*`, `delete_files` |
| `queue.list` / `calendar.list` / `health.list` / `system.status` | igual que Sonarr | — |
| `commands.search` (w) | POST `/api/v3/command` | `name*` (`MoviesSearch`, `RssSync`, …) |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 4: Prowlarr

**Files:** Create: `app/Integrations/Connectors/Arr/ProwlarrConnector.php` · Test: `tests/Feature/Integrations/Connectors/ProwlarrConnectorTest.php`

**Interfaces:** kind `prowlarr`, base `http://localhost:9696`, API `v1`.

- [ ] **Step 1: Test que falla** (`indexers.list` + `search.query`).
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params |
|---|---|---|
| `indexers.list` | GET `/api/v1/indexer` | — |
| `indexers.test` (w) | POST `/api/v1/indexer/test` | `id*` |
| `search.query` | GET `/api/v1/search` | `query*`, `categories`, `limit` |
| `health.list` | GET `/api/v1/health` | — |
| `system.status` | GET `/api/v1/system/status` | — |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 5: Jellyseerr

**Files:** Create: `app/Integrations/Connectors/Arr/JellyseerrConnector.php` · Test: `tests/Feature/Integrations/Connectors/JellyseerrConnectorTest.php`

**Interfaces:** kind `jellyseerr`, base `http://localhost:5055`, header `X-Api-Key`, API `v1`.

- [ ] **Step 1: Test que falla** (`requests.list` + `requests.approve`).
- [ ] **Step 2-3: Implementar**:

| key | método | endpoint | params | access |
|---|---|---|---|---|
| `requests.list` | GET | `/api/v1/request` | `take`, `skip`, `filter` | read |
| `requests.approve` | POST | `/api/v1/request/{id}/approve` | `id*` | write |
| `requests.decline` | POST | `/api/v1/request/{id}/decline` | `id*` | write |
| `requests.create` | POST | `/api/v1/request` | `media_type*` (movie/tv), `media_id*` | write |
| `media.search` | GET | `/api/v1/search` | `query*` | read |
| `status.get` | GET | `/api/v1/status` | — | read |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 6: qBittorrent (login por cookie)

**Files:** Create: `app/Integrations/Connectors/Arr/QbittorrentConnector.php` · Test: `tests/Feature/Integrations/Connectors/QbittorrentConnectorTest.php`

**Interfaces:** kind `qbittorrent`, base `http://localhost:8080`, auth `basic` (campos `username`/`password`, default `admin`), API `/api/v2`. El conector hace login (`POST /api/v2/auth/login`, form) y reusa la cookie `SID` en las llamadas siguientes (cache 30 min por conexión).

- [ ] **Step 1: Test que falla**

```php
it('logs in and lists torrents with the session cookie', function () {
    Http::fake([
        'qb.local/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=abc123; path=/']),
        'qb.local/api/v2/torrents/info*' => Http::response([['hash' => 'h1', 'name' => 'Ubuntu']], 200),
    ]);

    $result = (new QbittorrentConnector)->execute(qbittorrent(), 'torrents.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['name'])->toBe('Ubuntu');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'torrents/info') && $r->hasHeader('Cookie', 'SID=abc123'));
});
```

- [ ] **Step 2-3: Implementar** (`login(Connection): string` con cache; acciones):

| key | endpoint | params |
|---|---|---|
| `torrents.list` | GET `/api/v2/torrents/info` | `filter`, `category` |
| `torrents.add` (w) | POST `/api/v2/torrents/add` (form `urls`) | `urls*`, `category`, `paused` |
| `torrents.pause` (w) | POST `/api/v2/torrents/pause` | `hashes*` |
| `torrents.resume` (w) | POST `/api/v2/torrents/resume` | `hashes*` |
| `torrents.delete` (d) | POST `/api/v2/torrents/delete` | `hashes*`, `delete_files` |
| `transfer.info` | GET `/api/v2/transfer/info` | — |
| `categories.list` | GET `/api/v2/torrents/categories` | — |
| `system.status` | GET `/api/v2/app/version` | — |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 7: Jellyfin

**Files:** Create: `app/Integrations/Connectors/Jellyfin/JellyfinConnector.php` · Test: `tests/Feature/Integrations/Connectors/JellyfinConnectorTest.php`

**Interfaces:** kind `jellyfin`, base `http://localhost:8096`, auth `api_token` (`api_key` → header `Authorization: MediaBrowser Token="<key>"`), `options.verify` soportado.

- [ ] **Step 1: Test que falla** (`items.list` + `sessions.list`).
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params |
|---|---|---|
| `system.info` | GET `/System/Info` | — |
| `items.list` | GET `/Items` | `types` (`Movie,Series`), `limit` |
| `items.search` | GET `/Items` | `search_term*`, `types` |
| `sessions.list` | GET `/Sessions` | — |
| `users.list` | GET `/Users` | — |
| `library.refresh` (w) | POST `/Library/Refresh` | — |
| `items.favorite` (w) | POST `/Users/{user_id}/FavoriteItems/{item_id}` | `user_id*`, `item_id*` |
| `tasks.list` | GET `/ScheduledTasks` | — |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 8: Proxmox VE

**Files:** Create: `app/Integrations/Connectors/Proxmox/ProxmoxConnector.php` · Test: `tests/Feature/Integrations/Connectors/ProxmoxConnectorTest.php`

**Interfaces:** kind `proxmox`, base `https://localhost:8006`, auth `api_token` con campos `token_id` (`USER@REALM!TOKENID`) y `token_secret`; header `Authorization: PVEAPIToken={token_id}={secret}`; `options.verify=false` recomendado (self-signed).

- [ ] **Step 1: Test que falla** (`nodes.list` + `qemu.status` + `qemu.start`).

```php
it('lists cluster nodes', function () {
    Http::fake(['pve.local/api2/json/nodes' => Http::response(['data' => [['node' => 'pve', 'status' => 'online']]], 200)]);

    $result = (new ProxmoxConnector)->execute(proxmox(), 'nodes.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['node'])->toBe('pve');
});
```

- [ ] **Step 2-3: Implementar** (respuestas Proxmox envuelven en `data`; el helper `unwrap()` devuelve `$response->data['data']`):

| key | método | endpoint | params | access |
|---|---|---|---|---|
| `nodes.list` | GET | `/api2/json/nodes` | — | read |
| `nodes.status` | GET | `/api2/json/nodes/{node}/status` | `node*` | read |
| `qemu.list` | GET | `/api2/json/nodes/{node}/qemu` | `node*` | read |
| `qemu.status` | GET | `/api2/json/nodes/{node}/qemu/{vmid}/status/current` | `node*`, `vmid*` | read |
| `qemu.start` | POST | `/api2/json/nodes/{node}/qemu/{vmid}/status/start` | `node*`, `vmid*` | write |
| `qemu.shutdown` | POST | `/api2/json/nodes/{node}/qemu/{vmid}/status/shutdown` | `node*`, `vmid*` | write |
| `qemu.reboot` | POST | `/api2/json/nodes/{node}/qemu/{vmid}/status/reboot` | `node*`, `vmid*` | write |
| `qemu.snapshots` | GET | `.../qemu/{vmid}/snapshot` | `node*`, `vmid*` | read |
| `qemu.snapshot.create` | POST | `.../qemu/{vmid}/snapshot` | `node*`, `vmid*`, `snapname*` | write |
| `qemu.snapshot.rollback` | POST | `.../qemu/{vmid}/snapshot/{snapname}/rollback` | `node*`, `vmid*`, `snapname*` | destructive |
| `lxc.list` / `lxc.status` / `lxc.start` / `lxc.shutdown` | análogos `/lxc/{vmid}/…` | | | read/write |
| `tasks.list` | GET | `/api2/json/nodes/{node}/tasks` | `node*`, `limit` | read |
| `storage.list` | GET | `/api2/json/nodes/{node}/storage` | `node*` | read |

`test()`: `GET /api2/json/version` → meta `version`.

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 9: Home Assistant

**Files:** Create: `app/Integrations/Connectors/HomeAssistant/HomeAssistantConnector.php` · Test: `tests/Feature/Integrations/Connectors/HomeAssistantConnectorTest.php`

**Interfaces:** kind `home_assistant`, base `http://localhost:8123`, auth `api_token` (`token` → `Authorization: Bearer`), `options.verify` soportado.

- [ ] **Step 1: Test que falla** (`states.list` + `services.call`).
- [ ] **Step 2-3: Implementar**:

| key | método | endpoint | params | access |
|---|---|---|---|---|
| `api.status` | GET | `/api/` | — | read |
| `states.list` | GET | `/api/states` | `domain` (filtro client-side por prefijo) | read |
| `states.get` | GET | `/api/states/{entity_id}` | `entity_id*` | read |
| `services.call` | POST | `/api/services/{domain}/{service}` | `domain*`, `service*`, `entity_id`, `data` | write |
| `scenes.list` | GET | `/api/states` (filtra `scene.`) | — | read |
| `template.render` | POST | `/api/template` | `template*` | read |
| `config.get` | GET | `/api/config` | — | read |
| `logbook.list` | GET | `/api/logbook/{timestamp}` | `timestamp`, `entity` | read |

- [ ] **Step 4: Verde.** **Step 5:** Pint.

---

### Task 10: Registro, documentación y QA

**Files:**
- Modify: `config/integrations.php` (9 clases nuevas)
- Modify: `docs/superpowers/specs/2026-09-25-integraciones-core-design.md` (marcar Ola 1 ✅ si aplica)
- Test: `tests/Feature/Integrations/ConnectorRegistryTest.php` (assert de kinds), `tests/Feature/Integrations/IntegrationToolsTest.php` (catálogo incluye arr si está permitido)

- [ ] **Step 1:** Registrar todas las clases en `config('integrations.connectors')`.
- [ ] **Step 2:** Test de registry: `app(ConnectorRegistry::class)->all()` contiene los 12 kinds (3 de Ola 0 + 9).
- [ ] **Step 3:** Suite completa `php artisan test --compact` (debe quedar en verde) + `npm run build` si hubo UI (no en esta ola).
- [ ] **Step 4:** QA Playwright: crear una conexión Sonarr con URL/token ficticios → Probar → error legible; Home Assistant con token ficticio → error legible; el catálogo del wizard muestra los 12 servicios agrupados (Media / Infra / Dev / Google).
- [ ] **Step 5:** Actualizar `docs/qa/playwright-report.md` (sección Ola 1). Commit condicional.

---

## Self-Review

**Cobertura:** 9 conectores + opción TLS + registro + QA. Actions tabuladas por conector con endpoints reales (v3/v1, API2 de Proxmox, `/api/` de HA, `/api/v2` de qBittorrent). Sin dependencias nuevas.

**Riesgos conocidos:** qBittorrent rota cookie SID (cache 30 min + re-login en 403); Proxmox self-signed requiere `verify=false` (Task 1); YouTube/Reddit quedan para Ola 2.
