# Feed Aprendido — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Alcance: Sub-proyecto 3 de 4 (1 Integraciones → 2 Agentes → **3 Feed** → 4 Storage)

## Problema

El cockpit no tiene una superficie de lectura. El usuario quiere un feed de noticias interesantes que **aprenda** de sus gustos (no un lector RSS pasivo), con resumen diario y sin depender de un proveedor de embeddings que quizá no tenga configurado.

## Objetivo

Ingesta periódica de fuentes (RSS/Atom, Reddit, YouTube, Hacker News), ranking personalizado que usa **embeddings cuando el proveedor BYO los soporta y cae a perfil léxico + scoring LLM cuando no**, señales explícitas/implícitas, digest diario con IA y notificación opcional por Telegram. Página `/feed` con digest, cards, filtros y acciones.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Fuentes v1: RSS/Atom, Reddit (subreddits), YouTube (suscripciones), Hacker News. |
| 2 | Señales: 👍 like, 👎 dislike, guardar, ocultar, abrir (click). |
| 3 | Aprendizaje: embeddings si el BYO los soporta (`Settings → IA` gana `ai_embeddings_model`); fallback perfil léxico/tags + scoring LLM con el modelo de texto. **Nunca se rompe sin embeddings.** |
| 4 | Digest diario IA (`feed:digest`) con los N mejores + Telegram opcional. |
| 5 | Ingesta vía conectores de Ola 2 (`feed:ingest` cada 30 min). |
| 6 | Retención: items > 90 días o > 5.000 por usuario se purgan (los guardados se conservan). |
| 7 | Sin extracción full-text de artículos, sin comentarios/social, sin push (solo Telegram). |

## Arquitectura

```
feed:ingest (30 min) ──► FeedIngestor ──► conectores SP1 (rss/reddit/youtube + HN por RSS)
                                              └─► feed_items (dedupe por external_id)
feed:digest (diario) ──► FeedRanker(top N) ──► DigestAgent (structured output) ──► feed_digests + Telegram
UI /feed ──► señales ──► FeedLearner ──► feed_preferences (centroide / pesos)
```

- **`FeedIngestor`**: por cada `feed_source` habilitada resuelve el conector, lee, normaliza a `FeedItemData` (dto), deduplica por `(source_id, external_id)` y guarda. Errores por fuente a `fetch_error` sin abortar el resto.
- **`FeedRanker`**: candidatos = items no ocultos de últimos 7 días no vistos. Score híbrido:
  `score = 0.55*similitud + 0.25*recencia + 0.15*peso_fuente + 0.05*engagement` (similitud = coseno contra centroide; fallback = overlap léxico contra `topic_weights`).
  Si no hay embeddings: top 50 candidatos → scoring LLM en batch (structured output `{scores:[{item_id, score, why}]}`), cacheado en `feed_items.score`/`scored_at` (máx. 1 llamada por ingesta).
- **`FeedLearner`**: cada señal actualiza `feed_preferences`: EMA del centroide (si embeddings), `topic_weights` léxicos, `source_weights`. `hide` además marca el item y penaliza tema/fuente.
- **`DigestAgent`** (anonymous agent): recibe los top 10 + por qué; structured output `{intro, items:[{item_id, why}]}`; se persiste en `feed_digests` y se notifica.

## Modelo de datos

### `feed_sources`

```
id, user_id FK cascade
kind            string(20)     // rss|reddit|youtube|hackernews
name            string(100)
config          json           // rss: {url} · reddit: {subreddit, sort} · youtube: {channel_ids:[]} · hn: {min_score}
enabled         boolean default true
connection_id   FK connections nullable   // credenciales (Reddit OAuth / YouTube del conector google)
last_fetched_at timestamp nullable
fetch_error     text nullable
timestamps
unique (user_id, kind, name)
```

### `feed_items`

```
id, user_id FK cascade, feed_source_id FK cascade
external_id     string(255)
title           string(500)
url             string(1000)
author          string(150) nullable
summary         text nullable
content_hash    string(64)              // sha1(title+url) para dedupe blando
published_at    timestamp nullable
fetched_at      timestamp
embedding       json nullable           // vector (solo si embeddings activos)
score           float nullable
scored_at       timestamp nullable
is_saved        boolean default false
hidden_at       timestamp nullable
timestamps
unique (feed_source_id, external_id) · index (user_id, published_at) · index (user_id, hidden_at, score)
```

### `feed_signals`

```
id, feed_item_id FK cascade, user_id FK cascade
type            string(10)   // like|dislike|save|hide|open
timestamps
unique (feed_item_id, user_id, type)
```

### `feed_preferences` (1 por usuario)

```
id, user_id unique FK cascade
embedding       json nullable           // centroide
topic_weights   json                    // {"ai": 3.2, "laravel": 1.8, ...}
source_weights  json                    // {"feed_source_id": 1.4}
likes, dislikes, saves, opens unsignedInteger default 0
timestamps
```

### `feed_digests`

```
id, user_id FK cascade
date            date
content         text                    // markdown generado
item_ids        json
sent_at         timestamp nullable
timestamps
unique (user_id, date)
```

## Contratos

```php
final class FeedIngestor
{
    public function ingest(FeedSource $source): int;    // items nuevos
    public function ingestDue(): int;                   // todas las habilitadas
}

final class FeedRanker
{
    /** @return Collection<int, FeedItem> */
    public function top(User $user, int $limit = 30): Collection;
    public function score(FeedItem $item): float;
}

final class FeedLearner
{
    public function record(FeedItem $item, string $signal): void;
    public function profile(User $user): FeedPreference;
}

final class DigestAgent   // anonymous agent con HasStructuredOutput
{
    public function generate(User $user, Collection $items): FeedDigest;
}
```

**Embeddings** — extensión de `AiProviderResolver`:

```php
public static function embeddingsFor(User $user): array   // [provider, model] | [null, null]
```

- Si `ai_embeddings_model` está definido y `ai_enabled` → `['user', $user->ai_embeddings_model]`.
- Si no, y hay `OPENAI_API_KEY` de servidor → `[config('ai.default_for_embeddings'), null]`.
- Settings → IA agrega campo opcional `ai_embeddings_model` + hint de que habilita el feed con embeddings.

## Backend

- **Comandos**: `feed:ingest` (everyThirtyMinutes) y `feed:digest` (hourly; genera el digest del día si pasó la hora configurada y no existe) en `routes/console.php`.
- **Connectors**: Ola 2 pone `rss`, `reddit`, `youtube`; Hacker News se cubre con el conector `rss` apuntando a `https://news.ycombinator.com/rss` (fuente por defecto). El ingestor usa acciones de solo lectura.
- **Rutas**:
  | Método | URI | Action |
  |---|---|---|
  | GET | `feed` | index (Inertia `feed/index`; digest + items rankeados + filtros) |
  | GET | `feed/settings` | sources CRUD (Inertia `feed/settings`) |
  | POST | `feed/sources` | store |
  | PATCH | `feed/sources/{source}` | update |
  | DELETE | `feed/sources/{source}` | destroy |
  | POST | `feed/items/{item}/signal` | like/dislike/save/hide/open |
  | POST | `feed/digest` | generar digest on-demand (throttle 3,1) |
- **UI** `/feed`: banner de digest (colapsable), lista de cards (fuente, título, resumen, tiempo, "por qué"), acciones 👍/👎/guardar/ocultar/abrir (click envía `open`), tabs Todos/Guardados/Ocultos, filtro por fuente. Settings: lista de fuentes con estado (`last_fetched_at`, `fetch_error`), alta por URL/subreddit/canal, hora del digest, estado de embeddings.
- **Sidebar**: item **Feed** (`Newspaper`).

## Testing

Pest 4, `tests/Feature/Feed/`:
- Ingesta por fuente con `Http::fake` (RSS XML, Reddit JSON, YouTube, HN), dedupe, tolerancia a errores por fuente, purga de retención.
- Ranking: con embeddings (fake) el orden cambia por similitud; sin embeddings cae a léxico y no llama al SDK; scoring LLM en batch cachea.
- Señales: like/save/ocultar actualizan preferencias (EMA), `hide` excluye del ranking, `sources_weights`.
- Digest: structured output fake, persiste por fecha (no duplica), Telegram opcional con `Http::fake`.
- UI Inertia: index con filtros, signals, sources CRUD, scoping 404.
- Settings: `ai_embeddings_model` persiste y `embeddingsFor` resuelve.

## Fuera de alcance (SP3)

Full-text extraction/readability, compartir items, comentarios, push web, multiidioma explícito, entrenamiento ML, edición manual del perfil (solo inferido + reseteo).

## Compatibilidad

- **Ola 2**: conectores `rss`/`reddit`/`youtube` son prerequisito de la ingesta.
- **SP2**: el digest reutiliza el patrón de anonymous agent + structured output + notificación; un agente background podría resumir el feed en el futuro (no en v1).
- **SP1**: señales no usan aprobaciones (son datos internos); las fuentes con OAuth reutilizan las conexiones.
