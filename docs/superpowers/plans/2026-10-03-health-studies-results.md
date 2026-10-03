# Health Studies & PDF Import (Fase 2) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implementar el sistema de estudios médicos, resultados estructurados y el pipeline de importación de PDFs con visión AI.

**Architecture:** 
- `HealthStudy` y `HealthStudyResult` como modelos principales.
- `HealthService` extendido para manejar estas entidades.
- Pipeline de PDF: `pdftoppm` (rasterizado) -> Vision Model (extracción) -> Human Review -> Save.
- Límite de imágenes por mensaje subido de 5 a 50 (requerido para PDFs largos).

## Global Constraints
- Seguir patrones de Fase 1: scoping por `user_id`/`person_id`, `casts()`, `HealthService` como única vía de escritura.
- UI: Tokens Ember, componentes `ui/*`, Wayfinder `@/routes/health`.
- Test: Pest 4, RefreshDatabase, ownership 403.

## Tareas

### Task 1: Data Layer (Estudios y Resultados)
- [ ] Migraciones: `health_studies`, `health_study_results`.
- [ ] Enums: `StudyType`, `ResultFlag`.
- [ ] Modelos: `HealthStudy`, `HealthStudyResult` (con relaciones y casts).
- [ ] Factories y Seeder (datos de laboratorio TSH/CK para el test@example.com).
- [ ] Tests: `tests/Feature/Health/HealthStudyDataTest.php`.

### Task 2: Infraestructura PDF y Dependencias
- [ ] Docker: Instalar `poppler-utils` en los Dockerfiles.
- [ ] Composer: `smalot/pdfparser`.
- [ ] Config: `config/health.php` para límites de páginas (50) y DPI de rasterizado.
- [ ] `PdfService`: Helper para extraer texto y contar páginas.

### Task 3: Pipeline de Rasterizado y Visión
- [ ] Job `RasterizePdfPages`: usa `pdftoppm` para generar JPEGs.
- [ ] Integration: Enviar imágenes al modelo multimodal (AI SDK).
- [ ] `StudyImportService`: Coordina el flujo de extracción estructurada.

### Task 4: Web CRUD - Estudios
- [ ] `HealthStudyController` (Inertia).
- [ ] `Index.tsx`: Listado de estudios con filtros.
- [ ] `Show.tsx`: Detalle del estudio, tabla de resultados y visor de archivos.
- [ ] `Form.tsx`: Alta manual de estudios.

### Task 5: Importación PDF (Frontend)
- [ ] `ImportModal.tsx`: Subida de PDF, progreso de rasterizado.
- [ ] `ReviewProposal.tsx`: Grilla editable de resultados propuestos por la IA.
- [ ] Acción "Confirmar e Importar" que persiste el estudio.

### Task 6: API v1, MCP y Chat
- [ ] API Endpoints y Resources.
- [ ] MCP Tools: `health-study-read/write`.
- [ ] Chat: `HealthActionTool` extendida para manejar estudios.
- [ ] Aumentar límite de adjuntos de imagen 5 -> 50 en `use-attachment-upload.ts`.

### Task 7: Cierre y QA
- [ ] Suite completa de tests.
- [ ] Build y types.
- [ ] Rebuild de imagen Docker y deploy.

PLAN
