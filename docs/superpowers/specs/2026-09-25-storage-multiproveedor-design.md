# Storage Multiproveedor — Diseño

> Fecha: 2026-09-25 · Estado: aprobado · Alcance: Sub-proyecto 4 de 4 (1 Integraciones → 2 Agentes → 3 Feed → **4 Storage**)

## Problema

No hay un servicio de archivos: el agente no puede leer/escribir archivos del usuario, no existe browser de archivos y cada proveedor (local, S3, Drive, Nextcloud, Dropbox) tendría su propia integración ad-hoc.

## Objetivo

Un servicio de storage con proveedores populares (local, S3, SFTP/FTP, Google Drive, Dropbox, WebDAV/Nextcloud) sobre **Flysystem**, browser de archivos en `/storage`, y herramientas para el agente con las aprobaciones de SP1. Sin tablas nuevas: **cada disco es una `Connection`** de SP1 (kinds `storage_*`), reutilizando credenciales cifradas, executor, auditoría, aprobaciones, rate limit y catálogo de tools.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Proveedores v1: `local`, `s3`, `sftp`, `ftp`, `google_drive`, `dropbox`, `webdav` (Nextcloud). |
| 2 | Dependencias nuevas aprobadas: `masbug/flysystem-google-drive-ext`, `spatie/flysystem-dropbox`, `league/flysystem-webdav` (arrastran `google/apiclient`, `spatie/dropbox-api`, `sabre/dav`). Local/S3/SFTP/FTP ya están instalados. |
| 3 | **Discos = `connections`** con kinds `storage_*`; se registran en el `ConnectorRegistry` → executor/auditoría/aprobaciones/tools gratis. |
| 4 | Browser live (se lista contra el proveedor en cada request); v1 sin tabla `stored_files` (tags, indexado y shares persistentes quedan para una fase posterior). |
| 5 | OAuth (Drive/Dropbox) reutiliza `OAuthBroker` con presets nuevos; credenciales en `connections.credentials`. |
| 6 | Escrituras del agente → aprobación SP1 (`write`/`destructive`); lecturas libres. |
| 7 | Límites v1: upload ≤ 25 MB por request, listado ≤ 200 ítems por carpeta, previews de texto ≤ 256 KB. |

## Arquitectura

```
/storage ──► StorageBrowserController ──► StorageManager ──► StorageDriver (Flysystem)
agente ────► integration_call(storage_x.files.*) ──► IntegrationExecutor (SP1) ──► idem
connections ──► ConnectorRegistry (kinds storage_*)
```

- **`StorageDriver`** (interfaz): `list(path, cursor)`, `stat(path)`, `readStream(path)`, `readText(path, cap)`, `writeStream(path, stream)`, `mkdir`, `move`, `copy`, `delete(path)`, `temporaryUrl(path, ttl)` (null si no soporta).
- **`StorageManager::for(Connection $disk): StorageDriver`** construye el adapter Flysystem según `kind` + credenciales/options, con cache por request.
- **`StorageConnector`** (una clase base `AbstractStorageConnector` + subclases finas por proveedor) implementa `Connector` con las acciones de archivos; el `execute()` delega en `StorageManager`.
- Los discos aparecen también en Settings → Conexiones (wizard con `authFields` por proveedor y botón OAuth para Drive/Dropbox).

## Mapeo de proveedores

| Kind | Adapter Flysystem | `credentials` | `options` / `base_url` |
|---|---|---|---|
| `storage_local` | `LocalFilesystemAdapter` | — | `options.root` (path absoluto) |
| `storage_s3` | `AwsS3V3Adapter` | `key`, `secret` | `options.bucket`, `options.region`, `options.endpoint`, `options.prefix`; `base_url` = endpoint (opcional) |
| `storage_sftp` | `SftpAdapter` | `password` o `private_key` | `options.port`, `options.root`, `options.timeout`; `base_url` = `sftp://host` |
| `storage_ftp` | `FtpAdapter` | `username`, `password` | `options.port`, `options.root`, `options.passive`; `base_url` = `ftp://host` |
| `storage_google_drive` | `GoogleDriveAdapter` | OAuth token (access/refresh) | `options.root` (folder id opcional) |
| `storage_dropbox` | `DropboxAdapter` | OAuth token | `options.root` |
| `storage_webdav` | `WebDAVAdapter` | `username`, `password` | `base_url` = URL WebDAV, `options.root` |

## Acciones (catálogo del conector)

| key | access | params |
|---|---|---|
| `files.list` | read | `path` (default `/`), `limit` |
| `files.stat` | read | `path` |
| `files.read` | read | `path` (texto, cap 256 KB; binarios → metadata) |
| `files.upload` | write | `path`, `content` (texto) o `source_path` (archivo del server) |
| `files.mkdir` | write | `path` |
| `files.move` | write | `from`, `to` |
| `files.copy` | write | `from`, `to` |
| `files.share` | write | `path`, `ttl_minutes` (solo S3 presigned / Drive / Dropbox) |
| `files.delete` | destructive | `path`, `recursive` (default false) |

`test()` por proveedor: `list('/')` con manejo de error legible.

## OAuth

- Presets nuevos en `OAuthBroker`: `storage_google_drive` (scopes `drive.file` + `drive.readonly` o `drive` para navegar todo; default `drive`) y `storage_dropbox` (`files.content.write`, `files.content.read`, `sharing.write`).
- `config/services.php`: bloque `dropbox.oauth` (`DROPBOX_CLIENT_ID/SECRET`); Drive reutiliza `google.oauth`.
- El flujo UI es el mismo de SP1 (`integrations/oauth/{connection}/redirect|callback`).

## UI

| Método | URI | Action |
|---|---|---|
| GET | `storage` | browser (Inertia `storage/index`) |
| GET | `storage/browse` | JSON: `{disk_id, path, entries[]}` (para navegación sin recargar) |
| POST | `storage/upload` | upload multipart (≤ 25 MB) |
| POST | `storage/mkdir` | crear carpeta |
| POST | `storage/move` | mover/renombrar |
| DELETE | `storage/file` | borrar (`path`, `recursive`) |
| POST | `storage/share` | link temporal |
| GET | `storage/download` | stream de descarga |

- Página `/storage`: switcher de discos (solo `storage_*` del usuario), breadcrumb, vista lista/grid, dropzone, acciones por ítem (descargar, compartir, renombrar, mover, borrar), previews de texto/imagen, estados vacío/error/cargando. Si no hay discos: CTA al wizard de Conexiones.
- Sidebar: item **Archivos** (`HardDrive`).
- Settings → Conexiones: los discos ya aparecen; el wizard soporta los nuevos `authFields` y OAuth.

## Seguridad

- Path traversal: normalización con Flysystem (`PathNormalizer`) y rechazo de `..` fuera del root del disco.
- Upload: valida tamaño/tipo configurable; nunca escribe fuera del root.
- `temporaryUrl` solo para adapters que la soportan; shares con TTL mínimo 5 min y máximo 7 días.
- Credenciales cifradas (SP1); errores de proveedor mapeados sin filtrar secretos.
- Descargas vía controller autenticado + policy de dueño; nunca se expone el path del server.

## Testing

Pest 4, `tests/Feature/Storage/`:
- `StorageManager`: construcción del adapter por kind (con `Storage::fake`-equivalente: adapters en memoria de Flysystem para local; los demás con factory mockeado).
- Conectores: `files.list/stat/read/upload/mkdir/move/copy/delete` con un driver fake in-memory (`InMemoryFilesystemAdapter`), path traversal rechazado, cap de lectura.
- Executor: upload write → aprobación; delete destructive → aprobación; read inline; auditoría y rate limit.
- OAuth: presets construyen URLs y refrescan (Http::fake).
- UI Inertia: index/browse/upload/mkdir/move/delete/share/download, scoping 404, validaciones.
- QA Playwright: crear disco local apuntando a `storage/app/storage-qa`, subir/bajar/mover/borrar, share S3 omitido sin credenciales.

## Fuera de alcance (SP4)

Tags/indexado persistente (`stored_files`), versionado, papelera/restore, gestión de permisos de sharing, cuotas, sync bidireccional, migración de archivos entre discos, thumbnails de imágenes, cifrado cliente.

## Compatibilidad

- **SP1**: reutiliza registry/executor/aprobaciones/auditoría/OAuth/UI de conexiones; los discos son conexiones.
- **SP2**: los agentes pueden recibir `storage_*` en `tools_policy` sin cambios (allowlist de kinds).
- **SP3**: un digest/feed podría adjuntar archivos en el futuro (no en v1).
