import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ChatAttachmentController from '@/actions/App/Http/Controllers/Ai/ChatAttachmentController';
import type { ChatAttachment } from '@/types/chat';

const MAX_FILES = 5;
const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const MAX_DOCUMENT_BYTES = 25 * 1024 * 1024;
const POLL_INTERVAL_MS = 3000;
const LOCAL_ID_PREFIX = 'local-';
const DOCUMENT_EXTENSIONS = ['.txt', '.md', '.docx'];
const NO_DOCUMENTS: ChatAttachment[] = [];

export interface UseAttachmentUploadResult {
    attachments: ChatAttachment[];
    addFiles: (files: File[] | FileList) => Promise<void>;
    remove: (id: string) => Promise<void>;
    removeSource: (id: string) => void;
    retry: (id: string) => Promise<void>;
    clearImages: () => void;
    readyIds: () => string[];
    readyImageIds: () => string[];
    uploading: boolean;
    hasFailed: boolean;
    error: string | null;
    dismissError: () => void;
}

function readCookie(name: string): string {
    const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
}

function requestHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
    };
}

/**
 * `destroy` responds with `back()` (302). Following that redirect with fetch
 * preserves the DELETE method and hits a 405, so the redirect is not followed:
 * a manual redirect (status 0 / opaqueredirect) means the delete already ran.
 */
async function deleteAttachment(id: string): Promise<void> {
    const response = await fetch(ChatAttachmentController.destroy.url(id), {
        method: 'DELETE',
        headers: requestHeaders(),
        redirect: 'manual',
    });

    if (!response.ok && response.type !== 'opaqueredirect') {
        throw new Error('No se pudo eliminar el archivo.');
    }
}

function newLocalId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `${LOCAL_ID_PREFIX}${crypto.randomUUID()}`;
    }

    return `${LOCAL_ID_PREFIX}${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
}

function isPersisted(id: string): boolean {
    return !id.startsWith(LOCAL_ID_PREFIX);
}

function mergeDocuments(previous: ChatAttachment[], documents: ChatAttachment[]): ChatAttachment[] {
    const byId = new Map(previous.map((attachment) => [attachment.id, attachment]));
    let changed = false;

    for (const document of documents) {
        const existing = byId.get(document.id);

        if (existing === undefined) {
            byId.set(document.id, document);
            changed = true;

            continue;
        }

        if (existing.status !== document.status || existing.error !== document.error) {
            byId.set(document.id, { ...existing, ...document });
            changed = true;
        }
    }

    return changed ? [...byId.values()] : previous;
}

function isImageFile(file: File): boolean {
    return file.type.startsWith('image/');
}

function isDocumentFile(file: File): boolean {
    const name = file.name.toLowerCase();

    return DOCUMENT_EXTENSIONS.some((extension) => name.endsWith(extension));
}

function validationErrorFor(file: File): string | null {
    if (!isImageFile(file) && !isDocumentFile(file)) {
        return `“${file.name}” no es compatible. Usa imágenes, .txt, .md o .docx.`;
    }

    if (isImageFile(file) && file.size > MAX_IMAGE_BYTES) {
        return `“${file.name}” supera los 10 MB permitidos para imágenes.`;
    }

    if (!isImageFile(file) && file.size > MAX_DOCUMENT_BYTES) {
        return `“${file.name}” supera los 25 MB permitidos para documentos.`;
    }

    return null;
}

export function errorMessageFromPayload(payload: unknown): string | null {
    if (payload === null || typeof payload !== 'object') {
        return null;
    }

    const record = payload as { message?: unknown; errors?: Record<string, unknown> };

    if (record.errors !== null && typeof record.errors === 'object') {
        const first = Object.values(record.errors)
            .flat()
            .find((value): value is string => typeof value === 'string');

        if (first !== undefined) {
            return first;
        }
    }

    return typeof record.message === 'string' ? record.message : null;
}

/**
 * `store` responds with a top-level attachment while `index` wraps the list in
 * `{ data: [...] }`; normalize both envelopes here so components stay agnostic.
 */
function normalizeAttachment(payload: unknown): ChatAttachment | null {
    if (payload === null || typeof payload !== 'object') {
        return null;
    }

    const candidate = (payload as { data?: unknown }).data ?? payload;

    if (candidate === null || typeof candidate !== 'object') {
        return null;
    }

    const attachment = candidate as ChatAttachment;

    return typeof attachment.id === 'string' ? attachment : null;
}

function normalizeAttachmentList(payload: unknown): ChatAttachment[] {
    const data = Array.isArray(payload)
        ? payload
        : payload !== null && typeof payload === 'object' && Array.isArray((payload as { data?: unknown }).data)
          ? ((payload as { data: unknown[] }).data)
          : [];

    return data
        .map((entry) => normalizeAttachment(entry))
        .filter((attachment): attachment is ChatAttachment => attachment !== null);
}

export function useAttachmentUpload(
    threadId?: string | null,
    documents: ChatAttachment[] = NO_DOCUMENTS,
): UseAttachmentUploadResult {
    const [attachments, setAttachments] = useState<ChatAttachment[]>(() => documents);
    const [error, setError] = useState<string | null>(null);
    const [seededDocuments, setSeededDocuments] = useState<ChatAttachment[]>(documents);

    // Seeded (prop-originated) ids never count toward the client upload cap:
    // they already live in the thread and are not uploads from this session.
    const seededIds = useMemo(() => new Set(documents.map((document) => document.id)), [documents]);

    const attachmentsRef = useRef<ChatAttachment[]>([]);
    const filesRef = useRef(new Map<string, File>());
    const removedRef = useRef(new Set<string>());

    useEffect(() => {
        attachmentsRef.current = attachments;
    }, [attachments]);

    // Server documents survive reloads; merge them without duplicating ids
    // already tracked from this session (React's adjust-state-on-prop-change).
    if (seededDocuments !== documents) {
        setSeededDocuments(documents);

        if (documents.length > 0) {
            setAttachments((previous) => mergeDocuments(previous, documents));
        }
    }

    const uploadOne = useCallback(
        async (file: File, replaceId?: string): Promise<void> => {
            const image = isImageFile(file);
            const localId = newLocalId();
            const placeholder: ChatAttachment = {
                id: localId,
                kind: image ? 'image' : 'document',
                name: file.name,
                mime: file.type,
                size: file.size,
                status: 'pending',
                error: null,
                is_image: image,
                url: '',
            };

            filesRef.current.set(localId, file);

            setAttachments((previous) =>
                replaceId === undefined
                    ? [...previous, placeholder]
                    : previous.map((attachment) => (attachment.id === replaceId ? placeholder : attachment)),
            );

            const form = new FormData();
            form.append('file', file);

            if (threadId) {
                form.append('thread_id', threadId);
            }

            try {
                const response = await fetch(ChatAttachmentController.store.url(), {
                    method: 'POST',
                    headers: requestHeaders(),
                    body: form,
                });

                if (!response.ok) {
                    const payload: unknown = await response.json().catch(() => null);

                    throw new Error(errorMessageFromPayload(payload) ?? 'No se pudo subir el archivo.');
                }

                const attachment = normalizeAttachment(await response.json());

                if (attachment === null) {
                    throw new Error('Respuesta inesperada del servidor.');
                }

                if (removedRef.current.delete(localId)) {
                    // The chip was removed while the upload was in flight.
                    void deleteAttachment(attachment.id).catch(() => undefined);

                    return;
                }

                filesRef.current.delete(localId);
                filesRef.current.set(attachment.id, file);

                setAttachments((previous) =>
                    previous.map((entry) => (entry.id === localId ? attachment : entry)),
                );
            } catch (caught) {
                const message = caught instanceof Error ? caught.message : 'No se pudo subir el archivo.';

                setError(message);
                setAttachments((previous) =>
                    previous.map((entry) =>
                        entry.id === localId ? { ...entry, status: 'failed', error: message } : entry,
                    ),
                );
            }
        },
        [threadId],
    );

    const addFiles = useCallback(
        async (incoming: File[] | FileList): Promise<void> => {
            const files = Array.from(incoming);

            if (files.length === 0) {
                return;
            }

            setError(null);

            const uploadableCount = attachmentsRef.current.filter(
                (attachment) => attachment.kind === 'image' || !seededIds.has(attachment.id),
            ).length;

            if (uploadableCount + files.length > MAX_FILES) {
                setError(`Puedes adjuntar hasta ${MAX_FILES} archivos por mensaje.`);

                return;
            }

            for (const file of files) {
                const validationError = validationErrorFor(file);

                if (validationError !== null) {
                    setError(validationError);

                    return;
                }
            }

            await Promise.all(files.map((file) => uploadOne(file)));
        },
        [uploadOne, seededIds],
    );

    const remove = useCallback(async (id: string): Promise<void> => {
        setError(null);

        if (!isPersisted(id)) {
            removedRef.current.add(id);
            filesRef.current.delete(id);
            setAttachments((previous) => previous.filter((attachment) => attachment.id !== id));

            return;
        }

        try {
            await deleteAttachment(id);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'No se pudo eliminar el archivo.');

            return;
        }

        filesRef.current.delete(id);
        setAttachments((previous) => previous.filter((attachment) => attachment.id !== id));
    }, []);

    /**
     * A server-seeded document was detached from the thread: drop it from the
     * tracked attachments (and the refs behind the cap/retry bookkeeping) so it
     * no longer counts toward MAX_FILES nor keeps `uploading`/`hasFailed` set.
     * Local ids stay untouched — they never reach the sources panel.
     */
    const removeSource = useCallback((id: string): void => {
        filesRef.current.delete(id);
        removedRef.current.delete(id);
        setAttachments((previous) => previous.filter((attachment) => attachment.id !== id));
    }, []);

    const retry = useCallback(
        async (id: string): Promise<void> => {
            const file = filesRef.current.get(id);

            if (file === undefined) {
                setError('Vuelve a adjuntar el archivo para reintentarlo.');

                return;
            }

            setError(null);
            filesRef.current.delete(id);

            if (isPersisted(id)) {
                // The failed record is replaced by a fresh upload.
                void deleteAttachment(id).catch(() => undefined);
            }

            await uploadOne(file, id);
        },
        [uploadOne],
    );

    const clearImages = useCallback((): void => {
        for (const attachment of attachmentsRef.current) {
            if (attachment.kind !== 'image') {
                continue;
            }

            if (!isPersisted(attachment.id)) {
                removedRef.current.add(attachment.id);
            }

            filesRef.current.delete(attachment.id);
        }

        setAttachments((previous) => previous.filter((attachment) => attachment.kind !== 'image'));
    }, []);

    const readyImageIds = useCallback(
        (): string[] =>
            attachmentsRef.current
                .filter(
                    (attachment) =>
                        isPersisted(attachment.id) &&
                        attachment.kind === 'image' &&
                        attachment.status === 'ready',
                )
                .map((attachment) => attachment.id),
        [],
    );

    const readyIds = useCallback(
        (): string[] =>
            attachmentsRef.current
                .filter(
                    (attachment) =>
                        isPersisted(attachment.id) &&
                        ((attachment.kind === 'image' && attachment.status === 'ready') ||
                            (attachment.kind === 'document' && attachment.status === 'indexed')),
                )
                .map((attachment) => attachment.id)
                .slice(0, MAX_FILES),
        [],
    );

    const pendingKey = attachments
        .filter((attachment) => attachment.status === 'pending' && isPersisted(attachment.id))
        .map((attachment) => attachment.id)
        .join(',');

    useEffect(() => {
        if (pendingKey === '') {
            return;
        }

        const ids = pendingKey.split(',');

        const poll = async (): Promise<void> => {
            try {
                const response = await fetch(ChatAttachmentController.index.url({ query: { ids } }), {
                    headers: requestHeaders(),
                });

                if (!response.ok) {
                    return;
                }

                const fresh = normalizeAttachmentList(await response.json());

                if (fresh.length === 0) {
                    return;
                }

                const byId = new Map(fresh.map((attachment) => [attachment.id, attachment]));

                setAttachments((previous) => {
                    let changed = false;

                    const next = previous.map((attachment) => {
                        const update = byId.get(attachment.id);

                        if (update === undefined || update.status === attachment.status) {
                            return attachment;
                        }

                        changed = true;

                        return { ...attachment, ...update };
                    });

                    return changed ? next : previous;
                });
            } catch {
                // Transient poll failures retry on the next tick.
            }
        };

        const interval = window.setInterval(() => {
            void poll();
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, [pendingKey]);

    const uploading = attachments.some((attachment) => attachment.status === 'pending');
    const hasFailed = attachments.some((attachment) => attachment.status === 'failed');

    return {
        attachments,
        addFiles,
        remove,
        removeSource,
        retry,
        clearImages,
        readyIds,
        readyImageIds,
        uploading,
        hasFailed,
        error,
        dismissError: useCallback(() => setError(null), []),
    };
}
