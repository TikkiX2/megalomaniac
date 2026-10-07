const KEY_PREFIX = 'megalomaniac.chat.draft.';

/**
 * The composer draft survives failed turns and reloads: a turn that errors
 * before anything is persisted must never eat the user's message.
 */
export function saveChatDraft(scope: string, message: string): void {
    try {
        sessionStorage.setItem(KEY_PREFIX + scope, message);
    } catch {
        // storage unavailable (private mode, quota): draft persistence is best-effort
    }
}

export function loadChatDraft(scope: string): string {
    try {
        return sessionStorage.getItem(KEY_PREFIX + scope) ?? '';
    } catch {
        return '';
    }
}

export function clearChatDraft(scope: string): void {
    try {
        sessionStorage.removeItem(KEY_PREFIX + scope);
    } catch {
        // ignore
    }
}