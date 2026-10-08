import { useCallback, useEffect, useRef, useState } from 'react';
import { streamChatRequest } from '@/lib/chat-sse';
import type {
    ApprovalPayload,
    Citation,
    ChatTurnMeta,
    PendingApproval,
    ToolActivity,
    ToolPolicy,
} from '@/types/chat';

export type ChatStreamStatus =
    'idle' | 'streaming' | 'awaiting_approval' | 'error';

function normalizeApproval(approval: ApprovalPayload): PendingApproval {
    return {
        ...approval,
        reason: approval.reason ?? null,
        kind: approval.tool === 'AskUserTool' ? 'question' : 'approval',
    };
}

interface UseChatStreamOptions {
    onThread?: (threadId: string) => void;
    onError?: (message: string, recoverable: boolean) => void;
    onComplete?: (result: { text: string; citations: Citation[] }) => void;
    onPaused?: () => void;
}

export interface ChatStartOptions {
    /**
     * Mantiene las tarjetas de decisión vivas durante la petición y las
     * restaura si el retomo falla. Se usa al retomar un turno pausado
     * (`decide`/`approveAll`): sin esto, `start` las limpia al arrancar y un
     * fallo deja el hilo sin tarjetas (el bug de "no aparece la card").
     */
    retainApprovals?: boolean;
}

export interface UseChatStreamResult {
    status: ChatStreamStatus;
    text: string;
    reasoning: string;
    reasoningMs: number | null;
    citations: Citation[];
    tools: ToolActivity[];
    toolPolicy: ToolPolicy | null;
    pendingApprovals: PendingApproval[];
    error: string | null;
    /**
     * Proveedor del último turno emitido en vivo (`type:meta`). Vive en el
     * hook para que el badge aparezca durante el streaming, sin esperar al
     * `router.reload` que persiste el mensaje.
     */
    meta: ChatTurnMeta | null;
    start: (
        url: string,
        body: Record<string, unknown>,
        options?: ChatStartOptions,
    ) => Promise<void>;
    stop: () => void;
    reset: () => void;
    clearError: () => void;
}

export function useChatStream(
    options: UseChatStreamOptions = {},
): UseChatStreamResult {
    const [status, setStatus] = useState<ChatStreamStatus>('idle');
    const [text, setText] = useState('');
    const [reasoning, setReasoning] = useState('');
    const [reasoningMs, setReasoningMs] = useState<number | null>(null);
    const [citations, setCitations] = useState<Citation[]>([]);
    const [tools, setTools] = useState<ToolActivity[]>([]);
    const [toolPolicy, setToolPolicy] = useState<ToolPolicy | null>(null);
    const [pendingApprovals, setPendingApprovals] = useState<PendingApproval[]>(
        [],
    );
    const [error, setError] = useState<string | null>(null);
    const [meta, setMeta] = useState<ChatTurnMeta | null>(null);

    const abortRef = useRef<AbortController | null>(null);
    const erroredRef = useRef(false);
    const awaitingApprovalRef = useRef(false);
    const optionsRef = useRef(options);
    const textRef = useRef('');
    const reasoningRef = useRef('');
    const reasoningStartedAtRef = useRef<number | null>(null);
    const citationsRef = useRef<Citation[]>([]);
    /**
     * Espejo de `pendingApprovals`: `start` se memoriza con deps estables, así
     * que lee las tarjetas actuales desde acá y no desde el estado del cierre.
     */
    const approvalsRef = useRef<PendingApproval[]>([]);
    /**
     * Snapshot a restaurar si la petición en curso falla (solo con
     * `retainApprovals`); `null` cuando no hay nada que devolver.
     */
    const restoreApprovalsRef = useRef<PendingApproval[] | null>(null);

    const applyApprovals = useCallback((next: PendingApproval[]) => {
        approvalsRef.current = next;
        setPendingApprovals(next);
    }, []);

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

    /**
     * Devuelve las tarjetas que había antes del retomo: es lo que hace que un
     * fallo no se coma la decisión (el backend también restaura su estado).
     */
    const restoreApprovals = useCallback(() => {
        const snapshot = restoreApprovalsRef.current;

        if (snapshot === null) return;

        restoreApprovalsRef.current = null;
        applyApprovals(snapshot);
    }, [applyApprovals]);

    const stop = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        awaitingApprovalRef.current = false;
        // Detener a mitad de un retomo deja las decisiones pendientes en el
        // servidor: las tarjetas vuelven para que se puedan repetir.
        restoreApprovals();
        setStatus('idle');
    }, [restoreApprovals]);

    const reset = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        awaitingApprovalRef.current = false;
        restoreApprovalsRef.current = null;

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
        applyApprovals([]);
        setError(null);
        setStatus('idle');
        // `meta` sobrevive al reset: identifica el turno que acaba de terminar
        // y evita que el badge parpadee mientras llegan los mensajes persistidos.
    }, [applyApprovals]);

    const clearError = useCallback(() => setError(null), []);

    const start = useCallback(
        async (
            url: string,
            body: Record<string, unknown>,
            options: ChatStartOptions = {},
        ) => {
            abortRef.current?.abort();

            const controller = new AbortController();
            abortRef.current = controller;
            erroredRef.current = false;
            awaitingApprovalRef.current = false;

            // Al retomar una decisión las tarjetas no se limpian: quedan visibles
            // mientras el proveedor continúa y vuelven completas si algo falla.
            const retainApprovals = options.retainApprovals === true;
            const snapshot = approvalsRef.current;

            restoreApprovalsRef.current = retainApprovals ? snapshot : null;
            applyApprovals(retainApprovals ? snapshot : []);

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
            // El turno nuevo arranca sin proveedor conocido: el evento `meta`
            // lo publica cuando el backend sabe cuál respondió.
            setMeta(null);

            try {
                await streamChatRequest(
                    url,
                    body,
                    {
                        onThread: (threadId) =>
                            optionsRef.current.onThread?.(threadId),
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
                            if (
                                citationsRef.current.some(
                                    (existing) => existing.url === citation.url,
                                )
                            )
                                return;

                            citationsRef.current = [
                                ...citationsRef.current,
                                citation,
                            ];
                            setCitations(citationsRef.current);
                        },
                        onToolCall: (tool) => {
                            setTools((previous) => [
                                ...previous,
                                { ...tool, status: 'running' },
                            ]);
                        },
                        onTools: (groups, mode) => {
                            setToolPolicy({
                                mode: mode === 'manual' ? 'manual' : 'auto',
                                groups,
                            });
                        },
                        onToolResult: (result) => {
                            setTools((previous) =>
                                previous.map((tool) =>
                                    tool.id === result.id
                                        ? {
                                              ...tool,
                                              status: result.successful
                                                  ? 'done'
                                                  : 'failed',
                                          }
                                        : tool,
                                ),
                            );
                        },
                        onApprovalRequest: (approvals) => {
                            awaitingApprovalRef.current = true;
                            // Tarjetas frescas del backend: ya no hace falta el
                            // snapshot del retomo anterior.
                            restoreApprovalsRef.current = null;
                            applyApprovals(approvals.map(normalizeApproval));
                            setStatus('awaiting_approval');
                        },
                        onMeta: (turnMeta) => setMeta(turnMeta),
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
                    // El retomo falló: las decisiones siguen pendientes en el
                    // servidor (el backend las restaura), así que las tarjetas
                    // vuelven para que el usuario pueda reintentar.
                    restoreApprovals();
                    setStatus('error');
                    return;
                }

                if (awaitingApprovalRef.current) {
                    // The turn paused waiting for a decision: keep the pending cards
                    // alive instead of completing (a reload would erase them). The
                    // pause has settled (the paused message is persisted by now).
                    awaitingApprovalRef.current = false;
                    setStatus('awaiting_approval');
                    optionsRef.current.onPaused?.();
                    return;
                }

                setStatus('idle');
                optionsRef.current.onComplete?.({
                    text: textRef.current,
                    citations: citationsRef.current,
                });
            } catch (caught) {
                if (abortRef.current !== controller) return;

                abortRef.current = null;

                settleReasoning();

                if (
                    caught instanceof DOMException &&
                    caught.name === 'AbortError'
                ) {
                    restoreApprovals();
                    setStatus('idle');
                    return;
                }

                const message =
                    caught instanceof Error
                        ? caught.message
                        : 'La generación falló.';

                restoreApprovals();
                setError(message);
                setStatus('error');
                optionsRef.current.onError?.(message, false);
            }
        },
        [applyApprovals, restoreApprovals, settleReasoning],
    );

    return {
        status,
        text,
        reasoning,
        reasoningMs,
        citations,
        tools,
        toolPolicy,
        pendingApprovals,
        error,
        meta,
        start,
        stop,
        reset,
        clearError,
    };
}
