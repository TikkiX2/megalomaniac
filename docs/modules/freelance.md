# Módulo Freelance

Models: Client, Project, ProjectTask, ProjectComment, ProjectPayment, Quote, QuoteItem (+ MediaLibrary + Notion)

Rutas: `freelance/dashboard`, `clients`, `projects/{payments,media}`, `projects/{comments}`, `quotes/{pdf,duplicate,convert}`, `projects.tasks`, `tasks/sync-to-notion`, `notion/webhook`

Pages: `freelance/Dashboard.tsx`, `freelance/projects/{Index,Form,Show}`, `clients/*`, `quotes/*`

Components: YooptaEditor, TaskBoard, MediaGallery, CommentSection

Dashboard: stats active_projects, pending_quotes, monthly_income, pending_tasks + recent_projects + upcoming_tasks

QA diario: crear client/project/quote, cambiar status, add task/comment/media, pdf/duplicate/convert, kanban.

Features: Kanban drag real, Time tracker, Quote→Invoice pulido, Notion sync mock.
