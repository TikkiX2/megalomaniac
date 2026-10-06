# Inspiración — Auth, Perf, Keys UX y F4 — Implementation Plan

> **Agente inline:** este plan lo ejecuta el propio agente en sesión (método inline), commiteando en `main` por fase, con tests por fase y TDD donde el cambio lo amerite.

**Goal:** resolver lentitud de la página de inspiración, hacer accesibles las API keys/credenciales, sanear fuentes muertas y sumar 6 fuentes nuevas + autenticación por fuente (cookies principal, login simulado alternativo).

**Architecture:** cache stale-while-revalidate + subset curado `home_sources` en el primer render + lazy por chip; subsistema `App\Inspiration\Auth` con cookies/sesión cifradas en `inspiration_settings.body.auth` inyectadas como `session_cookie` (contrato `Source` intacto); adapters nuevos siguiendo `AbstractScrapeSource`/`AbstractApiSource`/embedded-JSON.

**Spec:** `docs/superpowers/specs/2026-10-06-inspiration-auth-performance-design.md`

## Global Constraints (verbatim de la spec)

- `home_sources` = aic, arena, openverse, wallhaven, zerochan, archdaily, cosmos, awwwards. `default_enabled_sources` pierde artstation, behance, met, bandcamp, lapaninja, newgrounds, savee (sin tocar tier3).
- Stale cache: read devuelve payload expirado (tope 12 h) con `stale`; refresh en cola desduplicado; sin caché → fetch inline (degradación actual intacta).
- Auth: `body.auth.<source> = {type: 'cookie'|'login', data: string cifrada Crypt, updated_at, invalid?}`; props solo `has_auth|auth_type|auth_invalid`; header `Cookie:` en las 3 bases cuando hay `credentials['session_cookie']`; re-login máx 3/h; login requiere ack.
- `auth_sources` config = behance, newgrounds, pinterest, cara, mobbin, artstation.
- Gelbooru: needsKey + campo `key` + `api_key` en request.
- Endpoints: `POST|DELETE /inspiration/sources/{source}/auth` (nombres `inspiration.sources.auth.store/destroy`).
- Sin login simulado salvo ack explícito; contraseñas/cookies nunca en logs ni props.
- Pint, sin DB:: fuera de migraciones (no hay migraciones nuevas), suite completa verde, build.

## Fases

- [ ] **F2 — Keys UX** (rápido, primero): sidebar "Ajustes de fuentes"; botón header del muro siempre; chips "falta key"/"+"; Gelbooru needsKey+key; input `zerochan_ua`; aviso DeviantArt. Tests: SettingsTest (gelbooru fields), ExploreTest (link/props) si aplica. Commit.
- [ ] **F1 — Perf**: `InspirationCache::read()` + `SourceManager` (stale+refresh job) + `RefreshSourceJob` + `home_sources` en mashup + higiene defaults (+SettingsTest) + badge "caché hace Xh" en grupo cuando `from_cache`. Tests: CacheTest (stale), SourceManagerTest (refresh job dispatch único), ExploreTest (home_sources). Commit.
- [ ] **F3 — Salud**: selectores Brutalist en vivo + fixture real + tests; docblock dormidas (met, bandcamp, lapaninja, artstation, newgrounds, savee — bandcamp/met además out de defaults ya en F1). Commit.
- [ ] **F4a — Auth backend**: `InspirationAuthStore` + config `auth_sources` + controller (store/destroy) + Form Request (cookie vs login + ack) + hydratación `session_cookie` en SourceManager + header Cookie en las 3 bases + `markInvalid` + re-login límites. Tests: cifrado reposo, no-leak, connect/login/disconnect, 401→invalid, header presente. Commit.
- [ ] **F4b — Auth UI**: modal "Conectar cuenta" (tabs cookie/login + ack) + badges en settings; build+lint. Commit.
- [ ] **F4c — Fuentes 1x / eMuseum / It's Nice That / 500px / ArtStation / Behance-rework**: live-check cada una → adapters + fixtures + datasets + registro + labels + docs. Commits por fuente o agrupadas. Commit.
- [ ] **F5 — Cierre**: docs `inspiration.md` (auth + estado fuentes + tabla salud), suite completa, pint, build, commit, push, deploy con backup y verificación (curl landing/login + migraciones + contenedores).