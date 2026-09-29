# Herramientas del chat IA (grupos, tools y servicios)

> Mapa del grupo del picker → tools → servicio compartido. El chat y el servidor MCP escriben por los **mismos servicios**, así que las reglas viven en un solo lugar.

## Grupos y tools

| Grupo (`ai_tools.php`) | Query | Action | Servicio |
|---|---|---|---|
| `tasks` | `TaskQueryTool` | `TaskActionTool`, `ProjectActionTool` | `Services\Tasks\TaskService`, `Services\Projects\ProjectService` |
| `workout` | `GymQueryTool` | `GymActionTool` | `Services\Gym\WorkoutSessionService`, `RoutineService` |
| `finance` | `FinanceQueryTool` | `FinanceActionTool` | `Services\Finance\FinanceService` |
| `nutrition` | `NutritionQueryTool` | `NutritionActionTool` | `Services\Nutrition\NutritionService` |
| `grocery` | `GroceryQueryTool` | `GroceryActionTool` | `Services\Grocery\GroceryService` |
| `supplements` | `SupplementQueryTool` | `SupplementActionTool` | `Services\Supplement\SupplementService` |
| `freelance` | `FreelanceQueryTool` | `FreelanceActionTool` | `Services\Freelance\FreelanceService` |
| `people` | `PeopleQueryTool` | `PeopleActionTool` | `Services\People\PeopleService` |
| `actions` | — | alias de **todas** las action tools | — |
| `integrations`, `agents`, `skills`, `memory`, `web` | — | — | integraciones/agentes/skills/memoria/web |

`ToolCatalog::toolsFor()` dedupea por clase, así que elegir un módulo y `actions` a la vez no repite tools.

## Selección automática (ToolRouter)

- La política del hilo puede ser `auto` (router por palabras) o `manual` (picker). Elegir "Auto" en el picker envía `tools_policy={mode:'auto',groups:[]}` y vuelve a habilitar el router.
- `config/ai_tools.php`: keywords por grupo + `write_verbs` (ES/EN) que agregan `actions`; fallback = grupos de datos en lectura.
- El prompt del agente (`MegalomaniacAgent::writeInstructions()`) describe **solo** las tools publicitadas en el turno: nunca menciona una tool ausente, así el modelo no responde "no puedo".

## Escrituras y aprobaciones

- Toda action tool implementa `Approvable`: el turno se pausa y la tarjeta de aprobación muestra la etiqueta de la acción (editable antes de aprobar).
- Errores de dominio (`InvalidArgumentException`, `ModelNotFoundException`, `AuthorizationException`) se devuelven como `{success:false,error}` legible; nunca tiran el stream.
- Ownership: cada servicio valida `user_id`; los `exists` de MCP usan búsquedas con scope.

## Módulos de proyecto (Personal/Freelance)

- `ProjectActionTool::create_project` **exige `type`**; si el usuario no dijo el módulo, el modelo debe preguntar. `update_project` con `type` mueve el proyecto y re-mapea el tablero (`ProjectTypeService`).
- Los grupos están aislados por `type`: Freelance lista `type=freelance`, Personal `type=personal` + tareas sueltas.

## Reglas de consistencia

- Macros de nutrición: snapshot = valor por porción × `quantity` (paridad web).
- Fechas: ventanas con `toDateString()`; las deudas no se ocultan por antigüedad.
- Tareas: `status` siempre validado contra columnas del tablero y `is_done` sincronizado; al mover entre proyectos de distinto tipo la columna se re-mapea a la primera del destino.
- Stock: consumos y reposiciones con lock; reposición registra historial de precios.
