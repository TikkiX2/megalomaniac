import { useCallback, useEffect, useRef, useState } from 'react';
import { streamChatRequest } from '@/lib/chat-sse';
import type { Citation, ToolActivity } from '@/types/chat';

export type ChatStreamStatus = 'idle' | 'streaming' | 'error';

interface UseChatStreamOptions {
    onThread?: (threadId: string) => void;
    onComplete?: (result: { text: string; citations: Citation[] }) => void;
}

export interface UseChatStreamResult {
    status: ChatStreamStatus;
    text: string;
    citations: Citation[];
    tools: ToolActivity[];
    error: string | null;
    start: (url: string, body: Record<string, unknown>) => Promise<void>;
    stop: () => void;
    reset: () => void;
    clearError: () => void;
}

export function useChatStream(options: UseChatStreamOptions = {}): UseChatStreamResult {
    const [status, setStatus] = useState<ChatStreamStatus>('idle');
    const [text, setText] = useState('');
    const [citations, setCitations] = useState<Citation[]>([]);
    const [tools, setTools] = useState<ToolActivity[]>([]);
    const [error, setError] = useState<string | null>(null);

    const abortRef = useRef<AbortController | null>(null);
    const optionsRef = useRef(options);
    const textRef = useRef('');
    const citationsRef = useRef<Citation[]>([]);

    useEffect(() => {
        optionsRef.current = options;
    });

    const stop = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        setStatus('idle');
    }, []);

    const reset = useCallback(() => {
        textRef.current = '';
        citationsRef.current = [];
        setText('');
        setCitations([]);
        setTools([]);
        setError(null);
        setStatus('idle');
    }, []);

    const clearError = useCallback(() => setError(null), []);

    const start = useCallback(async (url: string, body: Record<string, unknown>) => {
        const controller = new AbortController();
        abortRef.current = controller;

        textRef.current = '';
        citationsRef.current = [];

        setText('');
        setCitations([]);
        setTools([]);
        setError(null);
        setStatus('streaming');

        try {
            await streamChatRequest(
                url,
                body,
                {
                    onThread: (threadId) => optionsRef.current.onThread?.(threadId),
                    onTextDelta: (delta) => {
                        textRef.current += delta;
                        setText(textRef.current);
                    },
                    onCitation: (citation) => {
                        if (citationsRef.current.some((existing) => existing.url === citation.url)) return;

                        citationsRef.current = [...citationsRef.current, citation];
                        setCitations(citationsRef.current);
                    },
                    onToolCall: (tool) => {
                        setTools((previous) => [...previous, { ...tool, status: 'running' }]);
                    },
                    onToolResult: (result) => {
                        setTools((previous) =>
                            previous.map((tool) =>
                                tool.id === result.id
                                    ? { ...tool, status: result.successful ? 'done' : 'failed' }
                                    : tool,
                            ),
                        );
                    },
                    onError: (message) => setError(message),
                },
                controller.signal,
            );

            abortRef.current = null;
            setStatus('idle');
            optionsRef.current.onComplete?.({ text: textRef.current, citations: citationsRef.current });
        } catch (caught) {
            abortRef.current = null;

            if (caught instanceof DOMException && caught.name === 'AbortError') {
                setStatus('idle');
                return;
            }

            setError(caught instanceof Error ? caught.message : 'La generación falló.');
            setStatus('error');
        }
    }, []);

    return { status, text, citations, tools, error, start, stop, reset, clearError };
}
