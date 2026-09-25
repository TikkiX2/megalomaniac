/**
 * CSRF headers for same-origin fetch requests.
 *
 * Prefers the XSRF-TOKEN cookie (rotated on every response, so it is always
 * current — this is what axios/Inertia use) and falls back to the
 * <meta name="csrf-token"> tag for environments where the cookie is missing.
 *
 * The meta tag can go stale: Fortify regenerates the CSRF token on login and
 * Inertia navigations do not re-render the document, so a meta-only token
 * would produce 419 responses after a client-side login.
 */
export function csrfHeaders(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    if (match) {
        try {
            return { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
        } catch {
            // Malformed cookie: fall through to the meta tag.
        }
    }

    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return meta ? { 'X-CSRF-TOKEN': meta } : {};
}
