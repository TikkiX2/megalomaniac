# SP3 Feed Aprendido — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ingesta de RSS/Reddit/YouTube/HN, ranking personalizado (embeddings si el BYO los soporta, fallback léxico + scoring LLM), señales, digest diario IA + Telegram y UI `/feed`.

**Architecture:** 5 modelos (`feed_sources`, `feed_items`, `feed_signals`, `feed_preferences`, `feed_digests`); `FeedIngestor` (usa conectores de SP1), `FeedRanker` (híbrido embeddings/léxico), `FeedLearner` (señales → preferencias), `DigestAgent` (markdown IA), comandos `feed:ingest`/`feed:digest`, UI Inertia.

**Spec:** `docs/superpowers/specs/2026-09-25-feed-aprendido-design.md`

## Global Constraints

- Sin deps nuevas. `Embeddings::fake()` y agentes fake en tests; nunca red real.
- Ranking: embeddings con fallback; nunca romper sin embeddings.
- Señales son datos internos (sin aprobaciones); la notificación del digest usa `TelegramNotifier` de SP2 (fuera del tool path).
- Pint por tarea; commits con autorización.
- **Desviación registrada:** el digest guarda **markdown** del agente (no JSON estructurado) + `item_ids` top; el contrato estructurado anidado no es expresable en `JsonSchema` y el streaming del SDK lo rechaza (mismo motivo que SP2).

---

### Task 1: Config, migraciones, modelos y factories

**Files:** `config/feed.php`; migrations `2026_09_25_000006..000010`; modelos `FeedSource`, `FeedItem`, `FeedSignal`, `FeedPreference`, `FeedDigest`; factories; `tests/Feature/Feed/FeedModelsTest.php`.

**Interfaces:** columnas del spec. `FeedItem` scopes `forUser`, `visible` (`hidden_at` null), `saved`; `FeedSource` scopes `forUser`, `enabled`; `FeedPreference::forUser` static. `config/feed.php`: `retention_days`, `max_items`, `digest_hour`, `window_days`.

- [ ] Tests (casts, unique, scopes, relaciones) → RED → implementar → GREEN.

---

### Task 2: FeedIngestor

**Files:** `app/Feed/FeedIngestor.php`, `app/Console/Commands/IngestFeedsCommand.php`; `tests/Feature/Feed/FeedIngestTest.php`.

**Interfaces:**
- `ingest(FeedSource): int` (items nuevos), `ingestDue(): int` (todas las habilitadas).
- `rss`/`hackernews`: conexión en memoria + `RssConnector::feed.fetch`.
- `reddit`: `connection_id` + `RedditConnector` (`subreddit.hot|new|top`).
- `youtube`: `connection_id` + `YoutubeConnector` (`channels.get` → uploads playlist → `playlist.items`).
- Errores por fuente → `fetch_error` sin abortar; dedupe `(source_id, external_id)`; purga por retención/max items (excepto guardados).

- [ ] Tests: rss fixture, reddit fixture, youtube fixture (Http::fake), error de fuente, dedupe, purga → RED → implementar → GREEN.

---

### Task 3: Embeddings + FeedRanker

**Files:** `app/Ai/Support/AiProviderResolver.php` (+`embeddingsFor`), `app/Feed/FeedRanker.php`, settings IA (+`ai_embeddings_model`), migration `add_ai_embeddings_model_to_users_table`; `tests/Feature/Feed/FeedRankerTest.php`.

**Interfaces:**
- `AiProviderResolver::embeddingsFor(User): array` (`['user', model]` si configurado; fallback server `openai`; si no `[null,null]`).
- `FeedRanker::top(User, int $limit = 30): Collection`; score = `0.55*similitud + 0.25*recencia + 0.15*peso_fuente + 0.05*engagement`.
  - Con embeddings: genera lazy los faltantes (batch + `->cache()`), coseno contra centroide.
  - Sin embeddings: overlap léxico con `topic_weights` (+ scoring LLM en batch si hay proveedor de texto, cacheado en `score/scored_at`; si falla, solo léxico).

- [ ] Tests: `Embeddings::fake()` + centroide ordena; sin embeddings no llama al SDK y ordena por léxico; `embeddingsFor` resuelve/`null`; settings persiste el modelo → RED → implementar → GREEN.

---

### Task 4: FeedLearner + DigestAgent + comandos

**Files:** `app/Feed/FeedLearner.php`, `app/Feed/DigestAgent.php`, `app/Console/Commands/IngestFeedsCommand.php`, `DigestFeedsCommand.php`; `routes/console.php`; `tests/Feature/Feed/FeedDigestTest.php`.

**Interfaces:**
- `FeedLearner::record(FeedItem, string $signal): void` — señal idempotente; `topic_weights`/`source_weights` (EMA), centroide si hay embedding; `hide` oculta, `save` marca.
- `DigestAgent::generate(User, Collection $items): FeedDigest` — agente anónimo (provider del usuario) con prompt de resumen; persiste por fecha (no duplica); `TelegramNotifier` si hay conexión.
- `feed:ingest` (everyThirtyMinutes) y `feed:digest` (hourly; genera si pasó `digest_hour` y no existe).

- [ ] Tests: señales actualizan preferencias y ocultan; digest fake persiste único y notifica; comandos con `travel` → RED → implementar → GREEN.

---

### Task 5: Controllers, rutas y UI `/feed`

**Files:** `app/Http/Controllers/Feed/{FeedController,FeedSourceController}.php`, requests, `routes/feed.php` (require desde web.php), `resources/js/pages/feed/{index,settings}.tsx`, `resources/js/components/feed/*`, sidebar; `tests/Feature/Feed/FeedPagesTest.php`.

**Rutas:** GET `feed`, GET `feed/settings`, POST/PATCH/DELETE `feed/sources`, POST `feed/items/{item}/signal`, POST `feed/digest` (`throttle:3,1`). Scoping 404.

- [ ] Tests Inertia (index con digest+items, signals, sources CRUD, 404) → RED → implementar → GREEN; `npm run types && npm run build`.

---

### Task 6: QA y cierre

- [ ] Suite completa + Pint + types/build.
- [ ] QA Playwright: `/feed` vacío → crear fuente RSS con `https://news.ycombinator.com/rss` → `feed:ingest` real → items visibles → like/guardar/ocultar → settings → digest sin proveedor IA (skip elegante).
- [ ] `docs/qa/playwright-report.md` + commit condicional.

---

## Self-Review

**Cobertura:** ingesta (4 fuentes), ranking (embeddings + fallback), señales, digest + notificación, UI y comandos. La purga respeta guardados. El scoring LLM es opcional y cacheado.
