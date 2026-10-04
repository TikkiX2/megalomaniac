# Módulo Inspiración — Moodboards multi-fuente — Design Spec

**Fecha:** 2026-10-04
**Estado:** Aprobado (brainstorming con el usuario, enfoque A: live + caché write-through)
**Alcance:** Nuevo módulo "Inspiración": explorar y buscar imágenes desde ~35 plataformas externas (APIs oficiales, JSON públicos y scrapers HTML), guardarlas en moodboards vinculados a proyectos personales (Inbox + uno por proyecto), descargar la imagen completa bajo demanda, con toggle global de madurez.

## Problema

1. **Pinterest está flojo** y es la única ventana de inspiración visual del usuario. El resto de las plataformas buenas (Cara, Are.na, DeviantArt, ArtStation, Pixiv, Zerochan, Trend List, museos…) están dispersas y sin una forma unificada de explorarlas y guardar.
2. **No existe persistencia de inspiración**: las imágenes referentes de cada proyecto se pierden o viven en pestañas sueltas.
3. **El ecosistema 2026 es hostil al scraping improvisado**: Reddit mató su `.json`, Pinterest no tiene API de búsqueda, Behance murió para keys nuevas, Artsy retira su API pública. El módulo necesita un diseño adaptador que aísle cada fuente y degrade con elegancia cuando una se rompe.

## Decisiones

- **Enfoque A — live + caché write-through.** Cada búsqueda/exploración llama a la fuente real; el resultado se persiste en `inspiration_cache` (TTL por fuente/tipo); si la fuente falla, se sirve caché con badge "caché · hace Xh"; si no hay caché, la fuente se marca caída en los chips y el resto del mazo sigue intacto (aislamiento por fuente).
- **Moodboards por proyecto.** `moodboards` 1:1 con `projects` de tipo `personal` (herencia single-table existente: `Project` con `type='personal'`, `PersonalProjectController`), creado lazy al primer guardado, más un **Inbox** por usuario (moodboard sin proyecto) para guardar rápido.
- **Guardado: miniatura + lazy full.** `saved_images` guarda metadatos + miniatura local (Storage, disco configurable `inspiration.disk`). La imagen completa se descarga bajo demanda vía job en cola (tope diario por usuario).
- **Roster de 35 fuentes en 3 tiers** (tabla abajo): Tier 1 APIs/JSON estables (9 activas sin configurar nada), Tier 2 scrapers HTML curatoriales (activadas, sin key), Tier 3 opt-in (requieren key del usuario, token, o riesgo ToS: Pixiv, Pinterest, Cara.app, Bandcamp, WikiArt, Newgrounds/Mobbin). Descartadas: Reddit (muerto 2026), Artsy (retira API pública), Mobbin API oficial (solo planes Team/Enterprise).
- **Madurez: toggle global (default OFF = SFW estricto).** Cada fuente mapea su rango propio (tabla de mapeo abajo). Con toggle OFF se fuerza el nivel más seguro; con ON se respeta el rango de la fuente.
- **Dedupe:** `unique(user_id, source, source_id)` en `saved_images`; guardar algo ya guardado en otro moodboard → HTTP 409 con aviso.
- **Scrapers:** `composer require symfony/dom-crawler symfony/css-selector`, User-Agent descriptivo, baja frecuencia, sin bypass de login, uso personal. Tier 3 con aviso explícito en UI antes de activar.
- **Sin nuevas dependencias de infra:** cola y caché ya usan driver `database` (verificado en `.env`).
- **UI con sistema existente:** paleta Ember (`docs/design-tokens.md`, tokens `bg-background`/`card`/`border`/`primary`), componentes `@/components/ui/*`, sidebar 8º item "Inspiración". Aplicar anti-ui-slop + ui-radar durante el build (muro masonry real tipo Pinterest/Are.na, no grid cuadrado genérico).
- **Fuera de alcance (YAGNI):** múltiples moodboards por proyecto, drag&drop/reordenamiento, recomendaciones AI, compartir público, API pública del módulo, proxies/anti-bot pagados, modo offline total, Mobbin API oficial (paga).

## Roster de fuentes (35)

### Tier 1 — APIs / JSON estables

| Fuente | Acceso | Default | Madurez |
|---|---|---|---|
| DeviantArt | API oficial OAuth2 client-credentials (client_id/secret en `.env` de la app) | ● activa | `mature_content` bool; toggle ON → allowed |
| ArtStation | JSON público (`/api/v2/feeds/projects{?sorting,query}`) | ● | n/a |
| Wallhaven | API v1 sin key (SFW solos sin key) | ● | purity `sfw|sketchy|nsfw` (nsfw requiere key del usuario) |
| Openverse | API v1 anónima (1 req/s, page ≤ 20) | ● | n/a (CC) |
| Zerochan | API oficial read-only (UA `{proyecto}-{usuario}` en settings, 60 req/min) | ● | n/a |
| Gelbooru | API pública (`score:…` tags, rating s/q/e) | ● | rating `s|q|e` |
| Are.na | API v3 pública sin auth (`/v3/channels/search`, `/v3/search` blocks) | ● | n/a |
| Met Museum | API pública sin key (`/public/v1/search`) | ● | n/a (open access) |
| Art Institute of Chicago | API pública sin key (`/api/v1/artworks/search`) | ● | n/a (public domain) |
| Flickr | API key (`flickr.photos.search`, `safe_search`) | 🔑 off hasta key | `safe_search 1|2|3` |
| Tumblr | API v2 key (`/blog/{blog}/posts/photo`, tags) | 🔑 | n/a |
| Unsplash | API key (50 req/h) | 🔑 | n/a |
| Pexels | API key (200 req/h) | 🔑 | n/a |
| Pixabay | API key | 🔑 | `safesearch` |
| Discogs | Token de usuario (search database, imágenes de release) | 🔑 | n/a |
| Giphy | Beta key (100 req/h) | 🔑 | `rating g|pg|pg-13|r` |
| Europeana | API key (search + IIIF) | 🔑 | n/a |
| Rijksmuseum | API key | 🔑 | n/a |

### Tier 2 — Scraping HTML curatorial (activas, sin key)

| Fuente | Nota |
|---|---|
| Designspiration | Feed página + búsqueda, extrapola cards |
| Savee | Moodboarding colaborativo |
| Trend List | Tendencias gráficas/editorial/posters 2011+ |
| PosterSpy | Posters alternativos de cine |
| Lapa Ninja | 7.300+ landings curadas |
| Brutalist Websites (+ Brutal Web) | Neo-brutalismo |
| Godly | Web design curatorial |
| Dark Mode Design | Dark UI |
| Behance | Proyectos de diseño (HTML server-rendered) |
| Dribbble | Shots (HTML server-rendered) |
| Awwwards | Awards de web design |

### Tier 3 — Opt-in (off por defecto; aviso en UI antes de activar)

| Fuente | Nota |
|---|---|
| Pixiv | API App no oficial; requiere refresh token del usuario; rango `safe|r15|r18` |
| Pinterest | Sin API de búsqueda; scraping con anti-bot; puede romperse → caché la amortigua |
| Cara.app | Plataforma joven anti-AI; sin API oficial; scrape respetuoso, baja frecuencia |
| Bandcamp | API no documentada (`/api/discover/…`); portadas de álbumes |
| WikiArt | Key por solicitud (400 req/h); 2,5M pinturas |
| Newgrounds | Portal de arte indie (HTML) |
| Mobbin | Solo API no oficial con sesión de cuenta (frágil); screencaps de apps |

## Arquitectura

### Backend — `app/Inspiration/`

- **`Contracts\Source.php`** — contrato único para las 35 fuentes:

```php
interface Source
{
    public function key(): string;                                  // 'deviantart', 'trendlist'…
    public function label(): string;
    public function capabilities(): SourceCapabilities;             // supportsSearch, supportsExplore, needsKey, hasMaturityLevels, maxPageSize, ratePerMinute
    public function isConfigured(): bool;                           // true si no necesita key o si la key del usuario está puesta
    public function search(string $query, int $page, SourceQuery $queryOptions): Page; // Page<InspirationItem>
    public function explore(int $page, SourceQuery $queryOptions): Page;
}
```

  - `SourceQuery`: filtros normalizados (madurez pedida, categoría/tags libres por fuente pasados tal cual).
  - `Page`: `{items: InspirationItem[], hasMore: bool, nextPage: ?int}`.
  - `InspirationItem` (DTO): `source, sourceId, title?, author?, authorUrl?, pageUrl, imageUrl, thumbnailUrl?, width?, height?, tags[], dominantColor?, license?, maturity?`.
- **`SourceManager`** — registry de las 35 instancias (bind en `AppServiceProvider` o Provider propio `InspirationServiceProvider`), dispatch con try/catch por fuente, throttling por fuente (RateLimiter `inspiration:{source}`), resolución de "fuente activa y configurada".
- **`Sources\Api\*Source.php`** — adapters HTTP JSON (`Illuminate\Http\Client` con timeouts por tier: 5s Tier 1, 12s Tier 2, 10s Tier 3; 1 retry con backoff corto).
- **`Sources\Scrape\*Source.php`** — adapters fetch + `symfony/dom-crawler`, selectores con fallback (2 variantes si el sitio cambia), User-Agent `MegalomaniacInspiration/1.0 (+uso personal)`.
- **`InspirationCache`** — write-through sobre `inspiration_cache`: clave `{source}:{kind}:{queryHash}` (queryHash incluye madurez pedida), TTL por fuente (search 30 min / explore 60 min / scrapers 6 h), en fallo `read latest fresh` devuelve payload + `ageMinutes`.

### Modelo de datos

| Tabla | Columnas |
|---|---|
| `moodboards` | `id`, `user_id` (FK), `project_id` (FK `projects`, nullable, unique, solo `type=personal`), `name`, `created_at`. Partial unique: un Inbox por usuario (`user_id` WHERE `project_id IS NULL`). |
| `saved_images` | `id`, `user_id`, `moodboard_id` (FK), `source`, `source_id`, `title?`, `author?`, `author_url?`, `page_url`, `image_url`, `thumb_path`, `full_path?`, `width?`, `height?`, `tags` (json), `license?`, `maturity?`, `note?`, `download_status` (`thumb\|full\|failed`), `downloaded_at?`, `created_at`. **Index unique `(user_id, source, source_id)`**. Semántica de `download_status`: `thumb` = solo miniatura local (default); `full` = completa descargada; `failed` = falló la última descarga solicitada (miniatura o full). |
| `inspiration_cache` | `id`, `source`, `kind` (`search\|explore`), `query_hash`, `payload` (json), `fetched_at`, `expires_at` (index). |
| `inspiration_settings` | `user_id` (PK/FK, único), `body` (json): `enabled_sources[]`, `keys{}` (por fuente), `maturity` (bool), `zerochan_ua` (string opcional, formato `Megalomaniac-{username}`), `acknowledged_tier3[]` (keys de fuentes Tier 3 cuyo aviso fue aceptado). |

### Rutas — `routes/inspiration.php` (require en `web.php`, grupo `auth + verified`)

| Ruta | Método | Controller |
|---|---|---|
| `GET /inspiration` | `index` | `Inspiration\ExploreController@index` — página explore (Inertia: chips con estado, primera página de exploración) |
| `GET /inspiration/search` | `search` | `@search` — resultados con `only['results', 'filters']` para infinite scroll (`preserveState/preserveScroll`) |
| `POST /inspiration/save` | `store` | `Inspiration\SavedImageController@store` — dedupe (409 si ya existe en otro board), encola `DownloadThumbJob` |
| `DELETE /inspiration/saved/{savedImage}` | `destroy` | `@destroy` |
| `POST /inspiration/saved/{savedImage}/download` | `download` | `@download` — encola `DownloadFullJob` (tope diario `config('inspiration.max_downloads_per_day', 20)`) |
| `GET /inspiration/moodboards/{moodboard}` | `show` | `Inspiration\MoodboardController@show` |
| `GET /inspiration/settings` | `index` | `Inspiration\SettingsController@index` |
| `PATCH /inspiration/settings` | `update` | `@update` — Form Request: `enabled_sources` (array de keys válidas), `keys` (por fuente), `maturity` (bool) |
| `POST /inspiration/sources/{source}/test` | `test` | `@test` — prueba de conexión con la key del usuario (timeout corto) |

Controllers: `App\Http\Controllers\Inspiration\{ExploreController, SavedImageController, MoodboardController, SettingsController}`. Autorización: owner-scope en todos (scope query por `user_id`, policy simple o `where(user_id)`).

### Frontend — `resources/js/pages/inspiration/`

- **`explore.tsx`** — el corazón del módulo:
  - Barra de búsqueda + except chips de fuentes (verde=ok, rojo=fallo → "caché · hace Xh", gris=desactivada, `+` para activar en settings).
  - Muro **masonry real** (CSS `columns`) con `ImageCard`: imagen protagonista, hover revela botones (guardar, expandir, abrir en fuente) y badge de fuente con color único por fuente.
  - "Todo": mashup 1 página por fuente (round-robin). Fuente individual: paginación propia con IntersectionObserver + `router.get(..., {preserveState, preserveScroll, only:['results','filters']})`.
  - `SaveModal`: elegir moodboard (Inbox + proyectos personales con su moodboard), nota opcional, estado 409 mostrado como aviso.
  - Lightbox full-screen: imagen grande, metadatos, tags, licencia (si la trae), acciones (guardar, descargar full, abrir en fuente). Soporta navegación ← → sin salir.
  - Estados: skeleton pulsante por fuente, empty ("sin resultados en esta fuente"), error aislado por chip.
- **`moodboard.tsx`** — muro del moodboard de un proyecto (`/inspiration/moodboards/{board}`): grid masonry, orden `created_at` desc, acciones por card (quitar, descargar full, abrir original), header con nombre del proyecto + contador + botón "explorar más".
- **`settings.tsx`** — tabla de fuentes: toggle por fuente, inputs de key por fuente (password-style + "probar"), toggle global madurez con aviso, avisos Tier 3 (texto: "acceso no oficial, puede romperse / requiere tu cuenta"), link a docs.
- Sidebar: 8º item "Inspiración" (icono material-symbols `auto_awesome`), consistente con `app-sidebar.tsx`/`nav-main.tsx`.
- Consistencia: `Dialog`, `Input`, `Button`, `Switch`/`Checkbox`, `Label` existentes; tokens Ember; `animate-in` existentes; `aria-label` en icon-buttons.

### Mapeo de madurez (toggle global)

| Fuente | OFF (SFW estricto) | ON |
|---|---|---|
| Wallhaven | `purity=100` (sfw) | + `sketchy`/`nsfw` si key del usuario |
| Gelbooru | filtro `-rating:explicit` | todos |
| Giphy | `rating=g|pg` | `pg-13|r` |
| Pixabay | `safesearch=1` | `0` |
| Flickr | `safe_search=1` | `2|3` |
| DeviantArt | `mature_content=false` | `true` |
| Pixiv | solo `safe` (ignora r15/r18) | `safe|r15|r18` |
| Wikimedia/Openverse/museos/Are.na | n/a | n/a |

## Manejo de errores

- **Fuente caída** (`SourceException` timeout/5xx/parse): SourceManager captura, registra el fallo en el chip (estado `degraded`), sirve caché si existe (badge de edad), si no existe caché el chip pasa a `down` y el mazo omite la fuente. Nunca 500 al usuario por una fuente.
- **409 dedupe**: `saved_images` único (user, source, source_id) → respuesta 409 con `existing_moodboard_name`; frontend muestra aviso con link al moodboard existente.
- **Throttle de la fuente externa (429)**: mapeado a `degraded` + caché (mismo camino que fallo).
- **Keys inválidas en settings**: `test` devuelve 422 con mensaje de la fuente; la fuente queda marcada `misconfigured` en chips.
- **Quota de descargas full**: 429 con mensaje "límite diario alcanzado".
- **Scraper que no reconoce el HTML**: parse nulo → `SourceException('estructura cambiada')` → degraded (el detrás de escena se revisa con los fixtures de QA).

## Datos

- **Migraciones:** 4 (`moodboards`, `saved_images`, `inspiration_cache`, `inspiration_settings`).
- **Config:** `config/inspiration.php` — disk (`'inspiration.disk' => env('INSPIRATION_DISK', 'local')`, subpath `inspiration/{user_id}/{source}/`), TTLs por tier, timeouts, `max_downloads_per_day`, claves app-level (DeviantArt client credentials etc. vía `services.php` o `config/inspiration.php` con env).
- **Documentación:** `docs/modules/inspiration.md` (setup de keys por fuente, tier 3 caveats, QA diario).
- **Deploy note:** `composer require symfony/dom-crawler symfony/css-selector`, `php artisan wayfinder:generate`, `npm run build`.

## Testing (Pest, TDD)

- **Fixtures:** `tests/Fixtures/inspiration/<source>.json|html` (1 fixture happy path + 1 vacío por fuente) + `Http::fake()` por adapter.
- `tests/Feature/Inspiration/SourcesTest.php` (dataset por fuente): parse correcto a `InspirationItem` (campos mínimos), página vacía → `Page` vacío, respuesta 500/429 → `SourceException`, HTML sin selectores → `SourceException`.
- `tests/Feature/Inspiration/SaveFlowTest.php`: save crea moodboard lazy del proyecto (o Inbox), encola `DownloadThumbJob`; dedupe → 409 con nombre del board existente; destroy; download full con tope diario; ajeno → 404/403.
- `tests/Feature/Inspiration/MoodboardTest.php`: show del moodboard por proyecto (solo proyectos personales del owner), Inbox único por usuario.
- `tests/Feature/Inspiration/SettingsTest.php`: validación de keys por fuente, `test` de conexión con `Http::fake`, toggle madurez persiste, Tier 3 requiere aviso (flag `acknowledged`).
- `tests/Feature/Inspiration/ExploreTest.php`: degradación (fuente 500 → otras fuentes responden, chip `down`), caché write-through (segunda llamada con fallo → sirve caché), madurez OFF fuerza safe en el payload cacheado.
- Cierre por fase: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run build`, eslint de archivos tocados, QA Playwright diario (flujo AGENTS.md + paso nuevo: Landing → Login → Inspiración → explorar → guardar en proyecto → moodboard → descargar full).

## Fases

1. **Núcleo**: migraciones, contrato + DTO + SourceManager + caché + config + Provider; 9 fuentes Tier 1 sin key de usuario (client-credentials de DeviantArt viven en `.env` a nivel app; ArtStation, Wallhaven, Openverse, Zerochan, Gelbooru, Are.na, Met y AIC no requieren nada) con sus tests; explore page + save + Inbox; sidebar item; `docs/modules/inspiration.md` inicial.
2. **Tier 1 con key**: settings page completa (toggles, keys, test), Flickr, Tumblr, Unsplash, Pexels, Pixabay, Discogs, Giphy, Europeana, Rijksmuseum + tests; toggle madurez global con mapeo.
3. **Scrapers Tier 2**: infra DOM (dom-crawler, UA, fallback de selectores), Designspiration, Savee, Trend List, PosterSpy, Lapa Ninja, Brutalist(+Brutal Web), Godly, Dark Mode, Behance, Dribbble, Awwwards + tests + fixtures HTML.
4. **Tier 3 opt-in**: avisos UI, Pixiv, Pinterest, Cara.app, Bandcamp, WikiArt, Newgrounds, Mobbin + tests (algunos con fixtures mínimas; Pinterest/Pixiv con tests de contrato y degradación).
5. **Polish**: anti-ui-slop finish gate sobre explore/moodboard (ui-radar para referencias del muro y lightbox), empty states, skeleton, accesibilidad, QA Playwright extendido, docs final.