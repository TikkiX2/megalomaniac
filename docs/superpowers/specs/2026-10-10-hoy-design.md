# Hoy — sistema de ejecución diaria — Design Spec

**Fecha:** 2026-10-10
**Aprobado por usuario:** sí (reemplaza parte de dashboard, elimina insights automáticos, alcance 1-6 completo)

## 1. Objetivo
Ejecutar 3 cosas/día sin elegir en el momento y sin culpa. La app reduce decisión, no mide productividad.

## 2. Invariantes (R1-R8)
- R1 máx 3 ítems/día, validado en Request + modelo + UNIQUE(dia_id,posicion).
- R2 se elige noche anterior 21:00; vacío permitido sin fricción.
- R3 ancla enum cerrado: `al levantarme, después de comer, después del gimnasio, después de bañarme, antes de dormir, sin ancla`.
- R4 estados `pendiente|hecho|soltado`; soltado libera slot e invisible.
- R5 cero agregados en Hoy (sin "2 de 3", %, promedios). Excepción: "X de 3 elegidas" en Mañana como restricción de carga.
- R6 Hoy sin scroll 1280×720 y 375px.
- R7/R8 Hoy nunca muestra backlog/cantidad ni proyectos/objetivos largo plazo.

## 3. Datos
- `dias(id,user_id,fecha,created_at)` UNIQUE(user_id,fecha).
- `dia_items(id,dia_id,tarea_id nullable FK project_tasks,titulo,ancla enum,posicion 1..3,estado enum,nota_cierre nullable,hecho_at nullable)` UNIQUE(dia_id,posicion).
- `project_tasks += en_semana bool default false, archivada_at nullable` (se conserva `is_archived` legacy sin usar para Hoy).
- `bloques(id,user_id,etiqueta,dia_semana 0-6,hora_inicio,duracion_min,activo)` — contexto solo lectura.
- `cola_media(id,user_id,titulo,tipo enum serie|pelicula|libro|juego,posicion int)` — cursor, sin historial.
- Rutina gym: lectura `Routine where scheduled_date = now()->format('l')` (patrón `ExerciseController:96`).

## 4. Pantallas
- `Hoy` (reemplaza parte de `/dashboard`): fecha, 3 líneas checkbox+título+ancla, nota_cierre inline placeholder "¿cómo te sentiste?", contexto gris (bloque + rutina), `Hoy toca: <pos1>` + Siguiente. Vacío: "Hoy no hay nada elegido." + Elegir. Sin rojo/triste.
- Se elimina de dashboard: fetches auto `/ai/insights/workout` + `/ai/suggestions`, `AiInsightCard/SuggestionList`, volumen semanal, racha (violan §4).
- `Mañana`: pool `en_semana`, "Elegí hasta 3", crea/edita dia de mañana, "Dejarlo vacío", B2 "quedó pendiente" con Ponerlo hoy/Soltarlo.
- `Semana`: pool 5-7, buscador backlog bajo demanda, aviso a los 10.
- `Cola`: lista ordenable, Siguiente = delete pos1.
- `Archivadas`: `archivada_at` no nulo, restaurar individual/bloque.
- Archivo masivo: filtros + preview (total + 15 títulos) + `Archivar N` soft + Deshacer sesión.

## 5. Comportamientos
- B1 ritual 21:00: comando `hoy:avisar` (una sola, neutra). Sin push fuera de esta.
- B2 recuperación en Mañana.
- B3 archivo masivo con preview obligatorio, reversible, nunca borrado duro.
- B4 cierre 00:00: comando `hoy:cerrar` no muta pendientes, no notifica.

## 6. Lista negra §4
Sin rachas/puntos/%/tendencias/recompensas/notifs culpa/historial media/metas ocio/backlog en Hoy/conteos gym/analíticas/push extra. Verificación por grep + tests.

## 7. Rutas
- `GET /dashboard` → Hoy (reemplazado).
- `GET /hoy/manana`, `POST /hoy/manana`, `PATCH /hoy/items/{item}`, `POST /hoy/items/{item}/soltar`.
- `GET /hoy/semana`, `POST /hoy/semana`, `DELETE /hoy/semana/{task}`.
- `GET /hoy/cola`, `POST /hoy/cola`, `PATCH /hoy/cola/reordenar`, `POST /hoy/cola/siguiente`, `DELETE /hoy/cola/{item}`.
- `GET /hoy/archivadas`, `POST /hoy/archivadas/restaurar`.
- `GET /hoy/archivo-masivo`, `POST /hoy/archivo-masivo/preview`, `POST /hoy/archivo-masivo/ejecutar`.
- Sidebar: `Hoy` primero en Principal + `Semana/Cola` en Personal.

## 8. Criterios aceptación §6
11 checks testeables (ver plan).
