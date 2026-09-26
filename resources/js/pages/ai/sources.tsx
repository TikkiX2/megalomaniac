import { Head } from '@inertiajs/react';
import { AlertCircle, Download, FileText, Loader2, Trash2, UploadCloud, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { attachmentStatusLabel, formatBytes } from '@/components/ai/chat/AttachmentChips';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAttachmentUpload } from '@/hooks/use-attachment-upload';
import MainLayout from '@/layouts/main-layout';
import { cn } from '@/lib/utils';
import type { AiChatState, ChatAttachment } from '@/types/chat';

interface SourcesPageProps {
    documents: ChatAttachment[];
    ai: AiChatState;
}

const DOCUMENT_ACCEPT = {
    'text/plain': ['.txt'],
    'text/markdown': ['.md'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
};

const STATUS_TONES: Record<ChatAttachment['status'], string> = {
    ready: 'border-primary/30 bg-primary/10 text-primary',
    pending: 'border-border bg-muted text-muted-foreground',
    indexed: 'border-primary/30 bg-primary/10 text-primary',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
};

function statusBadgeLabel(document: ChatAttachment): string {
    return document.status === 'failed' ? 'Error' : attachmentStatusLabel(document);
}

function sortTimestamp(document: ChatAttachment): number {
    if (document.created_at === null || document.created_at === undefined) {
        return Date.now();
    }

    const parsed = Date.parse(document.created_at);

    return Number.isNaN(parsed) ? Date.now() : parsed;
}

function formatDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Sources({ documents }: SourcesPageProps) {
    const upload = useAttachmentUpload(null, documents);
    const [dropError, setDropError] = useState<string | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ChatAttachment | null>(null);
    const [deleting, setDeleting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { getRootProps, isDragActive } = useDropzone({
        accept: DOCUMENT_ACCEPT,
        multiple: true,
        noClick: true,
        disabled: upload.uploading,
        onDrop: (files) => {
            setDropError(null);
            void upload.addFiles(files);
        },
        onDropRejected: (rejections) => {
            const names = rejections.map((rejection) => rejection.file.name).join(', ');

            setDropError(`No se admiten: ${names}. Usa archivos .txt, .md o .docx.`);
        },
    });

    const rows = [...upload.attachments].sort((left, right) => sortTimestamp(right) - sortTimestamp(left));
    const alert = dropError ?? upload.error;

    const confirmDelete = async (): Promise<void> => {
        if (pendingDelete === null) {
            return;
        }

        setDeleting(true);
        await upload.remove(pendingDelete.id);
        setDeleting(false);
        setPendingDelete(null);
    };

    return (
        <MainLayout>
            <Head title="Fuentes" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <Heading
                    title="Fuentes"
                    description="Tu biblioteca de documentos para el chat: subí archivos y adjuntalos a los hilos para usarlos como contexto."
                />

                <div
                    {...getRootProps({
                        className: cn(
                            'rounded-2xl border border-dashed px-4 py-6 text-center transition-colors',
                            isDragActive
                                ? 'border-primary/60 bg-primary/5'
                                : 'border-border bg-card/40 hover:border-primary/40',
                        ),
                    })}
                >
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        hidden
                        accept=".txt,.md,.docx"
                        onChange={(event) => {
                            if (event.target.files !== null && event.target.files.length > 0) {
                                setDropError(null);
                                void upload.addFiles(Array.from(event.target.files));
                            }

                            event.target.value = '';
                        }}
                    />

                    <div className="flex flex-col items-center gap-2">
                        {upload.uploading ? (
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                        ) : (
                            <UploadCloud className={cn('h-6 w-6', isDragActive ? 'text-primary' : 'text-muted-foreground')} />
                        )}

                        <p className="text-sm text-foreground">
                            {upload.uploading ? 'Subiendo documentos…' : 'Arrastrá tus documentos acá'}
                        </p>
                        <p className="text-xs text-muted-foreground">.txt, .md o .docx · hasta 25 MB por archivo</p>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={upload.uploading}
                            className="border-border"
                        >
                            Elegir archivos
                        </Button>
                    </div>
                </div>

                {alert !== null && alert !== '' && (
                    <div
                        role="alert"
                        className="flex items-start justify-between gap-3 rounded-xl border border-destructive/40 bg-destructive/10 px-3 py-2"
                    >
                        <p className="flex items-center gap-2 text-xs text-destructive">
                            <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                            {alert}
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                setDropError(null);
                                upload.dismissError();
                            }}
                            aria-label="Descartar aviso"
                            className="shrink-0 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                )}

                {rows.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-border bg-card px-4 py-12 text-center">
                        <FileText className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm font-medium text-foreground">Todavía no tenés fuentes</p>
                        <p className="max-w-md text-xs text-muted-foreground">
                            Subí un documento .txt, .md o .docx para guardarlo en tu biblioteca y adjuntarlo a cualquier
                            hilo del chat.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-border hover:bg-transparent">
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Tamaño</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Hilos</TableHead>
                                    <TableHead>Agregado</TableHead>
                                    <TableHead className="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((document) => {
                                    const failed = document.status === 'failed';
                                    const pending = document.status === 'pending';
                                    const openable = !failed && document.url !== '';
                                    const threads = document.threads ?? [];
                                    const threadsCount = document.threads_count ?? 0;

                                    return (
                                        <TableRow key={document.id} className="border-border">
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {pending ? (
                                                        <Loader2 className="h-4 w-4 shrink-0 animate-spin text-muted-foreground" />
                                                    ) : (
                                                        <FileText
                                                            className={cn(
                                                                'h-4 w-4 shrink-0',
                                                                failed ? 'text-destructive' : 'text-muted-foreground',
                                                            )}
                                                        />
                                                    )}
                                                    <span
                                                        className="max-w-64 truncate text-xs text-foreground"
                                                        title={document.name}
                                                    >
                                                        {document.name}
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <TableCell className="text-xs text-muted-foreground tabular-nums">
                                                {formatBytes(document.size)}
                                            </TableCell>

                                            <TableCell>
                                                <span
                                                    title={attachmentStatusLabel(document)}
                                                    className={cn(
                                                        'inline-flex rounded-full border px-1.5 py-px text-[9px] font-medium tracking-wide uppercase',
                                                        STATUS_TONES[document.status],
                                                    )}
                                                >
                                                    {statusBadgeLabel(document)}
                                                </span>
                                                {failed && document.error !== null && document.error !== '' && (
                                                    <span
                                                        className="mt-0.5 block max-w-40 truncate text-[10px] text-destructive"
                                                        title={document.error}
                                                    >
                                                        {document.error}
                                                    </span>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-xs">
                                                {threadsCount > 0 ? (
                                                    <div title={threads.join('\n')}>
                                                        <span className="text-foreground">
                                                            {threadsCount} {threadsCount === 1 ? 'hilo' : 'hilos'}
                                                        </span>
                                                        {threads.length > 0 && (
                                                            <span className="mt-0.5 block max-w-40 truncate text-[10px] text-muted-foreground">
                                                                {threads.slice(0, 2).join(', ')}
                                                                {threads.length > 2 ? '…' : ''}
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">Sin adjuntar</span>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(document.created_at)}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {openable ? (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                                                        >
                                                            <a
                                                                href={document.url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                aria-label={`Abrir ${document.name}`}
                                                                title="Abrir"
                                                            >
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled
                                                            aria-label={`Abrir ${document.name}`}
                                                            className="h-7 w-7 text-muted-foreground"
                                                        >
                                                            <Download className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => setPendingDelete(document)}
                                                        aria-label={`Eliminar ${document.name}`}
                                                        title="Eliminar"
                                                        className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setPendingDelete(null);
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar fuente</DialogTitle>
                        <DialogDescription>
                            Se eliminará “{pendingDelete?.name}” de tu biblioteca
                            {(pendingDelete?.threads_count ?? 0) > 0
                                ? ` y de los ${pendingDelete?.threads_count} hilos donde está adjunta`
                                : ''}
                            . Los hilos dejarán de usar este documento como contexto. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setPendingDelete(null)} disabled={deleting}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => {
                                void confirmDelete();
                            }}
                            disabled={deleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleting ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
