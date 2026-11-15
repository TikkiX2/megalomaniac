# Diario — memorias personales (diseño)

**Fecha:** 2026-11-15
**Estado:** aprobado por el usuario ("Apruebo")

## Intención

Un lugar donde escribir lo que quiere recordar, con la mínima fricción posible, y volver a encontrarlo. El día es el ancla y los momentos son las piezas sueltas: se puede escribir la entrada del día sin agregar momentos, o tres momentos sin llenar la entrada.

No es un feed ni una red: es un archivo personal y privado, con fechas.

## Objetivos

- Capturar una memoria en menos de 10 s desde `/diario`.
- Que el calendario muestre los huecos reales (días sin nada escrito).
- Que "privado" signifique cifrado en reposo, no sólo un ícono.
- Que la IA pueda resumir únicamente lo público.
- Reutilizar los patrones existentes de la app (Ember, Inertia, Wayfinder, Pest, media library).

## No objetivos

- Redes sociales, compartir, comentarios, multiusuario.
- Edición colaborativa o tiempo real.
- Notificaciones push o recordatorios por email.
- Un editor WYSIWYG pesado.
- Reemplazar `/ai/memory`, que sigue siendo la memoria del agente.

## Anti-objetivos

- Un feed infinito sin fechas ni jerarquía.
- Gamificación con puntos o badges.
- Métricas decorativas o gráficos sin decisión detrás.
- Duplicar la grilla de cards de los módulos de gestión.

## Restricciones

- Laravel 12, Inertia v2, React 19, TypeScript, Tailwind v4, Wayfinder, Pest 4.
- Sin dependencias PHP ni JS nuevas (`spatie/laravel-medialibrary` ya está instalado).
- Postgres; tokens Ember de `docs/design-tokens.md`; UI en castellano.
- Timestamps en UTC; fechas de diario en la zona del usuario (`User::localTimezone()`).
- `config('app.timezone')` sigue en UTC.
- Cada cambio lleva test; `vendor/bin/pint --dirty --format agent` si se toca PHP.

## Modelo de datos (enfoque A: ancla + momentos)

### `journal_days`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users, cascade | |
| `date` | date | fecha local del usuario; `unique(user_id, date)` |
| `body` | text nullable | cifrado si `is_private` |
| `mood` | smallint nullable | 1..5 (Bajo, Flojo, Normal, Bien, Muy bien) |
| `tags` | jsonb nullable | array de strings normalizados |
| `is_private` | boolean, default false | |
| timestamps | | índice `(user_id, date desc)` |

### `journal_moments`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users, cascade | |
| `journal_day_id` | FK journal_days, cascade | |
| `occurred_at` | timestamp | UTC; se muestra en zona local |
| `title` | text nullable | cifrado si `is_private` (mismo cast que `body`) |
| `body` | text | cifrado si `is_private` |
| `tags` | jsonb nullable | |
| `is_private` | boolean, default false | |
| timestamps | | índice `(journal_day_id, occurred_at)` |

### `journal_moment_person`

`moment_id` + `person_id`, PK compuesta, cascades. La persona se valida por `user_id`; un momento nunca enlaza personas de otro usuario.

### Fotos

Se usa `spatie/laravel-medialibrary` (patrón de `HealthStudy`): **`JournalMoment`** implementa `HasMedia` con la colección `photos`. `JournalDay` **no** guarda fotos en Fase 1: una foto siempre vive dentro de un momento, y el día muestra las de sus momentos. No se crea tabla propia: la tabla `media` ya resuelve tipo, tamaño, nombre y borrado.

- Disco `local` (`storage/app/private`), nunca URL pública.
- Servido por ruta autenticada con chequeo de dueño y `response()->streamDownload` / stream del archivo.
- Sin conversiones ni thumbnails generados: la miniatura es CSS (`object-cover`).

### Personas y etiquetas

- Personas: pivote `journal_moment_person`. Los recuerdos de una persona se leen desde su ficha.
- Etiquetas: JSON en día y momento (mismo criterio que `SavedImage.tags`). Normalización: `trim` + minúsculas + dedupe, máximo 8 por memoria.

### Reglas de comportamiento

- **Privacidad independiente:** `is_private` del día y del momento son independientes. Marcar privado un día no toca sus momentos; cada memoria tiene su candado.
- **Contenido de un día:** un día cuenta si tiene `body`, y/o `mood`, y/o al menos un momento. Etiquetas solas no cuentan.
- **Día vacío:** si al guardar el día queda sin `body`, sin `mood`, sin etiquetas y sin momentos, el servicio borra la fila `journal_days`. No hay anclas fantasma.
- **Racha:** días consecutivos con contenido, en la zona del usuario, terminando hoy o ayer. Ayer con contenido y hoy vacío todavía mantiene la racha.
- **Calendario:** punto en todo día con contenido. Si no hay ninguna memoria pública ese día, el punto es un candado y no se muestra texto ni extracto.
- **Momento nuevo:** `occurred_at = now()` si el día es hoy; si se escribe en otro día, las 12:00 locales de esa fecha.
- **Mover un momento:** si un `PATCH` cambia `occurred_at` a otra fecha local, el momento se re-ancla a ese día (se crea el día si no existe) y se borra el día anterior si queda vacío.
- **Privado en Fase 1:** el dueño ve el texto (la sesión ya lo identifica). El cifrado es en reposo y la exclusión es de búsqueda e IA. El candado con re-autenticación llega en Fase 2.

## Cifrado

Cast `App\Casts\JournalBody`:

- **Leer:** si el valor empieza con `enc:v1:`, descifra el resto con `Crypt::decryptString`; si no, devuelve el valor tal cual (filas públicas y datos previos siguen funcionando).
- **Escribir:** si el modelo tiene `is_private = true`, guarda `enc:v1:` + `Crypt::encryptString`; si no, texto plano. Aplica a `body` en ambos modelos y a `title` en el momento.
- Cambiar de privado a público reescribe el atributo en el guardado: el hook `saving()` del modelo detecta `isDirty('is_private')` y reasigna `body`/`title`, de modo que el cast cifra o descifra sin depender de un servicio aparte.
- **Búsqueda:** `where('is_private', false)` explícito además de `ILIKE`, para no depender de que el ciphertext no matchee.
- **IA:** sólo memorias públicas; las privadas se excluyen en la consulta, no en el prompt.

### Límite honesto de "privado"

Protege **el texto**. Fecha, ánimo, etiquetas, personas y los archivos de foto quedan legibles para quien tenga la base o el disco. Un `APP_KEY` comprometido descifra todo: esto es cifrado en reposo, no zero-knowledge. Si se quiere más, es otro proyecto (cifrado de imágenes + metadata y/o passphrase).

## Navegación

Área nueva `diario` en `resources/js/lib/navigation-areas.ts`, icono `NotebookPen`, después de Proyectos:

- key `diario`, href `/diario`, match `['/diario']`.
- Subitems: Hoy (`/diario`), Calendario (`/diario/calendario`), Todas (`/diario/todas`), Repaso (`/diario/repaso`).
- La barra móvil de 4 + Más no cambia: Diario queda a dos toques. Se revisa después de usarlo.

## Rutas

`routes/journal.php`, `Route::middleware(['auth', 'verified'])->prefix('diario')->name('journal.')->group(...)`:

| Método | URI | Acción | Nombre |
|---|---|---|---|
| GET | `/diario` | `DiaryController@index` (hoy) | `journal.index` |
| GET | `/diario/{date}` | `DiaryController@show` (`Y-m-d`) | `journal.show` |
| PUT | `/diario/{date}` | `DiaryController@update` (upsert del día) | `journal.day.update` |
| POST | `/diario/{date}/momentos` | `MomentController@store` | `journal.moments.store` |
| PATCH | `/diario/momentos/{moment}` | `MomentController@update` | `journal.moments.update` |
| DELETE | `/diario/momentos/{moment}` | `MomentController@destroy` | `journal.moments.destroy` |
| POST | `/diario/momentos/{moment}/fotos` | `PhotoController@store` | `journal.photos.store` |
| GET | `/diario/momentos/{moment}/fotos/{media}` | `PhotoController@show` | `journal.photos.show` |
| DELETE | `/diario/momentos/{moment}/fotos/{media}` | `PhotoController@destroy` | `journal.photos.destroy` |
| GET | `/diario/calendario` | `CalendarController@index` | `journal.calendar` |
| GET | `/diario/todas` | `TimelineController@index` | `journal.timeline` |
| GET | `/diario/buscar` | `SearchController@index` | `journal.search` |
| GET | `/diario/repaso` | `ReviewController@index` | `journal.review` |

Las rutas estáticas (`calendario`, `todas`, `buscar`, `repaso`, `momentos/*`) se registran **antes** de `/diario/{date}`; `{date}` se restringe con `where('date', '\d{4}-\d{2}-\d{2}')`.

## UI (contrato visual)

**Dirección: el día es una página, no un tablero.**

- **Escritorio:** columna de lectura `max-w-3xl` + riel derecho de 18rem. **Móvil:** una columna; el riel pasa a tira de calendario bajo la fecha. `MainLayout` se aplica solo, por `resolvePageLayout`.
- **Orden de lectura:** fecha con `‹ Hoy ›` → portada del día (ánimo + texto) → espina vertical con momentos por hora → riel (calendario del mes, racha, "tal día como hoy").
- **Tipografía:** cromo con `text-[10px] font-black uppercase tracking-widest text-muted-foreground`; cuerpo escrito `text-[15px] leading-relaxed text-white/90`. Sin familia nueva: la jerarquía viene de tamaño y aire.
- **Ánimo:** 5 botones con etiqueta (Bajo, Flojo, Normal, Bien, Muy bien); el activo lleva anillo `primary`. Sin arcoíris ni emojis.
- **Momento:** hora en el margen de la espina, título en negrita, cuerpo, fotos 4-up con dialog, chips `@persona`, etiquetas, candado si privado.
- **Composer:** inline arriba de la espina ("¿Qué querés recordar?"), `Ctrl+Enter` guarda, `Esc` cancela. Editar existente en Dialog, como Mediciones.
- **Estados obligatorios:** día vacío con pregunta disparadora + CTA; primer uso con intro; error de guardado inline que conserva el borrador; esqueleto al cambiar de día; candado con "Desbloquear" en Fase 2; calendario con día activo y puntos de contenido; vacío de búsqueda y de repaso.
- **Accesibilidad:** foco visible `ring-primary`, labels en botones de icono, contraste AA de los tokens, targets ≥ 44px en móvil.
- **Movimiento:** sólo el `fade-in` de ruta que ya existe; nada de springs ni parallax.

## Fase 1 — escribir y encontrar

Migramientos, modelos, cast, factories y policies; día + momentos; ánimo, etiquetas, fotos y personas; calendario; "Todas"; búsqueda; **Repaso** (`/diario/repaso`): memorias del mismo mes-día de años anteriores y una memoria vieja al azar, sólo públicas, con el riel mostrando una versión compacta de lo mismo; racha; navegación; toggle de privado (sin desbloqueo).

## Fase 2 — privacidad e IA

Re-autenticación (`password.confirm`) para ver memorias privadas en la página del día, el timeline, la búsqueda y el repaso; luego de confirmar, la sesión recuerda el desbloqueo por 15 minutos. Estados de candado en todos esos lugares, incluidas las fotos de un momento privado.

Reflexión semanal a pedido desde Repaso (botón "Resumen de la semana") y tool `journal.recap` para el chat del agente. Se guarda en `journal_reflections`: `user_id`, `week_start` (date, lunes), `body`, `model`, `generated_at`, `unique(user_id, week_start)`; regenerar sobrescribe la fila. Las memorias privadas nunca entran al prompt. La Fase 2 lleva su propio plan.

## Criterios de aceptación

- Escribir hoy sin recargar la página; cambiar de día y volver conserva todo.
- El calendario marca los días con contenido y distingue sólo-privado sin mostrar texto.
- Un dump de la base no muestra el texto privado.
- La búsqueda no devuelve memorias privadas.
- Un usuario no puede leer, editar ni borrar memorias de otro (404 en todos los casos).
- `npm run types`, eslint sobre lo tocado, `npm run build` y Pest en verde.
- Sin clipping, solapamiento ni controles inertes en 1280 y 375.

## Decisiones

- Enfoque A (ancla + momentos) sobre una tabla con `kind` o sólo momentos.
- Cifrado en reposo con `APP_KEY` sobre passphrase zero-knowledge: no se puede perder una memoria por olvidar una clave.
- Privado = texto cifrado; metadata y fotos quedan en claro (documentado arriba).
- Fotos con media library, no tabla propia: es el patrón ya usado por Salud.
- Etiquetas en JSON, como Inspiración.
- Personas sólo en momentos; las del día se derivan de sus momentos.
- Diario fuera de la barra móvil de 4.
- `title` del momento se cifra junto con el `body` cuando es privado (columna `text`).
- Fotos sólo en momentos; el día no tiene colección propia en Fase 1.
- Privacidad de día y momento independiente, sin cascada.
- Repaso es "tal día como hoy" + memoria al azar; la reflexión semanal llega en Fase 2.

## Riesgos

- Dos superficies de escritura (entrada del día y momentos) pueden confundir; la UI debe dejar claro cuál es cuál.
- El cifrado por fila complica cualquier búsqueda futura; si se necesita full-text, será sobre un índice de memorias públicas.
- Fotos sin conversión pueden pesar; se valida tamaño máximo por archivo (10 MB) y cantidad por memoria (8).
