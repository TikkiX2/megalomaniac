import { FileText, Loader2, Paperclip, Search, Unlink, UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { attachmentStatusLabel, formatBytes } from '@/components/ai/chat/AttachmentChips';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ChatAttachment } from '@/types/chat';

interface ThreadSourcesPanelProps {
    sources: ChatAttachment[];
    library: ChatAttachment[];
    onAttach: (attachmentId: string) => Promise<void>;
    onDetach: (attachmentId: string) => Promise<void>;
    onUpload: (file: File) => Promise<void>;
    disabled?: boolean;
}

const STATUS_LABELS: Record<ChatAttachment['status'], string> = {
    ready: 'Listo',
    pending: 'Indexando…',
    indexed: 'Indexado',
    failed: 'Error',
};

const STATUS_TONES: Record<ChatAttachment['status'], string> = {
    ready: 'border-primary/30 bg-primary/10 text-primary',
    pending: 'border-border bg-muted text-muted-foreground',
    indexed: 'border-primary/30 bg-primary/10 text-primary',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
};

function messageOf(caught: unknown): string {
    return caught instanceof Error ? caught.message : 'No se pudo completar la acción.';
}

function StatusBadge({ attachment }: { attachment: ChatAttachment }) {
    return (
        <span
            title={attachmentStatusLabel(attachment)}
            className={cn(
                'shrink-0 rounded-full border px-1.5 py-px text-[9px] font-medium tracking-wide uppercase',
                STATUS_TONES[attachment.status],
            )}
        >
            {STATUS_LABELS[attachment.status]}
        </span>
    );
}

export function ThreadSourcesPanel({ sources, library, onAttach, onDetach, onUpload, disabled = false }: ThreadSourcesPanelProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [attachBusyId, setAttachBusyId] = useState<string | null>(null);
    const [detachBusyId, setDetachBusyId] = useState<string | null>(null);
    const [fileBusy, setFileBusy] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const attachedIds = new Set(sources.map((source) => source.id));
    const normalizedQuery = query.trim().toLowerCase();
    const filtered = normalizedQuery === ''
        ? library
        : library.filter((document) => document.name.toLowerCase().includes(normalizedQuery));
    const busy = fileBusy || attachBusyId !== null || detachBusyId !== null;

    const attach = async (attachmentId: string) => {
        setError(null);
        setAttachBusyId(attachmentId);

        try {
            await onAttach(attachmentId);
        } catch (caught) {
            setError(messageOf(caught));
        } finally {
            setAttachBusyId(null);
        }
    };

    const detach = async (attachmentId: string) => {
        setError(null);
        setDetachBusyId(attachmentId);

        try {
            await onDetach(attachmentId);
        } catch (caught) {
            setError(messageOf(caught));
        } finally {
            setDetachBusyId(null);
        }
    };

    const upload = async (files: File[]) => {
        const file = files[0];

        if (file === undefined) return;

        setError(null);
        setFileBusy(true);

        try {
            await onUpload(file);
        } catch (caught) {
            setError(messageOf(caught));
        } finally {
            setFileBusy(false);
        }
    };

    const { getRootProps, isDragActive } = useDropzone({
        onDrop: (files) => {
            void upload(files);
        },
        onDropRejected: () => setError('Solo se admiten documentos .txt, .md o .docx.'),
        accept: {
            'text/plain': ['.txt'],
            'text/markdown': ['.md'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
        },
        multiple: false,
        noClick: true,
        disabled: disabled || busy,
    });

    return (
        <div className="mt-2 rounded-xl border border-border bg-card/60 p-2">
            <div className="flex items-center justify-between gap-2 px-1">
                <p className="text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                    Fuentes adjuntas{sources.length > 0 ? ` · ${sources.length}` : ''}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setError(null);
                        setOpen(true);
                    }}
                    disabled={disabled}
                    className="h-6 gap-1 px-2 text-[10px] text-muted-foreground hover:text-foreground"
                >
                    <Paperclip className="h-3 w-3" />
                    Adjuntar
                </Button>
            </div>

            {sources.length === 0 ? (
                <p className="px-1 py-1 text-[11px] text-muted-foreground">
                    Sin fuentes adjuntas. La IA usará tus documentos de la biblioteca solo si los adjuntas aquí.
                </p>
            ) : (
                <ul className="mt-1 space-y-0.5">
                    {sources.map((source) => (
                        <li key={source.id} className="flex items-center gap-2 rounded-lg px-1 py-1">
                            <FileText
                                className={cn(
                                    'h-3.5 w-3.5 shrink-0',
                                    source.status === 'failed' ? 'text-destructive' : 'text-muted-foreground',
                                )}
                            />
                            <span className="min-w-0 flex-1 truncate text-xs text-foreground" title={source.name}>
                                {source.name}
                            </span>
                            <StatusBadge attachment={source} />
                            <span className="shrink-0 text-[10px] tabular-nums text-muted-foreground">
                                {formatBytes(source.size)}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => {
                                    void detach(source.id);
                                }}
                                disabled={disabled || busy}
                                aria-label={`Quitar ${source.name} del hilo`}
                                title="Quitar del hilo"
                                className="h-6 w-6 text-muted-foreground hover:text-destructive"
                            >
                                {detachBusyId === source.id ? (
                                    <Loader2 className="h-3 w-3 animate-spin" />
                                ) : (
                                    <Unlink className="h-3 w-3" />
                                )}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {error !== null && !open && (
                <p role="alert" className="px-1 pt-1 text-[10px] text-destructive">
                    {error}
                </p>
            )}

            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (!next) {
                        setQuery('');
                        setError(null);
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Adjuntar fuentes</DialogTitle>
                        <DialogDescription>
                            Elige documentos de tu biblioteca o sube uno nuevo. Las fuentes adjuntas se usan como
                            contexto en este hilo.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Buscar por nombre…"
                            aria-label="Buscar en la biblioteca"
                            className="h-8 border-border bg-background pl-8 text-xs"
                        />
                    </div>

                    <div
                        {...getRootProps({
                            className: cn(
                                'flex flex-wrap items-center justify-center gap-2 rounded-xl border border-dashed px-3 py-3 text-xs transition-colors',
                                isDragActive
                                    ? 'border-primary/60 bg-primary/5 text-foreground'
                                    : 'border-border text-muted-foreground hover:border-primary/40',
                            ),
                        })}
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            hidden
                            accept=".txt,.md,.docx"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file !== undefined) {
                                    void upload([file]);
                                }

                                event.target.value = '';
                            }}
                        />

                        {fileBusy ? (
                            <span className="flex items-center gap-2">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Subiendo…
                            </span>
                        ) : (
                            <>
                                <UploadCloud className="h-4 w-4" />
                                <span>Arrastra un documento aquí o</span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => fileInputRef.current?.click()}
                                    disabled={disabled || busy}
                                    className="h-6 border-border px-2 text-[10px]"
                                >
                                    Elegir archivo
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="max-h-72 overflow-y-auto rounded-xl border border-border">
                        {filtered.length === 0 ? (
                            <p className="px-3 py-6 text-center text-xs text-muted-foreground">
                                {library.length === 0
                                    ? 'Tu biblioteca está vacía. Sube un documento para empezar.'
                                    : 'Sin resultados para tu búsqueda.'}
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {filtered.map((document) => {
                                    const attached = attachedIds.has(document.id);
                                    const failed = document.status === 'failed';

                                    return (
                                        <li key={document.id} className="flex items-center gap-2 px-3 py-2">
                                            <FileText
                                                className={cn(
                                                    'h-3.5 w-3.5 shrink-0',
                                                    failed ? 'text-destructive' : 'text-muted-foreground',
                                                )}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs text-foreground" title={document.name}>
                                                    {document.name}
                                                </p>
                                                <p
                                                    className={cn(
                                                        'truncate text-[10px]',
                                                        failed ? 'text-destructive' : 'text-muted-foreground',
                                                    )}
                                                    title={attachmentStatusLabel(document)}
                                                >
                                                    {STATUS_LABELS[document.status]} · {formatBytes(document.size)}
                                                    {document.threads_count !== undefined && document.threads_count > 0
                                                        ? ` · En ${document.threads_count} ${document.threads_count === 1 ? 'hilo' : 'hilos'}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={attached ? 'ghost' : 'outline'}
                                                disabled={attached || failed || disabled || busy}
                                                onClick={() => {
                                                    void attach(document.id);
                                                }}
                                                className="h-7 shrink-0 border-border px-2 text-[10px]"
                                            >
                                                {attachBusyId === document.id ? (
                                                    <Loader2
                                                        role="img"
                                                        aria-label="Adjuntando…"
                                                        className="h-3 w-3 animate-spin"
                                                    />
                                                ) : attached ? (
                                                    'Adjuntada'
                                                ) : (
                                                    'Adjuntar'
                                                )}
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    {error !== null && (
                        <p role="alert" className="text-xs text-destructive">
                            {error}
                        </p>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
