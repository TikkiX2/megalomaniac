# Inspiración — Rendimiento, Keys UX, Salud de fuentes y F4 con Auth — Design Spec

**Fecha:** 2026-10-06
**Estado:** Aprobado en conversación (diseño por secciones; ejecución inline)
**Alcance:** (F1) la página de exploración tarda demasiado; (F2) las API keys/credenciales no se pueden agregar; (F3) servicios que no traen contenido; (F4) 6 fuentes nuevas + subsistema de autenticación por fuente (cookies como mecanismo principal, login simulado como alternativa).

## Problema (todas las causas verificadas en vivo, prod 2026-10-06)

1. **Lentitud:** `ExploreController::mashup()` y `SourceManager::searchAll()` recorren TODAS las fuentes activas EN SERIE. Con ~20 fuentes default, cada carga en frío bloquea 30–120 s (Met hace hasta 21 requests; scrapers 1–12 s c/u). Las fuentes muertas se intentan en cada carga y suman timeouts. El primer render no pinta nada hasta terminar todo.
2. **Keys inalcanzables:** el único link a Ajustes vive en el *empty state* del muro; el sidebar solo tiene "Inspiración → explorar". En prod la tabla `inspiration_settings` está vacía. Además **Gelbooru ahora responde 401** (requiere key) y no hay input para ella.
3. **Servicios que no traen (verificado desde el server prod):**

| Fuente | Estado real | Decisión |
|---|---|---|
| Gelbooru | 401 (ahora requiere api_key) | needsKey + campo `key` en Ajustes |
| Brutalist Websites | 200 pero selectores caducos (hoy `.box`/`.screenshot`) | selectores nuevos verificado en vivo + fixture real |
| Met Museum | search **410 Gone**; lista de objetos 200 pero lenta | dormida + out de defaults |
| Bandcamp | discover **404** (`UnknownEndpointError`) | dormida + out de defaults |
| Lapa Ninja | 403 Cloudflare | dormida + out de defaults |
| Behance | 403 bot-wall | rework a JSON embebido + cookie de sesión (F4) |
| Newgrounds | 403 NG Guard | cookie opcional (mismo mecanismo) |
| ArtStation | API retirada 2026 | dormida + out de defaults |
| Savee | SPA + API 401 Bearer | dormida + out de defaults |
| DeviantArt | sin creds app en `.env` prod | aviso en Ajustes; requiere env |
| Pinterest/Cara/Mobbin | tier3 off (bot-walls/cuenta) | cookie opcional donde aplique |
| Dribbble | funciona en prod | nada |

## Decisiones

### F1 — Rendimiento
- **Stale-while-revalidate:** `InspirationCache::read()` devuelve payload aunque esté expirado (tope 12 h) con `stale=true`; `SourceManager::search/explore` sirven caché (fresca o stale) SIEMPRE que exista, y encolan `RefreshSourceJob` en background cuando la caché estaba vencida (desduplicado por clave). Sin caché → fetch en línea como hoy (degradación existente intacta).
- **Subset curado en primer render:** nuevo `config('inspiration.home_sources')` = `[aic, arena, openverse, wallhaven, zerochan, archdaily, cosmos, awwwards]`; el mashup del index usa SOLO las activas dentro del subset. El resto carga lazy por chip (flujo existente `search?source=key`).
- **Higiene de defaults:** `default_enabled_sources` pierde artstation, behance, met, bandcamp, lapaninja, newgrounds, savee (siguen visibles en chips como off/configurables).
- **Badge UI:** el encabezado de grupo muestra "caché · hace Xh" también cuando se sirvió stale (hoy solo en `down`).

### F2 — Keys UX
- Item fijo **"Ajustes de fuentes"** en el sidebar (grupo Inspiración) + botón header visible siempre en el muro.
- `SourceChips`: fuente con `needs_key && !has_key` → "falta key → Ajustes" clickeable; fuente off → link "+ configurar".
- **Gelbooru → needsKey=true** + `credential_fields` `gelbooru => ['key']`; el adapter manda `api_key`; docs (cómo pedir key).
- Input `zerochan_ua` en settings.tsx (backend ya lo mergea).
- Aviso en Ajustes para DeviantArt cuando faltan las creds de app.

### F3 — Salud
- Brutalist: selectores `.box` (card) + estructura interna verificada en vivo; fixture `brutalist/feed.html` real; tests ajustados.
- Met/Bandcamp/Lapa Ninja/Savee/ArtStation/Newgrounds: docblock "dormida" + out de defaults (bandcamp además marca 404; met 410).
- Awwwards: ya funciona; el "archivo por año" queda cubierto por el explore actual (sin adapter extra).

### F4 — Fuentes nuevas + Auth
**Subsistema `App\Inspiration\Auth` (cookies principal, login alternativa):**
- Guardado en `inspiration_settings.body.auth.<source>`: `{type: 'cookie'|'login', data: <string cifrada con Crypt>, updated_at, invalid?}`. Nunca expuesto en props (solo `has_auth`, `auth_type`, `auth_invalid`).
- `InspirationAuthStore`: `saveCookie(User, source, cookie)`, `saveLogin(User, source, email, password)` (ejecuta el flujo de login simulado del sitio y guarda las cookies resultantes), `disconnect`, `cookieFor(User, source): ?string` (descifra), `markInvalid`, `previousLoginCookies`.
- Hydratación: `SourceManager` inyecta `session_cookie` como credential reservada (contrato `Source` sin cambios; `UsesCredentials` ya acepta keys arbitrarias).
- Envío: `AbstractApiSource`, `AbstractScrapeSource`, `AbstractEmbeddedJsonSource` agregan header `Cookie: ...` cuando `credentials['session_cookie']` está presente.
- Vencimiento: 401/403 en una fuente con sesión → `SourceException` + marcado `auth_invalid` en el store → chip "reconectar". Tipo `login`: intento de re-login automático con límite 3/h (contador en cache).
- Endpoints: `POST /inspiration/sources/{source}/auth` (cookie o login + `acknowledged_login` para tipo login), `DELETE .../auth` (desconectar). Validación por fuente (solo fuentes con `supportsAuth` config).
- UI: modal "Conectar cuenta" por fuente en settings.tsx (tab Pegar cookie / tab Login con aviso de riesgo de ban y checkbox de aceptación requerido para login).
- Fuentes con `supportsAuth` (config `inspiration.auth_sources`): behance, newgrounds, pinterest, cara, mobbin, artstation (para portafolios si 403). Sin sesión, el resto del comportamiento de degradación no cambia.

**Fuentes nuevas (todas con fixtures, dataset, registro, labels, docs):**
1. **1x.com** (`1x`) — scrape público; explore feed; search según live check.
2. **eMuseum Zürich** (`emuseum`) — scrape público; colección/pósters; search según live check.
3. **It's Nice That** (`itsnicethat`) — scrape editorial; search según live check.
4. **500px** (`500px`) — live check de la API v1 (`api.500px.com/v1/photos/search?consumer_key=`); si viva → adapter API con key (needsKey); si no → scrape o descarte documentado.
5. **ArtStation portafolios** (`artstation` rework o `artstation-portfolios`) — scrape SSR de perfiles + directorio verificado en vivo; cookie opcional.
6. **Behance embedded-JSON** (`behance` rework) — parse de JSON embebido (con cookie de sesión para el 403); reusa `AbstractEmbeddedJsonSource`-style.

### F5 — Cierre
Tests por pieza (auth: cifrado en reposo, no-leak en props, conectar/desconectar, re-login con límites, cookie header en requests, degradación sin sesión; perf: stale-serving + refresh job + home_sources; salud: fixtures reales), docs, suite completa, deploy con backup y verificación post-deploy (mismo protocolo anterior).

## Fuera de alcance
Concurrencia real (Http::pool) entre fuentes; OAuth real de terceros (500px si lo requiere se evalúa con su estado vivo); pantalla de login de las plataformas embebida (el usuario pega la cookie o usa login simulado en nuestro modal); scrape de Instagram/X.