# Módulo Freelance

Models: Client, Project, ProjectTask, ProjectComment, ProjectPayment, Quote, QuoteItem (+ MediaLibrary + Notion)

Rutas: `freelance/dashboard`, `clients`, `projects/{payments,media}`, `projects/{comments}`, `quotes/{pdf,duplicate,convert}`, `projects.tasks`, `tasks/sync-to-notion`, `notion/webhook`

Pages: `freelance/Dashboard.tsx`, `freelance/projects/{Index,Form,Show}`, `clients/*`, `quotes/*`

Components: YooptaEditor, TaskBoard, TaskDetailDialog, ColumnManager, MediaGallery, CommentSection

Dashboard: stats active_projects, pending_quotes, monthly_income, pending_tasks + recent_projects + upcoming_tasks

QA diario: crear client/project/quote, cambiar status, add task/comment/media, pdf/duplicate/convert, kanban.

Features: Kanban drag real, columnas configurables por proyecto, popup de detalle con edición completa, descripción por IA, Time tracker, Quote→Invoice pulido, Notion sync mock.

Columnas Kanban: tabla `task_board_columns` (por proyecto + set default del usuario), `project_tasks.status` guarda la key de la columna e `is_done` desnormalizado alimenta progreso/dashboards. CRUD en `task-board-columns` (crear, renombrar, color, `is_done`, reordenar, eliminar con columna destino).
