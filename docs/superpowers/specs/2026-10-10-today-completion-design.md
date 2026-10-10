# Today — completar el sistema de ejecución diaria (renombre EN + fixes + módulo Media)

**Fecha:** 2026-10-10
**Reemplaza:** `2026-10-10-hoy-design.md` (rename de todo lo creado en ese step).
**Aprobado:** sí — A (renombre total incl. URLs y DB), sacar hidratación estática, alcance D completo, módulo Media con búsqueda en DBs externas y pick aleatorio.

## 1. Problemas que resuelve

- `/dashboard` quedó huérfano: `routes/web.php` lo apunta a la página Today y la vieja `fitness/dashboard.tsx` ya no se renderiza. El usuario quería **agregar** una sección Hoy al dashboard, no reemplazarlo.
- El pool de Semana nunca se pudo llenar: `WeekController@search` no filtra `is_done=false` y devuelve tareas terminadas; en producción `in_week=0/237`.
- Todo el módulo quedó en spanglish (`Hoy/Manana/Semana`, `dias/dia_items`, `fecha/ancla`) contra la convención EN del repo.
- Faltan del spec original: Deshacer real del archivo masivo, CRUD de bloques, drag en Cola, filtros `proyecto`/`nunca`, B1 con notificación real.
- Módulo nuevo pedido por el usuario: catálogo Media (películas/series/discos/libros/juegos) con búsqueda en DBs externas sin API key y pick aleatorio diario en Hoy.

## 2. Convenciones (renombre A)

- Todo en inglés: nombres de archivos/clases/tablas/columnas/rutas/props/keys de JSON.
- **Copy visible queda en español** (UI personal): "Hoy no hay nada elegido.", "Elegí hasta 3", "¿cómo te sentiste?", "quedó pendiente", "Siguiente", "Sorprendeme".
- Tipos de media en español como valores (son datos de contenido personal): `pelicula|serie|disco|libro|juego`.
- Anclas DB en inglés con copy español solo en `<option>`: `wake_up|after_meal|after_gym|after_shower|before_sleep|no_anchor`.

## 3. Esquema DB (prod vacía: days=0, day_items=0, queue=0)

- `days`: id, user_id, **date**, pick_type (enum tipo media, default `pelicula`), created_at.
- `day_items`: id, day_id, **task_id**, **title**, **anchor**, **position**, **state**, **closing_note**, **done_at**. Índice único parcial `(day_id, position) WHERE state != 'released'`.
- `blocks`: id, user_id, **label**, **weekday**, **start_time**, **duration_min**, **active**.
- `queue_items` (ex `cola_media`): id, user_id, **title**, **type**, position, **source**, **external_id**, **cover_url**, **year**, **creator**. Sin historial.
- `project_tasks`: `in_week` (ex `en_semana`), `archived_at` (ex `archivada_at`).
- `notifications`: tabla estándar de Laravel (para B1).

## 4. Renombre de archivos

Sync (borra y crea):
- `app/Models/{Dia,DiaItem,Bloque,ColaMediaItem}.php` → `{Day,DayItem,Block,QueueItem}.php`
- `app/Http/Controllers/Hoy/*` → `app/Http/Controllers/Today/{TodayController,TomorrowController,WeekController,QueueController,ArchiveController,ArchiveBulkController}.php`
- `app/Http/Requests/Hoy/*` → `app/Http/Requests/Today/{StoreTomorrowRequest,UpdateDayItemRequest,MoveDayItemRequest}.php`
- `app/Console/Commands/Hoy{Avisar,Cerrar}.php` → `Today{Notify,Close}.php`
- `resources/js/pages/hoy/*` → `resources/js/pages/today/{Index,Tomorrow,Week,Queue,Archived,BulkArchive}.tsx`
- `tests/Feature/Hoy/*` → `tests/Feature/Today/*`
Borra (reemplazadas): página `hoy/Index.tsx` (su contenido pasa a `TodayPanel`), `ArchivoController` dividido.

## 5. Módulo Media

### 5.1 Búsqueda externa sin API key (verificado 200 OK)

| tipo | provider | endpoint |
|---|---|---|
| pelicula | Wikidata | `wbsearchentities(type=item)` + `wbgetentities` P31=Q11424 |
| serie | Wikidata | P31=Q5398426 |
| libro | Open Library | `search.json?q=` |
| disco | MusicBrainz | `/ws/2/release-group?query=` (User-Agent obligatorio) |
| juego | Wikidata | P31=Q7889 |

- `MediaSearchService` en `app/Services/Media/` con providers cacheables 24h (`Cache::remember`, key `media:{type}:{md5(q)}`).
- DTO `MediaSearchResult`: `title, creator, year, cover_url, source, external_id`.
- Alta manual permitida (spec): POST sin `external_id`.
- Dedupe por `(user_id, source, external_id)` si existe.

### 5.2 Pick en Hoy

- `days.pick_type` seleccionable desde la línea de media del Dashboard.
- "Hoy toca" muestra pos 1 (cursor). Botón **Sorprendeme**: elige aleatoria determinística `sha1(user_id|date|pick_type|item_ids)` del subset del tipo → estable todo el día (no slot-machine). "Siguiente" = consume rota el cursor (delete pos1, ya existente).
- 🔒 El random vive **solo** acá, sin rachas, sin historial, sin notificarlo.

## 6. Comportamientos (§5 original, completados)

- B1: comando `today:notify` 21:00 + notificación database neutra, una sola, sin segundo aviso.
- B2: en `Tomorrow`, pendientes de ayer con `[Ponerlo hoy] [Soltarlo]` ("quedó pendiente").
- B3: bulk archive con preview + **Deshacer real** (ids en sesión) + filtros `nunca|mas_n_dias|proyecto|todas`.
- B4: `today:close` no muta ni notifica.

## 7. Fuera de alcance

- No se toca gimnasio/finanzas/salud/nutrición/contactos.
- Sin integraciones con Radarr/Jellyseerr existentes (keys propias, no hacen falta).
- Sin push notifications (solo database).
- Sin features anti-spec: rachas, puntos, %, historial de media, notifs de culpa.
