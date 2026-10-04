# Módulo Inspiración — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo de inspiración multi-fuente: explorar/buscar imágenes de 35 plataformas (APIs, JSON públicos y scrapers), guardarlas en moodboards por proyecto personal (con Inbox), descargar full bajo demanda, con toggle global de madurez y caché write-through con degradación elegante.

**Architecture:** `App\Inspiration` — contrato único `Source` implementado por 35 adapters (JSON vía `Http\Client`, HTML vía `symfony/dom-crawler`), despachados por `SourceManager` con aislamiento por fuente; resultados cacheados write-through en `inspiration_cache`; guardado en `saved_images` (dedupe por user+source+source_id) con miniaturas locales vía jobs de cola; moodboards 1:1 con `Project type=personal` + Inbox.

**Tech Stack:** Laravel 12, Inertia v2 + React 19, Tailwind v4, Pest 4, `symfony/dom-crawler` + `symfony/css-selector` (a instalar), Wayfinder, cola/caché driver `database`.

**Spec:** `docs/superpowers/specs/2026-10-04-inspiration-design.md` (fuente de verdad; este plan argumenta desde ella).

## Global Constraints

- Namespace de dominio: `App\Inspiration`; controladores en `App\Http\Controllers\Inspiration`; rutas en `routes/inspiration.php` (require en `web.php`, grupo `auth, verified`); frontend en `resources/js/pages/inspiration/` + componentes `resources/js/components/inspiration/`.
- Contrato `Source` (exacto, desde spec): `key(): string`, `label(): string`, `capabilities(): SourceCapabilities`, `isConfigured(): bool`, `setCredentials(array $credentials): void` (vía trait `Concerns\UsesCredentials`), `search(string $query, int $page, SourceQuery $options): Page`, `explore(int $page, SourceQuery $options): Page`, `test(): bool`.
- DTOs: `SourceCapabilities(supportsSearch, supportsExplore, needsKey, hasMaturityLevels, maxPageSize=24, ratePerMinute=null)`; `SourceQuery(maturity='safe', extra=[])`; `Page(items, hasMore, nextPage)`; `InspirationItem(source, sourceId, title?, author?, authorUrl?, pageUrl, imageUrl, thumbnailUrl?, width?, height?, tags=[], dominantColor?, license?, maturity?)` — items sin `pageUrl` o `imageUrl` se descartan en el adapter.
- Tablas (spec §Modelo de datos): `moodboards`, `saved_images` (unique `(user_id, source, source_id)`; `download_status` `thumb|full|failed`), `inspiration_cache` (index `expires_at`), `inspiration_settings` (user_id PK, body json: `enabled_sources[]`, `keys{}`, `maturity` bool, `zerochan_ua`?, `acknowledged_tier3[]`). Partial unique: un Inbox por usuario (`user_id` WHERE `project_id IS NULL`) y un moodboard por proyecto (`user_id, project_id` WHERE `project_id IS NOT NULL`).
- Config `config/inspiration.php`: `disk` (env `INSPIRATION_DISK`, default `local`, subpath `inspiration/{user_id}/{source}/`), `max_downloads_per_day` (20), TTLs (search 1800s, explore 3600s, scraper 21600s), timeouts (Tier1 5s, Tier2 12s, Tier3 10s), credenciales app-level (deviantart client_id/secret desde env), **`tier3: ['pixiv','pinterest','cara','bandcamp','wikiart','newgrounds','mobbin']`** (gate de aviso, verbatim del spec).
- Mapeo de madurez (spec, verbatim): Wallhaven `purity=100`↔`110/111` con key; Gelbooru `-rating:explicit`↔todos; Giphy `rating=g|pg`↔`pg-13|r`; Pixabay `safesearch=1`↔`0`; Flickr `safe_search=1`↔`2|3`; DeviantArt `mature_content=false`↔`true`; Pixiv solo `safe`↔`safe|r15|r18`; resto n/a. El hash de caché SIEMPRE incluye la madurez pedida.
- Defaults de fuente (spec): Tier 1 api = activas; 9 sin key de usuario (DeviantArt usa credenciales de app; ArtStation, Wallhaven, Openverse, Zerochan, Gelbooru, Are.na, Met, AIC nada); con-key = off hasta configurar (Flickr, Tumblr, Unsplash, Pexels, Pixabay, Discogs, Giphy, Europeana, Rijksmuseum); Tier 2 scrapers = activas; Tier 3 = off + requiere `acknowledged_tier3` (Pixiv, Pinterest, Cara, Bandcamp, WikiArt, Newgrounds, Mobbin).
- Sin `DB::` en queries; Eloquent `casts()`; Form Requests; `vendor/bin/pint --dirty --format agent` tras tocar PHP; `npm run build` + eslint tras tocar frontend; `php artisan wayfinder:generate` tras rutas/controladores nuevos.

## Review Focus

Fallos que la spec no lista pero un usuario razonable espera; cada uno queda clavado en el test de su tarea dueña:

1. **JSON de fuente con shape inesperado** (campos null, arrays anidados ausentes): el adapter no debe lanzar 500 ni guardar basura — descarta items incompletos, chip degrada si todo falla. → Test en Task 4 (fixture `malformed`).
2. **Miniatura remota rota (404/5xx)** al guardar: el saved_image no queda roto — `thumb_path` null y el frontend usa `image_url` remota como fallback visual. → Task 7.
3. **Fuente que exige query (búsqueda vacía)**: devuelve `Page` vacío, nunca excepción. → Task 4.
4. **Caché cross-madurez**: mismo término con madurez OFF y ON debe producir hashes de caché distintos (el OFF no puede servir contenido de un ON previo). → Task 13 (y verificación en Task 3).
5. **Download full lento/gigante**: timeout HTTP en el job (30s), fallo → `download_status=failed` visible en UI con opción reintentar. → Task 7.

---

### Task 1: Migraciones y modelos núcleo

**Files:**
- Create: `database/migrations/2026_10_04_000001_create_moodboards_table.php`, `..._000002_create_saved_images_table.php`, `..._000003_create_inspiration_cache_table.php`, `..._000004_create_inspiration_settings_table.php`
- Create: `app/Models/Moodboard.php`, `app/Models/SavedImage.php`, `app/Models/InspirationSetting.php`
- Create: `config/inspiration.php`

**Interfaces:**
- Consumes: nada (primera tarea).
- Produces: modelos `Moodboard` (fillable `user_id, project_id, name`; relations `project()`, `savedImages()`, scope `forUser`), `SavedImage` (fillable completo de la tabla; `casts tags => array`; const `STATUS_THUMB/STATUS_FULL/STATUS_FAILED`; scope `forUser`), `InspirationSetting` (`$primaryKey='user_id'`, `$incrementing=false`, `casts body => array`); `config('inspiration.*')` con los valores de Global Constraints.

- [ ] **Step 1: Escribir el test que falla** — `tests/Feature/Inspiration/ModelsTest.php`: crea moodboard con `project_id` null (Inbox) y con project; verifica casts de `SavedImage.tags`; verifica `InspirationSetting::updateOrCreate(['user_id'=>…], ['body'=>[…]])`; **verifica restricciones DB**: insertar dos `saved_images` con mismo (user, source, source_id) → `QueryException`; crear dos Inbox para el mismo user → `QueryException`; dos moodboards con el mismo project → `QueryException`.
- [ ] **Step 2: Correr el test** — `php artisan test --compact --filter=ModelsTest` → falla (tablas no existen).
- [ ] **Step 3: Migraciones + modelos + config** — columnas exactas de la spec; índices unique compuestos y partials (raw index en la migración: `DB::statement('CREATE UNIQUE INDEX ... WHERE project_id IS NULL')` — permitido solo en migración, ver constraint), casts, config con defaults de la spec.
- [ ] **Step 4: Correr tests** — paso el filtro completo; PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): migraciones y modelos núcleo (moodboards, saved_images, cache, settings)`.

### Task 2: InspirationCache + InspirationSettings (bag)

**Files:**
- Create: `app/Inspiration/InspirationCache.php`, `app/Inspiration/InspirationSettings.php`, `app/Inspiration/Dtos/SettingsBag.php`
- Test: `tests/Feature/Inspiration/CacheTest.php`

**Interfaces:**
- Consumes: modelos Task 1.
- Produces: `InspirationCache::remember(string $source, string $kind, string $queryHash, int $ttlSeconds, Closure $fetch): array{payload:array,from_cache:bool,age_minutes:?int}` (payload = array crudo de items del adapter); `InspirationCache::ageMinutes(...): ?int`; `InspirationCache::queryHash(string $query, SourceQuery $options): string` (md5 de `$query|maturity|json(extra)`; `SourceQuery` llega en Task 3 — definir aquí un stub mínimo `App\Inspiration\Dtos\SourceQuery` con `maturity='safe'` y `extra=[]`, Task 3 completa el contrato). `InspirationSettings::for(User $user): SettingsBag`; `InspirationSettings::update(User $user, array $body): void` (persiste shape del bag). `SettingsBag`: props `enabledSources:array, keys:array, maturity:bool, zerochanUa:?string, acknowledgedTier3:array`, `hasKey(string $source): bool`, `isEnabled(string $source): bool`.

- [ ] **Step 1: Test que falla** — `CacheTest`: `remember` con closure → payload retornado con `from_cache=false`; segunda llamada con closure que lanza → sirve caché con `from_cache=true` y `age_minutes` coherente; TTL expirado → closure se ejecuta; **`queryHash('portrait', maturity 'safe') !== queryHash('portrait', maturity 'allowed')`** (Review Focus 4); settings bag defaults (maturity=false, enabledSources=[]).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** (chat: `InspirationCache` usa `InspirationCacheEntry` model inline con `where source/kind/query_hash`; `SourceQuery` stub con constructor `__construct(string $maturity = 'safe', array $extra = [])`).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): caché write-through y settings bag por usuario`.

### Task 3: Contrato, DTOs y SourceManager

**Files:**
- Create: `app/Inspiration/Contracts/Source.php`, `app/Inspiration/Dtos/{SourceCapabilities,Page,InspirationItem}.php` (SourceQuery ya existe del Task 2, se completa si hace falta), `app/Inspiration/Concerns/UsesCredentials.php`, `app/Inspiration/Exceptions/SourceException.php`, `app/Inspiration/SourceManager.php`, `app/Inspiration/InspirationServiceProvider.php`
- Modify: `bootstrap/providers.php` (registrar el provider)
- Test: `tests/Feature/Inspiration/SourceManagerTest.php`

**Interfaces:**
- Consumes: modelos Task 1, `InspirationCache`/`InspirationSettings` Task 2 (**wiring:** `SourceManager` recibe `InspirationCache` por constructor y `search/explore` son write-through: caché fresca → payload + `from_cache=true`; miss → adapter, guarda, `from_cache=false`; adapter lanza → si hay caché vencida o no → `SourceException` con `previous_cache_age` para `statuses.down`).
- Produces: `SourceManager` — `all(): Collection<string,Source>`, `get(string $key): ?Source`, `activeConfigured(User $user): Collection<string,Source>` (settings.enabled + isConfigured tras `setCredentials($keys)`), `statuses(User $user): array<string,array{enabled:bool,configured:bool,down:bool,cache_age_minutes:?int}>`, `search(User $user, string $key, string $query, int $page): array{items:array,has_more:bool,from_cache:bool,age_minutes:?int}`, `explore(User $user, string $key, int $page): array{...}` (misma shape), `searchAll(User $user, string $query, int $perSource=12): array<string,array{...}>`, `rateLimit(User $user, string $key): bool` (RateLimiter `inspiration:{key}`, max = capabilities.ratePerMinute ?? 30).
- `InspirationServiceProvider`: binding tag `inspiration.sources` de TODOS los adapters (registrados con nombre de key); los adapters de cada wave se añaden a este binding en su tarea.

- [ ] **Step 1: Test que falla** — `SourceManagerTest`: registra 2 sources fake (uno que lanza `SourceException`, otro ok) vía container tag; `search` de una fuente caída → lanza `SourceException`; `searchAll` con la caída → la clave caída tiene `items=[]` y las demás responden (aislamiento); la clave caída aparece en `statuses` con `down=true`; **write-through:** primer `search` de la ok → `from_cache=false`; segundo (el fake devuelve distinto) → `from_cache=true` con el payload de la primera; `activeConfigured` filtra por enabled+configured.
- [ ] **Step 2: Correr** → falla (clases no existen).
- [ ] **Step 3: Implementar** contrato, DTOs (SourceCapabilities con `__construct(bool $supportsSearch, bool $supportsExplore, bool $needsKey, bool $hasMaturityLevels, int $maxPageSize = 24, ?int $ratePerMinute = null)`; `Page` con `fromItems(array $items, bool $hasMore, ?int $nextPage): self`; `InspirationItem::fromSource(string $source, array $data): ?self` que retorna null si faltan `pageUrl`/`imageUrl`), trait, excepción (props `previous_cache_age`), manager con wiring de caché, provider (fakes registrados solo en tests vía `app->tag`).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): contrato Source, DTOs y SourceManager con aislamiento y caché`.

### Task 4: Adapters Tier 1 sin-key — batch A (DeviantArt, ArtStation, Wallhaven, Openverse)

**Files:**
- Create: `app/Inspiration/Sources/Api/{DeviantArtSource,ArtStationSource,WallhavenSource,OpenverseSource}.php`
- Create: `tests/Fixtures/Inspiration/{deviantart,artstation,wallhaven,openverse}/{search,malformed,empty}.json`
- Modify: `InspirationServiceProvider` (tag los 4)
- Test: `tests/Feature/Inspiration/SourcesTest.php` (dataset-driven)

**Interfaces:**
- Consumes: contrato Task 3, `config('inspiration')`.
- Produces: 4 adapters con `key()`: `deviantart|artstation|wallhaven|openverse`; capabilities (soportan search+explore salvo donde la API no tenga explore público — `supportsExplore=false` para Wallhaven si no define feed → usar search vacío; anotar en capabilities); `isConfigured()`: DeviantArt → config app creds presentes, resto → true. Endpoints (verbatim): DeviantArt `https://www.deviantart.com/api/v1/oauth2/search/art?q=&limit=24&offset=` con token client-credentials cacheados 24h (cache Cache::put); ArtStation `https://www.artstation.com/api/v2/feeds/projects.json?page={page}&sorting=latest` y `…&query={q}`; Wallhaven `https://wallhaven.cc/api/v1/search?q=&page=&purity=100&sorting=toplist`; Openverse `https://api.openverse.org/v1/images/?q=&page_size=20&page={page}` (tiene `explore` vía search sin q? no → `supportsExplore=false`, usa `search(' ',1)` como fallback en `explore`).
- Dataset del test: `['class' => XxxSource::class, 'key' => 'x', 'fixture' => 'search', 'assert' => fn(Page) => …]` para los 4.

- [ ] **Step 1: Test que falla** — `SourcesTest` con dataset: `Http::fake(['*' => Http::response(json_encode(Fixture::read('deviantart/search.json')))])`; instancia adapter (sin key), `search('portrait', 1, new SourceQuery)` → `Page` con items parseados (verifica `source_id`, `pageUrl`, `imageUrl` no vacíos; al menos un item); **caso `empty`** → Page sin items y `hasMore=false`; **caso `malformed`** (json con shape desconocido: items null/strings) → items vacíos sin excepción (Review Focus 1 y 3: query vacío y shape roto); **caso 500** → `SourceException`.
- [ ] **Step 2: Correr** → falla (adapters no existen).
- [ ] **Step 3: Implementar** los 4 adapters; cada uno con `normalizeRaw(array $item): ?array` (filtra sin pageUrl/imageUrl) y `mapToPage(array $payload): Page`; timeouts según tier (5s); `openverse` mapea `results[]` (clic: `url`, `thumbnail`, `foreign_landing_url`, `license`, `creator`, width/height ausentes→null).
- [ ] **Step 4: Correr** → PASS dataset completo.
- [ ] **Step 5: Commit** — `feat(inspiration): adapters DeviantArt, ArtStation, Wallhaven, Openverse`.

### Task 5: Adapters Tier 1 sin-key — batch B (Zerochan, Gelbooru, Are.na, Met, AIC)

**Files:**
- Create: `app/Inspiration/Sources/Api/{ZerochanSource,GelbooruSource,AreNaSource,MetMuseumSource,ArtInstituteChicagoSource}.php`
- Create: `tests/Fixtures/Inspiration/{zerochan,gelbooru,arena,met,aic}/{search,empty}.json`
- Modify: `InspirationServiceProvider` (tag 5)
- Test: `tests/Feature/Inspiration/SourcesTest.php` (mismos casos de dataset)

**Interfaces:**
- Consumes: contrato y manager (Task 3), settings y caché (Task 2).
- Produces: `key()`: `zerochan|gelbooru|arena|met|aic`. Endpoints: Zerochan `https://www.zerochan.net/{query}?json=1&l=24&p={page}` (header User-Agent de `SettingsBag.zerochanUa`, FALLBACK `MegalomaniacInspiration/1.0` — zerochan: por API oficial los items viven en `items[]` con `full`, `thumb`, `id`, `tags`, `author`); Gelbooru `https://gelbooru.com/index.php?page=dapi&s=post&q=index&json=1&tags={q}&pid={page-1}&limit=24` (madurez OFF agrega `-rating:explicit`, ver Task 13 — aquí solo OFF: `tags=…+-rating:explicit`); Are.na `https://api.are.na/v2/search?q={q}&type=block` (items `class=Image` con `original_image.display.url`, `image.display.url`, `title`, `created_at`); Met `https://collectionapi.metmuseum.org/public/collection/v1/search?q={q}&hasImages=true` + contras: resuelve `objectIDs` con request adicional paginado (limit 20 por página, 1 request por objeto — presupuesto bajo) → `https://collectionapi.metmuseum.org/public/collection/v1/objects/{id}`; si el presupuesto se agota, cortar con `hasMore=true`; AIC `https://api.artic.edu/api/v1/artworks/search?q={q}&fields=id,title,image_id,artist_title,date_display&page={page}` + `https://www.artic.edu/iiif/2/{image_id}/full/843,/0/default.jpg` como imagen.

- [ ] **Step 1: Test dataset** (mismos 5 casos que Task 4; Met/AIC con 2 fixtures extra `object.json` para el resolve).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** 5 adapters (Met con su búsqueda+detalle es el único con lógica extra: cola de ids pendientes — anotar en el docblock).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): adapters Zerochan, Gelbooru, Are.na, Met, Art Institute`.

### Task 6: Rutas + ExploreController (index/search, mashup, throttling)

**Files:**
- Create: `routes/inspiration.php`, `app/Http/Controllers/Inspiration/ExploreController.php`, `app/Http/Requests/Inspiration/SearchInspirationRequest.php`
- Modify: `routes/web.php` (require)
- Test: `tests/Feature/Inspiration/ExploreTest.php`

**Interfaces:**
- Consumes: `SourceManager` (Task 3), `InspirationSettings` (Task 2).
- Produces: `GET /inspiration` (Inertia `inspiration/explore`) → props: `sources` (statuses), `projects` (`[{id,name}]` type=personal del user), `boards` (`[{id,name,project_name,count}]`), `results` (`[{source,items[],has_more,from_cache,age_minutes}]` — mashup `explore` 1 página/fuente activa, `perSource=12`), `saved` (`{"source:sourceId": moodboard_id}` para marcar dedupe en UI), `search=''`, `source='all'`. `GET /inspiration/search` (mismo render con `only ['results','search','source']`) → si `source` es una key concreta → esa fuente paginada (page), si `'all'` → `searchAll` fan-out.

- [ ] **Step 1: Test que falla** — `ExploreTest`: login (factory User); con settings vacíos y adapters fake taggeados → `GET /inspiration` 200 y props contienen `results` con entries de las fuentes activas; `search` con `?q=portrait&source=all` → cada fuente activa presente; `search` con una key concreta → solo esa; **degradación**: un adapter lanzando → su entry `items=[]` y las demás ok (Review Focus de spec); **caché en fallo**: primer `search` ok (fake con fixture), segundo con el fake lanzando → entry `from_cache=true` y `age_minutes>=0`; `q` > 200 chars → 422; source inválido → 422.
- [ ] **Step 2: Correr** → falla (rutas no existen).
- [ ] **Step 3: Implementar** rutas + controller + Form Request (rules: `q nullable|string|max:200`, `source nullable|string|in:config keys`, `page integer|min:1`); en `index/search` cada llamada por fuente va por `SourceManager` (que ya hace caché write-through + throttling); jamás 500 por fuente (try/catch por fuente dentro de fan-out).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): rutas y exploración con mashup, caché y degradación`.

### Task 7: Save flow — SaveService, Controller y Jobs (thumb/full, quota, dedupe 409)

**Files:**
- Create: `app/Inspiration/InspirationSaveService.php`, `app/Inspiration/Exceptions/{DuplicateSavedImageException,DownloadQuotaExceededException}.php`, `app/Http/Controllers/Inspiration/SavedImageController.php`, `app/Jobs/Inspiration/{DownloadThumbJob,DownloadFullJob}.php`, `app/Http/Requests/Inspiration/StoreSavedImageRequest.php`
- Test: `tests/Feature/Inspiration/SaveFlowTest.php`

**Interfaces:**
- Consumes: modelos Task 1, `SourceManager::get` para validar `source`, config quota.
- Produces: `InspirationSaveService::boards(User): Collection<Moodboard>` (Inbox + por proyecto, con `project_name` y `count`); `ensureInbox(User): Moodboard` (busca `project_id null`, si no crea con name `Inbox`); `ensureMoodboardForProject(User, Project): Moodboard` (solo `type=personal` y owner, si no `AuthorizationException`; crea name = project.name); `save(User, array $item, Moodboard $board): SavedImage` — valida `source` existe, precarga duplicado (user,source,source_id) → lanza `DuplicateSavedImageException($existing)`; crea + `DownloadThumbJob::dispatch($saved)` (after commit); `requestFullDownload(User, SavedImage): void` — si `download_status === full` no-op; quota: count `downloaded_at >= today` del user ≥ `config('inspiration.max_downloads_per_day')` → `DownloadQuotaExceededException`; si no `DownloadFullJob::dispatch` (after commit).
- Rutas: `POST /inspiration/save`; `DELETE /inspiration/saved/{saved_image}`; `POST /inspiration/saved/{saved_image}/download` (binding por id, scoping owner en controller directo).
- `DownloadThumbJob::handle(SavedImage)`: `Storage::disk(config('inspiration.disk'))`, path `inspiration/{user_id}/{source}/{source_id}.thumb.{ext}`; baja `thumbnailUrl ?? imageUrl` (timeout 30s); set `thumb_path` relativo; fallo → log warn, `thumb_path` queda null (Review Focus 2 — el frontend usa `image_url`).
- `DownloadFullJob::handle(SavedImage)`: baja `imageUrl` (timeout 30s), path `…/{source_id}.full.{ext}`, set `full_path`, `downloaded_at`, `download_status='full'`; fallo → `download_status='failed'` (Review Focus 5).

- [ ] **Step 1: Test que falla** — `SaveFlowTest`: `ensureInbox` idempotente (2 llamadas → misma row; try DB también); `ensureMoodboardForProject` crea lazy y lanza para project ajeno/`type!=personal`; `save` crea saved_image + encola `DownloadThumbJob` (Queue::fake) y setea `thumb_path` tras job con `Storage::fake` + `Http::fake` (fixture 200 image bytes) ejecutado `dispatchSync`; **segundo save mismo (user,source,source_id) → `DuplicateSavedImageException`** con el existing; destroy → 204 y `Moodboard` count baja; `requestFullDownload` respeta quota (20 → excepción) y `dispatchSync` con fixture 200 setea `full`+`downloaded_at`; **fallo de thumb (Http 404) → thumb_path null y download_status sigue `thumb`**; **fallo de full (timeout fixture) → `failed`**.
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** servicio + excepciones + controller (JSON 201/409/202/204/429) + jobs (queue `default` — cola database) + Form Request (campos item: `source, source_id, image_url, page_url` required; `title, author, author_url, thumbnail_url, width, height, tags, license, maturity, note` nullable; `moodboard_id` nullable exists).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): save flow con dedupe 409, thumb local y download full bajo demanda`.

### Task 8: MoodboardController

**Files:**
- Create: `app/Http/Controllers/Inspiration/MoodboardController.php`
- Test: `tests/Feature/Inspiration/MoodboardTest.php`

**Interfaces:**
- Consumes: Task 6 (sólo reusa `boards` prop shape), Task 7.
- Produces: `GET /inspiration/moodboards/{moodboard}` (Inertia `inspiration/moodboard`) → props: `board` (id, name, project_name|null), `items` (saved_images del board, orden `created_at desc`, con `thumb_url` resuelta o `image_url` fallback, `download_status`, `full_url` nullable), `total`, `sources` (count por source).

- [ ] **Step 1: Test que falla** — `MoodboardTest`: owner ve su board (200, props con items ordenados); board ajeno → 404 (scoping por user, no policy); items con `thumb_path` null → `thumb_url = image_url` (Review Focus 2).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** controller con scoping `Moodboard::where('user_id', $user->id)` + route binding `{moodboard}` y `whereNumber`? (binding por id con scope manual).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): vista de moodboard por proyecto`.

### Task 9: Frontend Wave 1 — sidebar, explore, moodboard, componentes base

**Files:**
- Create: `resources/js/pages/inspiration/explore.tsx`, `resources/js/pages/inspiration/moodboard.tsx`, `resources/js/components/inspiration/{MasonryGrid,ImageCard,SourceChips,SaveModal}.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (item "Inspiración", icono al patrón existente), `resources/js/components/nav-main.tsx` si los items viven ahí
- Test: validación = `npm run build` + eslint (sin tests unitarios de UI en este repo)

**Interfaces:**
- Consumes: props de `ExploreController` (Task 6) y `MoodboardController` (Task 8); wayfinder `@/routes/inspiration` (regenerar + `php artisan wayfinder:generate`).
- Produces: UI con identidad Ember: masonry `columns-*`, `ImageCard` con badge de fuente (color único por source desde `sources` props), hover reveal (guardar/expandir/origen), `SaveModal` (selector de board + nota + POST save → maneja 409 mostrando board existente con link), `SourceChips` (verde/rojo/gris + "caché · hace Xh"), Infinite scroll con `router.get(..., {preserveState, preserveScroll, only:['results','search','source']})` (IntersecciónObserver en MasonryGrid), `moodboard.tsx` con acciones (quitar → DELETE, descargar full → POST download con estado, abrir original). Estados: skeleton, empty por fuente, error aislado.

- [ ] **Step 1: Sidebar + rutas wayfinder** — añade item, `php artisan wayfinder:generate`, verifica build.
- [ ] **Step 2: `explore.tsx`** — chips + búsqueda + masonry + save modal + infinite scroll + estados vacíos/carga/error-a-islado; usa tokens Ember (`bg-background`, `card`, `border`, `primary`), `animate-in` existentes, `aria-label` en icon-buttons.
- [ ] **Step 3: `moodboard.tsx`** — header (proyecto, contador), masonry, acciones por card, estado `downloading`/`failed` con reintentar.
- [ ] **Step 4: Build + lint** — `npm run build` y eslint sobre archivos nuevos; corregir.
- [ ] **Step 5: Commit** — `feat(inspiration): frontend wave 1 - explore, moodboard, componentes masonry`.

### Task 10: Settings — controller completo + página settings.tsx

**Files:**
- Create: `app/Http/Controllers/Inspiration/SettingsController.php`, `app/Http/Requests/Inspiration/UpdateInspirationSettingsRequest.php`
- Create: `resources/js/pages/inspiration/settings.tsx`
- Test: `tests/Feature/Inspiration/SettingsTest.php`

**Interfaces:**
- Consumes: `InspirationSettings::update` (Task 3), `SourceManager::get($source)->test()` + `setCredentials`.
- Produces: `GET /inspiration/settings` (Inertia `inspiration/settings`) props: `sources` (por fuente: `key,label,needs_key,has_key,configured,enabled,has_tier3_notice`), `settings` (bag serializado, sin keys expuestas completas — `has_key` bool por fuente). `PATCH /inspiration/settings` con Form Request: `enabled_sources` (array|in keys), `keys` (array de shape `{source: [key fields]}` — validación por fuente: `keys.flickr string`, etc.), `maturity` (boolean), `zerochan_ua` (nullable string), `acknowledged_tier3` (array|in keys tier3). `POST /inspiration/sources/{source}/test`: key requerida si `needsKey` (422), llama `test()` → 200 `{ok:true}` o 422 `{ok:false, message}`.

- [ ] **Step 1: Test que falla** — `SettingsTest`: GET 200 con `sources` y sin keys planas; PATCH valida `enabled_sources` inválida → 422; PATCH persiste bag (madurez true, enable deviantart); `test` con key fake → 422 ok:false; `test` sin key en fuente needsKey → 422; Tier 3 source en `acknowledged_tier3` persiste.
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** controller + Form Request (reglas por fuente desde `capabilities/needsKey` + config).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Frontend `settings.tsx`** — tabla de fuentes (toggle, input key con ojo, botón "Probar"), toggle madurez global con aviso, avisos Tier 3 con checkbox "entiendo los riesgos", PATCH + flash. Build + eslint + commit — `feat(inspiration): settings de fuentes, keys y madurez`.

### Task 11: Adapters con key — batch A (Flickr, Tumblr, Unsplash, Pexels, Pixabay)

**Files:**
- Create: `app/Inspiration/Sources/Api/{FlickrSource,TumblrSource,UnsplashSource,PexelsSource,PixabaySource}.php`
- Create: `tests/Fixtures/Inspiration/{flickr,tumblr,unsplash,pexels,pixabay}/{search,empty}.json`
- Modify: `InspirationServiceProvider` (tag 5)
- Test: `SourcesTest` (dataset con `setCredentials(['key'=>'test-key'])`)

**Interfaces:**
- Consumes: Task 2 (`SettingsBag.keys`), Task 10 (reglas de settings).
- Produces: `key()` `flickr|tumblr|unsplash|pexels|pixabay`; `needsKey=true`; `isConfigured() = credentials.key !== null`; endpoints: Flickr `https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key=&text=&page=&per_page=24&format=json&nojsoncallback=1&safe_search=1&extras=url_m,url_l,owner_name,tags` (map: id, `url_l ?? url_m`, thumb `url_m`, author owner_name, pageUrl `https://www.flickr.com/photos/{owner}/{id}`); Tumblr `https://api.tumblr.com/v2/tagged?tag={q}` (`photos[].original_size.url`, post_url) — sin explore → falls back a search con tag trending `inspiration`; Unsplash `https://api.unsplash.com/search/photos?query=&per_page=24&page=` (+ `Authorization: Client-ID`; thumb `urls.small`, full `urls.full`, user.name/links.html, alt_description); Pexels `https://api.pexels.com/v1/search?query=&per_page=24&page=` (Bearer; `src.small|large`, photographer, url); Pixabay `https://pixabay.com/api/?key=&q=&per_page=24&page=&safesearch=1` (`webformatURL`, `pageURL`, `user`, `tags`, `imageWidth/Height`).

- [ ] **Step 1: Test dataset** en `SourcesTest` (mismos casos; `empty` y `malformed`).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** 5 adapters con `query()` builders que aplican el mapeo de madurez de Global Constraints (Task 13 añadirá el switch; aquí fijos en OFF).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): adapters Flickr, Tumblr, Unsplash, Pexels, Pixabay`.

### Task 12: Adapters con key — batch B (Discogs, Giphy, Europeana, Rijksmuseum)

**Files:**
- Create: `app/Inspiration/Sources/Api/{DiscogsSource,GiphySource,EuropeanaSource,RijksmuseumSource}.php`
- Create: `tests/Fixtures/Inspiration/{discogs,giphy,europeana,rijksmuseum}/{search,empty}.json`
- Modify: `InspirationServiceProvider`
- Test: `SourcesTest`

**Interfaces:**
- `key()` `discogs|giphy|europeana|rijksmuseum`; endpoints: Discogs `https://api.discogs.com/database/search?q=&type=release&per_page=24&page=` (header `Authorization: Discogs token={token}`; imágenes `cover_image`, thumb `thumb`, título release, pageUrl `https://www.discogs.com/release/{id}`); Giphy `https://api.giphy.com/v1/gifs/search?api_key=&q=&limit=24&offset={n}&rating=g` (+`pg` en OFF; `images.original.url`, `images.fixed_width.url`, slug→pageUrl embed; `explore` → `trending`); Europeana `https://api.europeana.eu/record/v2/search.json?query=&rows=24&start={page*24-23}&wskey=` (items `previewNoDistribute` filter; `edmPreview`, `title[]`, `guid` pageUrl, `edmIsShownBy`); Rijksmuseum `https://www.rijksmuseum.nl/api/en/collection?key=&q=&ps=24&p={page}` (`webImage.url`, `links.web`, `title`, `principalOrFirstMaker`).

- [ ] **Step 1–4:** dataset TDD igual que Task 11.
- [ ] **Step 5: Commit** — `feat(inspiration): adapters Discogs, Giphy, Europeana, Rijksmuseum`.

### Task 13: Mapeo de madurez global

**Files:**
- Create: `app/Inspiration/SourceMaturity.php` (value object: `forSource(string $source, bool $allowed): SourceQuery` + `queryHash` incluye maturity)
- Modify: adapters Task 11/12/4/5 donde aplique (usar `SourceMaturity` para parametrizar)
- Test: `tests/Feature/Inspiration/MaturityTest.php` (dataset: source, OFF → param esperado, ON → param esperado)

**Interfaces:**
- Consumes: `SourceQuery.maturity`.
- Produces: mapa exacto del spec (Global Constraints). Tests: Wallhaven OFF `purity=100` / ON `purity=110` (+key); Gelbooru OFF tags ganan `-rating:explicit` / ON sin filtro; Giphy OFF `rating=g|pg` / ON `pg-13|r`; Pixabay OFF `safesearch=1` / ON `0`; Flickr OFF `safe_search=1` / ON `2|3`; DeviantArt OFF `mature_content=false` / ON `true`; Pixiv OFF solo `safe` / ON `safe|r15|r18` (param `maturity` de Pixiv — Task 19). Openverse/Are.na/met/aic/zerochan/discogs/tumblr/unsplash/pexels/europeana/rijks → sin parámetro (no-op).

- [ ] **Step 1: Test dataset** `MaturityTest` (payloads Http::fake capturados vía `Http::assertSent` con `fn($req) => str_contains($req->url(), 'purity=100')` etc.).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** `SourceMaturity` + aplicar en adapters param builders.
- [ ] **Step 4: Correr** → PASS (incluye los casos "fuente sin soporte → parámetro ausente").
- [ ] **Step 5: Commit** — `feat(inspiration): mapeo global de madurez por fuente`.

### Task 14: Infraestructura de scrapers (dom-crawler)

**Files:**
- Create: `app/Inspiration/Scraping/ScraperClient.php`, `app/Inspiration/Scraping/HtmlParser.php`
- Test: `tests/Feature/Inspiration/ScraperInfraTest.php` + `tests/Fixtures/Inspiration/html/{simple,broken}.html`

**Interfaces:**
- Consumes: config timeouts Tier 2 (12s).
- Produces: `ScraperClient::get(string $url): string` (Http::withHeaders UA `MegalomaniacInspiration/1.0 (+uso personal)` + retry 1 + timeout tier; 5xx/429 → `SourceException`). `HtmlParser::cards(string $html, array $selectors): array` — dado `{card, image, link, title}` (selectors CSS con fallback: `card` puede ser array de 2 selectores intentados en orden), extrae pares (imageUrl absoluto, pageUrl absoluto, title?), filtra inválidos. `broken.html` (sin cards) → array vacío sin excepción (Review Focus 3/1 sobre HTML).

- [ ] **Step 1: Test que falla** — `ScraperInfraTest` con `Http::fake` (html fixture 200; 500 → SourceException; timeout → SourceException; `HtmlParser` con selectores A/B fallback y fixture rota → vacío).
- [ ] **Step 2: Correr** → falla (clases y dependencia ausentes — `composer require symfony/dom-crawler symfony/css-selector` primero; contemplado en spec).
- [ ] **Step 3: Implementar** `ScraperClient` + `HtmlParser` (CSS selector engine: `->filter()`; primeros intentos y fallback).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): infraestructura de scraping con dom-crawler`.

### Task 15: Scrapers batch A (Designspiration, Savee, Trend List, PosterSpy)

**Files:**
- Create: `app/Inspiration/Sources/Scrape/{DesignspirationSource,SaveeSource,TrendListSource,PosterSpySource}.php`
- Create: `tests/Fixtures/Inspiration/{designspiration,savee,trendlist,posterspy}/{feed,broken}.html`
- Modify: `InspirationServiceProvider`
- Test: `SourcesTest` (dataset con `Html::fake` vía `Http::fake`)

**Interfaces:**
- `key()` `designspiration|savee|trendlist|posterspy`; `explore` = fetch de página (Designspiration `https://www.designspiration.net/explore/` y `…/search/{q}/`; Savee `https://savee.it/` y `https://savee.it/search/?q=`; Trend List `https://trendlist.org/` + `https://trendlist.org/?search=`; PosterSpy `https://posterspy.com/` + `?s={q}`); selectores a definir en los fixtures (card: imagen css/background, link, title); `search` = página de búsqueda correspondiente; `supportsExplore=true`. Props de madurez: n/a.

- [ ] **Step 1: Test dataset** (feed fixture parse → items; broken → vacío/isla).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** 4 adapters sobre `HtmlParser` (selectores realistas anotados en docblock y verificados por fixtures).
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): scrapers Designspiration, Savee, Trend List, PosterSpy`.

### Task 16: Scrapers batch B (Lapa Ninja, Godly, Dark Mode, Brutalist + Brutal Web)

**Files:**
- Create: `app/Inspiration/Sources/Scrape/{LapaNinjaSource,GodlySource,DarkModeDesignSource,BrutalistSources}.php` (Brutalist + Brutal Web en un adapter `brutalist` con dos feeds)
- Create: `tests/Fixtures/Inspiration/{lapaninja,godly,darkmode,brutalist}/{feed,broken}.html`
- Modify: `InspirationServiceProvider`
- Test: `SourcesTest`

**Interfaces:**
- `key()` `lapaninja|godly|darkmode|brutalist`; feeds: Lapa `https://www.lapa.ninja/` (+ `/page/2/`), Godly `https://godly.website/` (+ `/trending`), Dark Mode `https://www.darkmodedesign.com/`, Brutalist `https://brutalistwebsites.com/` + `https://brutalweb.xyz/`; screenshots como imagen (og:image / first `<img>` grande); search solo donde exista (Lapa `/search`, Godly `/search?q=`) — si una fuente no tiene search propio → `supportsSearch=false` y `search()` delega a `explore()` con log warn.

- [ ] **Step 1–4:** dataset TDD igual batch A.
- [ ] **Step 5: Commit** — `feat(inspiration): scrapers Lapa Ninja, Godly, Dark Mode, Brutalist`.

### Task 17: Scrapers batch C (Behance, Dribbble, Awwwards)

**Files:**
- Create: `app/Inspiration/Sources/Scrape/{BehanceSource,DribbbleSource,AwwwardsSource}.php`
- Create: `tests/Fixtures/Inspiration/{behance,dribbble,awwwards}/{feed,broken}.html`
- Modify: `InspirationServiceProvider`
- Test: `SourcesTest`

**Interfaces:**
- `key()` `behance|dribbble|awwwards`; feeds: Behance `https://www.behance.net/search/projects?search={q}` y `https://www.behance.net/galleries` (`cover` imgs, links `/gallery/{id}/{slug}`), Dribbble `https://dribbble.com/search/shots?q={q}` y `https://dribbble.com/shots/popular` (imágenes `//cdn.dribbble.com/…` en `<img>`/`picture source`), Awwwards `https://www.awwwards.com/websites/` y `https://www.awwwards.com/search/?q=` (screenshots `.site-img`); todas `supportsSearch=true`.

- [ ] **Step 1–4:** dataset TDD.
- [ ] **Step 5: Commit** — `feat(inspiration): scrapers Behance, Dribbble, Awwwards`.

### Task 18: Tier 3 — avisos UI + WikiArt + Newgrounds

**Files:**
- Create: `app/Inspiration/Sources/Api/WikiArtSource.php`, `app/Inspiration/Sources/Scrape/NewgroundsSource.php`
- Create: `tests/Fixtures/Inspiration/{wikiart,newgrounds}/{search,empty}.json|html`
- Modify: `InspirationServiceProvider`, `UpdateInspirationSettingsRequest` (acknowledged_tier3 ya existe)
- Modify: `resources/js/pages/inspiration/settings.tsx` (sección Tier 3 con `Tier3Notice` checkbox)
- Test: `SourcesTest` + `SettingsTest` (activar tier3 sin ack → 422)

**Interfaces:**
- `key()` `wikiart|newgrounds`; **gate**: `activeConfigured()` solo incluye tier3 si `bag.acknowledgedTier3` contiene la key (implementar en `SourceManager::activeConfigured` leyendo `config('inspiration.tier3')` keys — retrofit pequeño de Task 3 + test en `SourceManagerTest`); WikiArt `https://www.wikiart.org/en/App/Painting/Newest?json=2` (key app-level opt o user — anotar: key por solicitud, si falta → `isConfigured=false`), search vía endpoint de WikiArt (Paintings by search? → usar `Newest` + filtro cliente — documentar); Newgrounds scrape `https://www.newgrounds.com/art/browse?sort=date` + `search: https://www.newgrounds.com/search/conduct/post?query={q}&category=art` (cards con thumbs, links `/art/view/{id}`).

- [ ] **Step 1: Test gate** en `SourceManagerTest` (nuevo caso: tier3 sin ack → excluida; con ack → incluida) — rojo.
- [ ] **Step 2: Retrofit** `activeConfigured` + `SettingsTest` (PATCH tier3 sin ack → 422) — verde.
- [ ] **Step 3: Adapters** WikiArt + Newgrounds (dataset en `SourcesTest`) + fixtures.
- [ ] **Step 4: UI** `settings.tsx` sección Tier 3 (notice + checkbox ack), build + eslint.
- [ ] **Step 5: Commit** — `feat(inspiration): tier 3 opt-in con aviso, WikiArt y Newgrounds`.

### Task 19: Pixiv + Bandcamp (tier 3)

**Files:**
- Create: `app/Inspiration/Sources/Api/PixivSource.php`, `app/Inspiration/Sources/Api/BandcampSource.php`
- Create: `tests/Fixtures/Inspiration/{pixiv,bandcamp}/{search,empty}.json`
- Modify: `InspirationServiceProvider`, config (pixiv refresh token key path)
- Test: `SourcesTest` + `MaturityTest` (Pixiv r18 case)

**Interfaces:**
- `key()` `pixiv|bandcamp`; Pixiv: refresh token del usuario (settings keys.pixiv.refresh_token) → token access vía `https://oauth.secure.pixiv.net/auth/token` (grant refresh_token, fixtures), search `https://app-api.pixiv.net/v1/search/illust?word=&search_target=partial_match_for_tags&offset={n}` (header `Authorization: Bearer`), items `illusts[]` (`id`, `title`, `image_urls.large`, `user.name`, `tags`, `type=ugoira` → skip en adapters inicial), madurez: OFF solo `safe` (param `filter=for_ios`), ON `maturity` según Task 13; **refresh token es obligatorio → `isConfigured=false` sin él**; Bandcamp: `https://bandcamp.com/api/discover/1/get_discover_items` + search `https://bandcamp.com/api/... ` (documentar formato real desde michaelherger/Bandcamp-API; si el shape cambia → `broken` fixture y degradación), items portadas `art_id`→ `https://f4.bcbits.com/img/a{art_id}_10.jpg`, `tralbum_url`, `band_name`, título.

- [ ] **Step 1: Test dataset** (pixiv search parse + ugoira skip; bandcamp discover parse; broken → SourceException).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** ambos (Pixiv: flujo refresh→access con Cache 1h; Bandcamp: POST discover con payload mínimo).
- [ ] **Step 4: Correr** + MaturityTest r18 → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): Pixiv (refresh token) y Bandcamp (discover)`.

### Task 20: Pinterest + Cara + Mobbin (tier 3 — scraping frágil)

**Files:**
- Create: `app/Inspiration/Sources/Scrape/{PinterestSource,CaraSource,MobbinSource}.php`
- Create: `tests/Fixtures/Inspiration/{pinterest,cara,mobbin}/{feed,broken}.html`
- Modify: `InspirationServiceProvider`
- Test: `SourcesTest` (broken es el caso principal: estos adapters DEBEN degradar con caché)

**Interfaces:**
- `key()` `pinterest|cara|mobbin`; filosofía: "best effort" — `explore` (Pinterest `https://www.pinterest.com/search/pins/?q={q}` devuelve HTML con JSON embebido `pins` en `<script>`; parse `application/ld+json` o variable global; si falla → `SourceException` → caché), Cara `https://cara.app/explore` (docs JSON `__NEXT_DATA__` o endpoint público; respeto: 1 req cada 30s, sin auth), Mobbin `https://mobbin.com/browse/ios/apps` (requiere sesión → `feed` usa páginas públicas si existen, si no → `isConfigured=false` y se muestra "requiere cuenta" en settings). Ninguna de las tres soporta `search` completo → `supportsSearch=false`, `explore` es la superficie; `ratePerMinute=2` (limiter). Fixture `broken` = HTML sin JSON → SourceException → degradación (Review Focus: caché amortigua).

- [ ] **Step 1: Test dataset** (feed parse; broken → SourceException; `isConfigured` de mobbin false sin clave de sesión; throttling rate 2/min verificado en `SourceManagerTest` retrofit).
- [ ] **Step 2: Correr** → falla.
- [ ] **Step 3: Implementar** 3 adapters best-effort.
- [ ] **Step 4: Correr** → PASS.
- [ ] **Step 5: Commit** — `feat(inspiration): Pinterest, Cara y Mobbin (best-effort con degradación)`.

### Task 21: Polish final — anti-ui-slop gate, lightbox, docs, QA

**Files:**
- Create: `resources/js/components/inspiration/Lightbox.tsx`, `resources/js/pages/inspiration/explore.tsx` (polish), `docs/modules/inspiration.md`
- Modify: `README.md` (mención módulo) si se ajusta al patrón existente

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: Lightbox full-screen (teclado ← →, escape, metadatos, tags, licencia, guardar/descargar/abrir en fuente); empty states con copy del producto; skeletons; a11y (focus trap, aria); **finish gate anti-ui-slop** (skills: ui-radar para referencias del muro; anti-ui-slop playbook `new-work` aplicado a explore/moodboard: sin grid cuadrado genérico, masonry real, jerarquía visual con el badge de fuente).

- [ ] **Step 1: Lightbox + polish** explore/moodboard (motion solo `animate-in` existentes, tokens Ember, hover states).
- [ ] **Step 2: Docs** — `docs/modules/inspiration.md`: setup de keys por fuente (links oficiales), tier 3 caveats, mapeo madurez, QA diario (flujo: Landing → Login → Inspiración → explorar → guardar en proyecto → moodboard → descargar full).
- [ ] **Step 3: Suite completa** — `php artisan test --compact` (todo verde), `vendor/bin/pint --dirty --format agent`, `npm run build`, eslint global.
- [ ] **Step 4: QA manual** — flujo Playwright del AGENTS.md + el paso nuevo de Step 2 corriendo contra `:8010` (si el entorno lo permite); registrar en `docs/qa/playwright-report.md` según convención.
- [ ] **Step 5: Commit** — `feat(inspiration): polish final, lightbox y docs del módulo`.