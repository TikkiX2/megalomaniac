# MCPs Custom en Conexiones

> Agregá servidores **MCP remotos** (Model Context Protocol) como conexiones: las tools/resources/prompts del server quedan disponibles para el agente, con las mismas reglas de aprobación y auditoría que el resto de las integraciones.

## Cómo agregar un MCP

1. **Settings → Conexiones → Nueva conexión → MCP personalizado**.
2. Completá:
   - **Nombre**: cualquiera (ej. `Notion`).
   - **Base URL**: la URL del servidor MCP (ej. `https://mcp.notion.com/mcp`).
   - **Bearer token** (opcional): si el server usa token fijo.
   - **OAuth client_id/secret/scope** (opcional): solo si el server no soporta registro dinámico (DCR).
3. Guardá y usá **Probar**:
   - Si el server no pide auth → `MCP OK` con la cantidad de tools/resources/prompts.
   - Si pide OAuth → aparece **Requiere autorización**; usá el botón **Conectar** de la card (DCR + PKCE automáticos).
4. **Editar** la conexión → panel **Herramientas del MCP**: lista las tools descubiertas con su nivel de acceso (read/write/destructive) y toggles para habilitar/deshabilitar. Los recursos y prompts también se listan.

## Ejemplo: Notion MCP

- URL: `https://mcp.notion.com/mcp`
- Auth: **OAuth** (dejar el token vacío y usar **Conectar**). El flujo descubre `https://mcp.notion.com/.well-known/oauth-protected-resource`, registra el cliente dinámicamente (`/register`) y guarda los tokens cifrados en la conexión (con refresh automático).
- Tools típicas: buscar páginas, crear/actualizar páginas, consultar databases. Las de escritura quedan sujetas a **aprobación** (bandeja Aprobaciones).

## Reglas de acceso

| Tipo de tool | Comportamiento |
|---|---|
| `readOnlyHint` | Lectura libre (se audita en Actividad). |
| Sin annotations | Escritura → requiere aprobación. |
| `destructiveHint` | Destructiva → requiere aprobación. |

- El agente descubre las tools vía `integration_catalog` y las invoca con `integration_call` (grupo **Integrations**).
- Los agentes background pueden limitarse a este MCP por **nombre o ID** en su `tools_policy` (`{"integrations": ["Notion"]}`).
- Deshabilitar una tool en el panel la oculta del agente (no aparece en el catálogo y las llamadas directas se rechazan).

## Prompts en el chat

El composer del chat tiene un menú **Prompts** con los prompts de los MCPs conectados: al elegir uno se piden los argumentos y la plantilla renderizada se inserta en el mensaje.

## Límites y notas

- Solo transporte **HTTP remoto** (stdio no soportado en esta versión).
- Resultados capados a 64 KB (config `integrations.mcp.output_cap_bytes`), timeout 20 s (`integrations.mcp.timeout`).
- La conexión usa el client oficial de `laravel/mcp`; el handshake/sesión (`MCP-Session-Id`) y el refresh OAuth son automáticos.
