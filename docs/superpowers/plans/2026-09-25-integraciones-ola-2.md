# Integraciones Ola 2 (Comunicación · Contenido · Música) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Sumar 6 conectores (Telegram, Notion, RSS/Atom, Reddit, YouTube, ListenBrainz) sobre el framework existente. Habilita el feed de SP3 (`rss`, `reddit`, `youtube`) y las notificaciones de SP2 (`telegram`).

**Architecture:** Idéntica a Olas 0-1: `AbstractConnector` + `Action[]` + `HttpCall`. RSS parsea XML del `HttpResult->body`. Reddit estrena preset OAuth2 en el `OAuthBroker`. YouTube usa API key (suficiente para el feed: search/videos/channels/playlistItems). Sin dependencias nuevas.

**Spec:** `docs/superpowers/specs/2026-09-25-integraciones-core-design.md`

## Global Constraints

- Sin deps nuevas. Tests con `Http::fake()`.
- Convenciones: `Action`/`Param`, `result()`, `defaultBaseUrl()`, `authFields()`, `group()`, `transports()`.
- Registrar cada conector en `config/integrations.php`.
- `write`/`destructive` pasan por aprobaciones (ya implementado).
- Pint al cerrar cada tarea; commits solo con autorización.

---

### Task 1: Telegram

**Files:** Create: `app/Integrations/Connectors/Telegram/TelegramConnector.php` · Test: `tests/Feature/Integrations/Connectors/TelegramConnectorTest.php`

**Interfaces:** kind `telegram`, group `Comunicación`, auth `api_token` (`token`), `options.chat_id` (default opcional), default base `https://api.telegram.org`. Token va en el path (`bot{token}/…`).

- [ ] **Step 1: Test** — `bot.info`, `message.send` (chat_id de options), error 401.
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params | access |
|---|---|---|---|
| `bot.info` | GET `bot{token}/getMe` | — | read |
| `message.send` | POST `bot{token}/sendMessage` (json) | `text*`, `chat_id`, `parse_mode` | write |
| `message.send_photo` | POST `bot{token}/sendPhoto` (json) | `photo*` (URL), `caption`, `chat_id` | write |
| `chat.info` | POST `bot{token}/getChat` | `chat_id` | read |
| `updates.get` | POST `bot{token}/getUpdates` | `offset`, `limit` | read |
| `webhook.set` | POST `bot{token}/setWebhook` | `url*` | write |
| `webhook.delete` | POST `bot{token}/deleteWebhook` | — | write |

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 2: Notion

**Files:** Create: `app/Integrations/Connectors/Notion/NotionConnector.php` · Test: `tests/Feature/Integrations/Connectors/NotionConnectorTest.php`

**Interfaces:** kind `notion`, group `Contenido`, auth `api_token` (`token`), headers `Authorization: Bearer` + `Notion-Version: 2022-06-28`, default base `https://api.notion.com`.

- [ ] **Step 1: Test** — `search.query`, `pages.create`, header de versión.
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params | access |
|---|---|---|---|
| `search.query` | POST `/v1/search` | `query`, `page_size` | read |
| `pages.get` | GET `/v1/pages/{page_id}` | `page_id*` | read |
| `pages.create` | POST `/v1/pages` | `parent_type*` (page/database), `parent_id*`, `title*` | write |
| `pages.update` | PATCH `/v1/pages/{page_id}` | `page_id*`, `archived`, `properties` | write |
| `pages.delete` | PATCH `/v1/pages/{page_id}` (`archived=true`) | `page_id*` | destructive |
| `blocks.children.list` | GET `/v1/blocks/{block_id}/children` | `block_id*`, `page_size` | read |
| `blocks.append` | PATCH `/v1/blocks/{block_id}/children` | `block_id*`, `paragraph*` (texto) | write |
| `databases.query` | POST `/v1/databases/{database_id}/query` | `database_id*`, `page_size` | read |
| `users.me` | GET `/v1/users/me` | — | read |

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 3: RSS/Atom

**Files:** Create: `app/Integrations/Connectors/Rss/RssConnector.php` · Test: `tests/Feature/Integrations/Connectors/RssConnectorTest.php`

**Interfaces:** kind `rss`, group `Contenido`, auth `none`, **sin default base URL** (la URL del feed es `base_url`, obligatoria). Parsea RSS 2.0 (`<item>`) y Atom (`<entry>`) a items normalizados `{external_id, title, url, summary, author, published_at}`.

- [ ] **Step 1: Test** con fixture XML (RSS y Atom), dedupe de items sin guid, error 404.
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params | access |
|---|---|---|---|
| `feed.info` | GET feed | — | read |
| `feed.fetch` | GET feed | `limit` (default 20) | read |

`parse(string $xml, int $limit): array` con `simplexml_load_string` + fallback `LIBXML_NOWARNING`; `external_id` = `guid`/`id` o `sha1(link)`.
`test()`: `feed.info` → meta `title`.

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 4: ListenBrainz

**Files:** Create: `app/Integrations/Connectors/Listenbrainz/ListenbrainzConnector.php` · Test: `tests/Feature/Integrations/Connectors/ListenbrainzConnectorTest.php`

**Interfaces:** kind `listenbrainz`, group `Música`, auth `api_token` con `token` **opcional** (lecturas públicas funcionan sin token), `options.username` (default), header `Authorization: Bearer` solo si hay token. Default base `https://api.listenbrainz.org`.

- [ ] **Step 1: Test** — `listens.recent`, `stats.top_artists`, usuario desde options.
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params | access |
|---|---|---|---|
| `user.get` | GET `/1/user/{user}` | `user` | read |
| `listens.recent` | GET `/1/user/{user}/listens` | `user`, `count` (20) | read |
| `stats.top_artists` | GET `/1/stats/user/{user}/artists` | `user`, `range` (week/month/year/all_time) | read |
| `stats.top_recordings` | GET `/1/stats/user/{user}/recordings` | `user`, `range` | read |
| `stats.listening_activity` | GET `/1/stats/user/{user}/listening-activity` | `user`, `range` | read |

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 5: YouTube (API key)

**Files:** Create: `app/Integrations/Connectors/Youtube/YoutubeConnector.php` · Test: `tests/Feature/Integrations/Connectors/YoutubeConnectorTest.php`

**Interfaces:** kind `youtube`, group `Contenido`, auth `api_token` (`api_key`, se envía como query `key`), default base `https://www.googleapis.com`, path base `youtube/v3/…`. Sin OAuth en v1 (las subs se resuelven con `channels.get` → uploads playlist + `playlist.items`).

- [ ] **Step 1: Test** — `videos.search`, `playlist.items`, `channels.get` con `forHandle`.
- [ ] **Step 2-3: Implementar**:

| key | endpoint | params | access |
|---|---|---|---|
| `videos.search` | GET `/youtube/v3/search` | `query*`, `max_results`, `channel_id`, `order` | read |
| `videos.get` | GET `/youtube/v3/videos` | `video_ids*` (coma), `part` | read |
| `channels.get` | GET `/youtube/v3/channels` | `channel_id`, `for_handle` | read |
| `playlist.items` | GET `/youtube/v3/playlistItems` | `playlist_id*`, `max_results` | read |
| `videos.categories` | GET `/youtube/v3/videoCategories` | `region_code` (US) | read |

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 6: Reddit + preset OAuth2

**Files:**
- Create: `app/Integrations/OAuth/Presets/RedditOAuthPreset.php`
- Modify: `app/Integrations/OAuth/OAuthBroker.php` (`reddit` => preset)
- Create: `app/Integrations/Connectors/Reddit/RedditConnector.php`
- Test: `tests/Feature/Integrations/Connectors/RedditConnectorTest.php`, `tests/Feature/Integrations/OAuthTest.php` (+1 test de preset)

**Interfaces:** kind `reddit`, group `Contenido`, auth `oauth2` con `client_id`/`client_secret` (authFields), API `https://oauth.reddit.com`, header `User-Agent: megalomaniac/1.0` + `raw_json=1`. Scopes: `read identity save submit vote`.

- [ ] **Step 1: Test** — preset arma URL (`reddit.com/api/v1/authorize`) y refresca; conector: `user.me`, `subreddit.hot`, `vote` (write).
- [ ] **Step 2-3: Implementar** — `RedditOAuthPreset` (authorize/token con Basic auth de client creds, `duration=permanent`) y acciones:

| key | endpoint | params | access |
|---|---|---|---|
| `user.me` | GET `/api/v1/me` | — | read |
| `subreddit.hot` / `.new` / `.top` | GET `/r/{sub}/{sort}` | `sub*`, `limit`, `t` (top) | read |
| `search.query` | GET `/search` | `query*`, `subreddit`, `sort`, `limit` | read |
| `post.get` | GET `/comments/{id}` | `id*` | read |
| `post.comments` | GET `/comments/{id}` (`limit`) | `id*`, `limit` | read |
| `saved.list` | GET `/user/{username}/saved` | `username*`, `limit` | read |
| `post.submit` | POST `/api/submit` (form) | `subreddit*`, `title*`, `kind*` (self/link), `text`/`url` | write |
| `comment.create` | POST `/api/comment` (form) | `parent_id*`, `text*` | write |
| `vote` | POST `/api/vote` (form) | `id*`, `direction*` (1/-1/0) | write |
| `save` | POST `/api/save` (form) | `id*` | write |

Refresh: `OAuthBroker::refreshIfNeeded` antes de cada request (igual que Google).

- [ ] **Step 4:** Verde. **Step 5:** Pint.

---

### Task 7: Registro, SP3-ready y QA

- [ ] **Step 1:** Registrar los 6 en `config/integrations.php`.
- [ ] **Step 2:** Actualizar el test de registry (17 kinds).
- [ ] **Step 3:** Test de humo del ingestor-ready: `RssConnector::feed.fetch` sobre fixtures RSS/HN devuelve items normalizados con `external_id` (lo que SP3 consumirá).
- [ ] **Step 4:** Suite completa + Pint.
- [ ] **Step 5:** QA Playwright: wizard muestra 17 servicios; crear RSS con la URL de HN (`https://news.ycombinator.com/rss`) → Probar `feed.info` OK (llamada real, sin auth); limpiar.
- [ ] **Step 6:** Actualizar `docs/qa/playwright-report.md`. Commit condicional.

---

## Self-Review

**Cobertura:** 6 conectores; Telegram y Reddit cubren notificaciones (SP2) y fuentes (SP3); RSS/HN y YouTube cubren el feed; Notion y ListenBrainz completan Contenido/Música. Sin deps nuevas.

**Riesgos:** RSS con XML malformado (fallback + error legible), Reddit exige User-Agent y `raw_json`, Telegram exige el token en el path (no header), ListenBrainz sin token limita algunas lecturas (se documenta en el `help` del authField).
