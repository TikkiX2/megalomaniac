# SP4 Storage Multiproveedor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** 7 conectores de storage (`storage_local|s3|sftp|ftp|google_drive|dropbox|webdav`) sobre Flysystem, browser `/storage`, y herramientas para el agente (vía executor/aprobaciones de SP1).

**Architecture:** **Los discos son `Connection`** con kinds `storage_*` → registry/executor/auditoría/aprobaciones/UI de conexiones gratis. `StorageManager` construye el `FilesystemOperator` por kind; `AbstractStorageConnector` implementa las acciones `files.*`; OAuth nuevo para Drive/Dropbox reutiliza `OAuthBroker`.

**Spec:** `docs/superpowers/specs/2026-09-25-storage-multiproveedor-design.md`

## Global Constraints

- Deps nuevas (aprobadas): `masbug/flysystem-google-drive-ext`, `spatie/flysystem-dropbox`, `league/flysystem-webdav`.
- Sin tabla nueva: discos = `connections`; browse live.
- Path traversal rechazado; cap de lectura 256 KB; upload ≤ 25 MB.
- Escrituras del agente pasan por aprobaciones (executor); la UI usa `ExecutionContext::forUi` (audita sin aprobación).
- Tests con adapters locales (tmp dir) vía `StorageManager` stubbeado; sin red.

---

### Task 1: Deps + StorageManager + AbstractStorageConnector

**Files:** `composer require` de las 3 deps; `app/Storage/StorageManager.php`, `app/Storage/AbstractStorageConnector.php`; `tests/Feature/Storage/StorageManagerTest.php`.

**Interfaces:**
- `StorageManager::for(Connection): FilesystemOperator` (match por kind; Drive/Dropbox refrescan OAuth antes de construir).
- `AbstractStorageConnector` (extiende `AbstractConnector`): helpers `fs(Connection)`, `safePath(string): ?string` (rechaza `..`), `entry(StorageAttributes): array`, `ok/failure`.
- Actions comunes: `files.list`, `files.stat`, `files.read`, `files.upload`, `files.mkdir`, `files.move`, `files.copy`, `files.share`, `files.delete` + `test()` (list `/`).

- [ ] Tests: `for()` construye `Filesystem` para cada kind (sin conectar); `safePath` rechaza traversal → RED → implementar → GREEN.

---

### Task 2: Conectores (7 kinds)

**Files:** `app/Storage/Connectors/{LocalStorageConnector,S3StorageConnector,SftpStorageConnector,FtpStorageConnector,GoogleDriveStorageConnector,DropboxStorageConnector,WebdavStorageConnector}.php`; `tests/Feature/Storage/StorageConnectorsTest.php`.

**Interfaces:** kinds/grupo `Archivos`/authFields por proveedor (spec §Mapeo); `local` usa `options.root`, S3 `options.bucket/region/endpoint/prefix`, SFTP/FTP host en `base_url` + puerto/root, WebDAV `base_url`, Drive/Dropbox OAuth.
`share`: `temporaryUrl` cuando el adapter lo soporta (S3) → URL; si no, failure legible.

- [ ] Tests (manager stub con tmp dir): list/stat/read/upload/mkdir/move/copy/delete reales sobre disco local; traversal rechazado; share no soportado; `test()` → RED → implementar → GREEN.

---

### Task 3: OAuth Drive/Dropbox + registro

**Files:** `app/Integrations/OAuth/Presets/{GoogleDriveOAuthPreset,DropboxOAuthPreset}.php`, `OAuthBroker` (+2 matches), `config/services.php` (+dropbox), `config/integrations.php` (+7), test de registry (24 kinds) y OAuth.

- [ ] Tests: URLs de autorización/refresh para ambos presets (Http::fake) → RED → implementar → GREEN.

---

### Task 4: UI `/storage` + rutas

**Files:** `app/Http/Controllers/Storage/StorageController.php`, requests, `routes/storage.php` (require), `resources/js/pages/storage/index.tsx`, sidebar; `tests/Feature/Storage/StoragePagesTest.php`.

**Rutas:** GET `storage` (browser), GET `storage/browse` (JSON), POST `storage/upload` (≤25MB), POST `storage/mkdir`, POST `storage/move`, DELETE `storage/file`, POST `storage/share`, GET `storage/download` (stream).
Acciones de escritura vía `IntegrationExecutor` con `ExecutionContext::forUi`.

- [ ] Tests Inertia/JSON (browse, upload, mkdir, delete, 404 ajeno, validaciones) → RED → implementar → GREEN; types + build.

---

### Task 5: QA y cierre

- [ ] QA Playwright: crear disco `storage_local` con root `storage/app/storage-qa` → subir archivo, listar, descargar, mover/renombrar, borrar; share no soportado muestra error legible; consola 0.
- [ ] `docs/qa/playwright-report.md` + commit condicional.

---

## Self-Review

**Cobertura:** 7 proveedores, manager, conectores, OAuth, UI, executor. Drive/Dropbox no se prueban contra la nube (solo construcción de adapter/preset); S3 presigned depende del adapter instalado. `stored_files`/tags quedan fuera (spec §Fuera de alcance).
