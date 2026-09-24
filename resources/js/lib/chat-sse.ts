import type { Citation } from '@/types/chat';

export interface ChatStreamHandlers {
    onThread?: (threadId: string) => void;
    onTextDelta?: (delta: string) => void;
    onCitation?: (citation: Citation) => void;
    onToolCall?: (tool: { id: string; name: string }) => void;
    onToolResult?: (tool: { id: string; name: string; successful: boolean }) => void;
    onError?: (message: string, recoverable: boolean) => void;
}

interface StreamEvent {
    type?: string;
    threadId?: string;
    delta?: string;
    citation?: { url?: string; title?: string | null };
    tool_id?: string;
    tool_name?: string;
    successful?: boolean;
    message?: string;
    recoverable?: boolean;
}

function readCookie(name: string): string {
    const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
}

function dispatch(event: StreamEvent, handlers: ChatStreamHandlers): void {
    switch (event.type) {
        case 'thread':
            if (event.threadId) handlers.onThread?.(event.threadId);
            break;
        case 'text_delta':
            if (event.delta) handlers.onTextDelta?.(event.delta);
            break;
        case 'citation':
            if (event.citation?.url) {
                handlers.onCitation?.({
                    url: event.citation.url,
                    title: event.citation.title ?? null,
                });
            }
            break;
        case 'tool_call':
            if (event.tool_id) handlers.onToolCall?.({ id: event.tool_id, name: event.tool_name ?? 'tool' });
            break;
        case 'tool_result':
            if (event.tool_id) {
                handlers.onToolResult?.({
                    id: event.tool_id,
                    name: event.tool_name ?? 'tool',
                    successful: event.successful !== false,
                });
            }
            break;
        case 'error':
            handlers.onError?.(event.message ?? 'La generación falló.', event.recoverable === true);
            break;
        default:
            break;
    }
}

export async function streamChatRequest(
    url: string,
    body: Record<string, unknown>,
    handlers: ChatStreamHandlers,
    signal?: AbortSignal,
): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify(body),
        signal,
    });

    if (!response.ok) {
        let message = 'No se pudo iniciar la respuesta.';

        try {
            const payload = await response.json();
            message = payload.message ?? message;
        } catch {
            // respuesta sin cuerpo JSON
        }

        throw new Error(message);
    }

    const reader = response.body?.getReader();

    if (!reader) {
        throw new Error('Tu navegador no soporta streaming.');
    }

    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();

        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        const parts = buffer.split('\n\n');
        buffer = parts.pop() ?? '';

        for (const part of parts) {
            const line = part.trim();

            if (!line.startsWith('data:')) continue;

            const payload = line.slice(5).trim();

            if (payload === '') continue;

            if (payload === '[DONE]') return;

            try {
                dispatch(JSON.parse(payload) as StreamEvent, handlers);
            } catch {
                // línea no JSON
            }
        }
    }
}
