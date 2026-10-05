# Módulo Inspiración

Explorador y tablero de referencias visuales multi-fuente: busca imágenes en ~35 plataformas externas (APIs oficiales, JSON públicos y scrapers HTML), guárdalas en moodboards vinculados a proyectos personales (Inbox + uno por proyecto) y descarga la imagen completa bajo demanda.

## Qué es

- **Explorar** (`/inspiration`): mashup de una página por fuente activa, con chips de salud por fuente, búsqueda fan-out, muro masonry real y lightbox full-screen.
- **Moodboards** (`/inspiration/moodboards/{board}`): muro persistido por proyecto, orden `created_at` desc, quitar, descargar full y abrir original.
- **Ajustes** (`/inspiration/settings`): toggles por fuente, credenciales por fuente, prueba de conexión, toggle global de madurez y aviso/aceptación Tier 3.

## Arquitectura de un vistazo — `app/Inspiration/`

| Pieza | Rol |
|---|---|
| `Contracts\Source` | Contrato único de adaptador: `key()`, `label()`, `capabilities()`, `isConfigured()`, `setCredentials()`, `search()`, `explore()`, `test()`. |
| `SourceManager` | Registry + dispatch. Resuelve adapters del tag de contenedor `inspiration.sources`, aplica throttling por fuente, caché write-through y persistencia de fallos. |
| `Sources\Api\*Source` | Adapters HTTP JSON (`Illuminate\Http\Client`), timeouts por tier (5s / 12s / 10s), 1 retry. |
| `Sources\Scrape\*Source` | Fetch + `symfony/dom-crawler`, selectores con fallback, UA `MegalomaniacInspiration/1.0 (+uso personal)`, baja frecuencia. |
| `InspirationCache` | Caché en `inspiration_cache` con clave `{source}:{kind}:{queryHash}` (el hash incluye la madurez pedida). TTL: search 30 min, explore 60 min, scrapers 6 h. |
| `SourceMaturity` | Único dueño del token abstracto `safe`/`allowed`; cada adapter lo mapea a su parámetro real. |
| `InspirationSaveService` | Guarda miniatura local, dedupe `(user, source, source_id)`, encola descarga full con tope diario. |
| `InspirationSettings` | Bag por usuario en `inspiration_settings.body`: `enabled_sources`, `keys`, `maturity`, `zerochan_ua`, `acknowledged_tier3`. |
| `InspirationServiceProvider` | Registra `SourceManager` singleton y tagea las clases de `SOURCES`. |

Frontend (`resources/js/`):

- `pages/inspiration/{explore,moodboard,settings}.tsx`
- `components/inspiration/{ImageCard,MasonryGrid,SourceChips,SaveModal,Lightbox}.tsx` + `shared.ts` (tipos y helpers).
- `Lightbox` es full-screen compartido por explorar y moodboard: metadatos, tags, licencia, guardar/descargar/abrir en fuente, navegación ← → dentro del grid activo, Escape cierra, focus trap y retorno de foco al card de origen.

### Modelo de datos

| Tabla | Contenido |
|---|---|
| `moodboards` | `user_id`, `project_id` (nullable, único, solo proyectos `personal`), `name`. Un Inbox por usuario (partial unique `user_id WHERE project_id IS NULL`). |
| `saved_images` | metadatos + `thumb_path`/`full_path`, `download_status` (`thumb\|full\|failed`), `note`, tags/licencia/madurez. Unique `(user_id, source, source_id)`. |
| `inspiration_cache` | `source`, `kind`, `query_hash`, `payload` (json), `fetched_at`, `expires_at`. |
| `inspiration_settings` | `user_id` (PK), `body` (json). |

### Rutas (`routes/inspiration.php`, grupo `auth + verified`)

`GET /inspiration` · `GET /inspiration/search` · `POST /inspiration/save` · `DELETE /inspiration/saved/{id}` · `POST /inspiration/saved/{id}/download` · `GET /inspiration/moodboards/{id}` · `GET|PATCH /inspiration/settings` · `POST /inspiration/sources/{source}/test`.

## Setup de keys por fuente

Las fuentes sin key del usuario (DeviantArt, ArtStation, Wallhaven, Openverse, Zerochan, Gelbooru, Are.na, Met, AIC y todos los scrapers Tier 2) funcionan sin configurar nada. **Wallhaven** acepta una key **opcional** (columna de credenciales en Ajustes) que, junto al toggle de madurez, desbloquea sketchy/NSFW. DeviantArt usa credenciales **a nivel app** en `.env`: `DEVIANTART_CLIENT_ID` / `DEVIANTART_CLIENT_SECRET` (registrar en https://www.deviantart.com/developers/apps).

Las credenciales por usuario se cargan en **Ajustes de inspiración** y se guardan en el servidor (nunca se devuelven al cliente). El campo canónico por fuente está en `config/inspiration.php` → `credential_fields`.

| Fuente | Campo | Cómo obtenerla |
|---|---|---|
| Flickr | `key` | https://www.flickr.com/services/apps/create/ — API key |
| Tumblr | `key` | https://www.tumblr.com/oauth/apps — registrar app, usar la consumer/API key |
| Unsplash | `key` | https://unsplash.com/developers — crear app (Demo: 50 req/h) |
| Pexels | `key` | https://www.pexels.com/api/ — API key (200 req/h) |
| Pixabay | `key` | https://pixabay.com/api/docs/ — API key |
| Discogs | `token` | https://www.discogs.com/settings/developers — personal access token (https://www.discogs.com/developers) |
| Giphy | `key` | https://developers.giphy.com/dashboard/ — API key (beta, 100 req/h) |
| Europeana | `key` | https://pro.europeana.eu/pages/developer-apis — API key (https://apis.europeana.eu/) |
| Rijksmuseum | `key` | https://data.rijksmuseum.nl/ — solicitar API key |
| WikiArt | `key` | https://www.wikiart.org/en/developers — acceso a la API por invitación (400 req/h) |
| Pixiv | `refresh_token` | Flujo OAuth PKCE propio contra `oauth.secure.pixiv.net` (referencia no oficial: https://github.com/upbit/pixivpy/wiki/Auth) |
| Wallhaven | `key` (**opcional**) | https://wallhaven.cc/settings/account — API key. Sin ella solo SFW; con ella el toggle de madurez desbloquea sketchy/NSFW |

**Zerochan** no lleva key: usa un User-Agent por usuario (`zerochan_ua`, formato `Megalomaniac-{usuario}`) que se guarda en el bag. **DeviantArt**, además, resuelve su token OAuth2 client-credentials a nivel app.

Cada fila de la tabla de Ajustes tiene un botón **Probar** que hace una conexión real con esas credenciales y reporta OK/error inline.

## Tier 3 — opt-in con aviso

`config('inspiration.tier3')`: `pixiv`, `pinterest`, `cara`, `bandcamp`, `wikiart`, `newgrounds`, `mobbin`. Están **desactivadas por defecto** y requieren aceptar el aviso por fuente (`acknowledged_tier3`) antes de poder activarlas. Motivos:

- **Pinterest**: no hay API de búsqueda; scraping con anti-bot. Puede romperse.
- **Cara.app**: plataforma joven anti-AI; sin API oficial; scrape respetuoso de baja frecuencia.
- **Mobbin**: solo API no oficial con sesión de cuenta; frágil (la API oficial es de planes Team/Enterprise).
- **Newgrounds**: portal HTML sin API de búsqueda estable.
- **Bandcamp**: API no documentada (`/api/discover/…`) para portadas de álbumes.
- **Pixiv**: API App no oficial; requiere `refresh_token` del usuario.
- **WikiArt**: key por solicitud y cuota limitada.

**Degradación**: si un Tier 3 (o cualquier fuente) falla, `SourceManager` sirve la última caché disponible con badge “caché · hace Xh”; si no hay caché, la fuente se marca caída en los chips y el resto del mazo sigue intacto. Nunca se devuelve un 500 al usuario por una fuente. Un scraper que cambió de estructura lanza `SourceException('estructura cambiada')` y entra por el mismo camino.

## Mapeo de madurez por fuente

El toggle global (default **OFF** = SFW estricto) entrega a cada adapter un token abstracto; el adapter lo traduce a su parámetro. Fuentes sin soporte no envían parámetro alguno.

| Fuente | OFF (SFW) | ON |
|---|---|---|
| Wallhaven | `purity=100` (sfw) | `purity=110` (sketchy; `nsfw` solo con key del usuario) |
| DeviantArt | `mature_content=false` | `mature_content=true` |
| Gelbooru | tags con `-rating:explicit` | sin filtro de rating |
| Giphy | `rating=pg` | `rating=r` |
| Pixabay | `safesearch=1` | `safesearch=0` |
| Flickr | `safe_search=1` | `safe_search=3` |
| Pixiv | agrega `filter=for_ios` | omite el filtro (boundary R-18 a nivel cuenta) |
| Openverse, museos, Are.na, Tumblr, Unsplash, Pexels, Discogs, Europeana, Rijksmuseum, Tier 2 | n/a | n/a |

## Flujo QA diario

Servidor `php artisan serve :8010` (o `composer run dev`), usuario `test@example.com / password`, Playwright MCP. Pasos:

1. **Landing** `GET /` → paleta Ember, sin errores de consola.
2. **Login** `/login` con `test@example.com / password` → dashboard.
3. **Inspiración** `/inspiration`: chips con estado, muro masonry, skeleton de búsqueda.
4. **Explorar**: buscar un término, filtrar por una fuente, abrir el **lightbox** (← →, Escape, metadatos, tags).
5. **Guardar en proyecto**: Guardar → elegir moodboard/Inbox → confirmación; reintentar la misma imagen → 409 con link al board existente.
6. **Moodboard**: `/inspiration/moodboards/{id}` → muro, quitar, expandir y **descargar full** (estado Descargando/En cola/Reintentar según respuesta).
7. **Ajustes**: activar una fuente, guardar key + Probar, toggles de madurez y Tier 3.

Registrar el resultado en `docs/qa/playwright-report.md` (solo lo efectivamente corrido). El crawl es *best-effort*: si `:8010` no está disponible en el entorno, se deja anotado como pendiente.

## Convenciones y notas

- **Sin `DB::`**: toda la persistencia pasa por Eloquent (`InspirationCacheEntry`, `InspirationSetting`, `Moodboard`, `SavedImage`), con `casts()` donde aplica.
- **Contrato `Source`**: agregar una fuente = crear el adapter + registrarlo en `InspirationServiceProvider::SOURCES` (+ `credential_fields`/`tier3` si corresponde). No hay que tocar `SourceManager`.
- **Validación en Form Requests** (`app/Http/Requests/Inspiration/*`), no en controllers.
- **Autorización por owner-scope**: moodboards/saved images ajenos devuelven 404 indistinguible.
- **Wayfinder** para links/rutas (`@/routes/inspiration`), tokens Ember (`bg-background`, `card`, `border`, `primary`, `muted-fg`), `animate-in` existentes.
- **Storage**: disco `inspiration.disk` (default `local`), subpath `inspiration/{user_id}/{source}/`; tope diario de descargas full `INSPIRATION_MAX_DOWNLOADS_PER_DAY` (default 20).
- **Tests**: `tests/Feature/Inspiration/*` (Explore, Moodboard, SaveFlow, Settings, Sources, Maturity, Cache, SourceManager, Models, ScraperInfra) con fixtures en `tests/Fixtures/Inspiration/`.

## Deploy

```bash
composer require symfony/dom-crawler symfony/css-selector
php artisan wayfinder:generate
npm run build
```
