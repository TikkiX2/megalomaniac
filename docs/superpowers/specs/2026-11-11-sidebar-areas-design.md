# Sidebar por áreas — diseño

**Fecha:** 2026-11-11
**Estado:** aprobado por el usuario

## Intención

La sidebar general mostraba los ~40 enlaces de todos los módulos en 8 grupos. El usuario navega sabiendo a qué área va y usa a diario **Gym**, **Chat IA** y **Proyectos personales**. La sidebar pasa de ser un índice de servicios a un selector de áreas: 9 filas visibles y, al entrar en un área, sus páginas en una subnavegación contextual.

## Objetivos

- Reducir el primer nivel de ~40 enlaces a 9 áreas.
- Dejar Gym, IA y Proyectos a un clic.
- Mantener cualquier destino existente a dos clics.
- Conservar la estética Ember y el comportamiento móvil.

## No objetivos

- No se cambian rutas, controladores ni páginas.
- No se elimina ningún destino.
- No se añaden dependencias.

## Mapa de áreas

| Área | Primer nivel (destino al hacer clic) | Subnavegación |
|---|---|---|
| Hoy | `/dashboard` | Hoy, Semana, Cola, Archivadas |
| Gym | `/fitness/gym` | Entrenamiento, Rutinas, Historial, Nutrición, Suplementos, Compras |
| Proyectos | `/personal/projects` | Proyectos, Tareas |
| IA | `/ai/chat` | Chat · Asistentes (Gym, Nutrición, Compras, Finanzas, Freelance, Salud, Personas) · Sistema (Fuentes, Memoria) |
| Finanzas | `/finance/dashboard` | Panel, Compras, Ingresos |
| Freelance | `/freelance/dashboard` | Panel, Clientes, Proyectos, Cotizaciones |
| Salud | `/health` | Panel, Condiciones, Medicación, Mediciones, Síntomas, Estudios, Citas, Profesionales, Chats |
| Personas | `/people` | Personas, Historial, Calendario |
| Más | `/inspiration` | Inspiración (Explorar, Ajustes de fuentes) · Sistema (Agentes, Feed, Archivos, Aprobaciones) |

## Comportamiento

- **Escritorio ≥lg:** rail de áreas (13.5rem) + columna de subnavegación del área activa (12rem). El área activa se deduce de la URL.
- **<lg:** los subitems del área activa se muestran inline bajo su fila (sheet móvil o rail de tablet).
- **Colapsado:** el rail queda en iconos y la columna contextual se oculta.
- **Móvil:** la barra inferior mantiene 4 destinos diarios (Hoy, Gym, Proyectos, IA) y un “Más” desplazable con el resto.
- **Aprobaciones:** conserva el badge con el contador pendiente.

## Decisiones

- Se elimina el pin personalizado y el hover automático: chocan con el layout de dos columnas. Queda el trigger estándar de colapsar/expandir.
- “Más” tiene `/inspiration` como destino de aterrizaje para que el clic siempre tenga una página coherente.
- El ítem activo se resuelve por la coincidencia de URL más específica, para que `/health/conditions` no active “Panel” ni `/inspiration/settings` active “Explorar”.
- Las rutas de ajustes (`/settings/*`) no pertenecen a ninguna área: no se muestra columna contextual ahí.

## Criterios de aceptación

- Ningún enlace del menú anterior desaparece.
- Las rutas con detalle (`/finance/purchases/12/edit`, `/people/5`, `/ai/chat/9`) resaltan el ítem y área correctos.
- `npm run types`, `eslint` sobre los archivos tocados y `npm run build` pasan.
- La app sigue funcionando en escritorio, tablet y móvil.
