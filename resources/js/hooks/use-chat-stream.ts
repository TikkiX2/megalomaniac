import { useCallback, useEffect, useRef, useState } from 'react';
import { streamChatRequest } from '@/lib/chat-sse';
import type { Citation, ToolActivity, ToolPolicy } from '@/types/chat';

export type ChatStreamStatus = 'idle' | 'streaming' | 'error';

interface UseChatStreamOptions {
    onThread?: (threadId: string) => void;
    onError?: (message: string, recoverable: boolean) => void;
    onComplete?: (result: { text: string; citations: Citation[] }) => void;
}

export interface UseChatStreamResult {
    status: ChatStreamStatus;
    text: string;
    reasoning: string;
    reasoningMs: number | null;
    citations: Citation[];
    tools: ToolActivity[];
    toolPolicy: ToolPolicy | null;
    error: string | null;
    start: (url: string, body: Record<string, unknown>) => Promise<void>;
    stop: () => void;
    reset: () => void;
    clearError: () => void;
}

export function useChatStream(options: UseChatStreamOptions = {}): UseChatStreamResult {
    const [status, setStatus] = useState<ChatStreamStatus>('idle');
    const [text, setText] = useState('');
    const [reasoning, setReasoning] = useState('');
    const [reasoningMs, setReasoningMs] = useState<number | null>(null);
    const [citations, setCitations] = useState<Citation[]>([]);
    const [tools, setTools] = useState<ToolActivity[]>([]);
    const [toolPolicy, setToolPolicy] = useState<ToolPolicy | null>(null);
    const [error, setError] = useState<string | null>(null);

    const abortRef = useRef<AbortController | null>(null);
    const erroredRef = useRef(false);
    const optionsRef = useRef(options);
    const textRef = useRef('');
    const reasoningRef = useRef('');
    const reasoningStartedAtRef = useRef<number | null>(null);
    const citationsRef = useRef<Citation[]>([]);

    useEffect(() => {
        optionsRef.current = options;
    });

    // The gateway may end the stream without emitting `reasoning_end` (for
    // example, when the provider errors mid-stream), so the timer is settled
    // whenever the request finishes and the accumulated reasoning stays
    // visible with streaming=false instead of waiting for an end event.
    const settleReasoning = useCallback(() => {
        if (reasoningStartedAtRef.current === null) return;

        setReasoningMs(Date.now() - reasoningStartedAtRef.current);
        reasoningStartedAtRef.current = null;
    }, []);

    const stop = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        setStatus('idle');
    }, []);

    const reset = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;

        textRef.current = '';
        reasoningRef.current = '';
        reasoningStartedAtRef.current = null;
        citationsRef.current = [];
        setText('');
        setReasoning('');
        setReasoningMs(null);
        setCitations([]);
        setTools([]);
        setToolPolicy(null);
        setError(null);
        setStatus('idle');
    }, []);

    const clearError = useCallback(() => setError(null), []);

    const start = useCallback(async (url: string, body: Record<string, unknown>) => {
        abortRef.current?.abort();

        const controller = new AbortController();
        abortRef.current = controller;
        erroredRef.current = false;

        textRef.current = '';
        reasoningRef.current = '';
        reasoningStartedAtRef.current = null;
        citationsRef.current = [];

        setText('');
        setReasoning('');
        setReasoningMs(null);
        setCitations([]);
        setTools([]);
        setToolPolicy(null);
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
                    onReasoningDelta: (delta) => {
                        reasoningStartedAtRef.current ??= Date.now();
                        reasoningRef.current += delta;
                        setReasoning(reasoningRef.current);
                    },
                    onCitation: (citation) => {
                        if (citationsRef.current.some((existing) => existing.url === citation.url)) return;

                        citationsRef.current = [...citationsRef.current, citation];
                        setCitations(citationsRef.current);
                    },
                    onToolCall: (tool) => {
                        setTools((previous) => [...previous, { ...tool, status: 'running' }]);
                    },
                    onTools: (groups, mode) => {
                        setToolPolicy({ mode: mode === 'manual' ? 'manual' : 'auto', groups });
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
                    onError: (message, recoverable) => {
                        setError(message);

                        if (!recoverable) {
                            erroredRef.current = true;
                        }

                        optionsRef.current.onError?.(message, recoverable);
                    },
                },
                controller.signal,
            );

            if (abortRef.current !== controller) return;

            abortRef.current = null;

            settleReasoning();

            if (erroredRef.current) {
                setStatus('error');
                return;
            }

            setStatus('idle');
            optionsRef.current.onComplete?.({ text: textRef.current, citations: citationsRef.current });
        } catch (caught) {
            if (abortRef.current !== controller) return;

            abortRef.current = null;

            settleReasoning();

            if (caught instanceof DOMException && caught.name === 'AbortError') {
                setStatus('idle');
                return;
            }

            const message = caught instanceof Error ? caught.message : 'La generación falló.';

            setError(message);
            setStatus('error');
            optionsRef.current.onError?.(message, false);
        }
    }, [settleReasoning]);

    return { status, text, reasoning, reasoningMs, citations, tools, toolPolicy, error, start, stop, reset, clearError };
}
