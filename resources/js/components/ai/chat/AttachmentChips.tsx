import { AlertCircle, FileText, Loader2, RotateCcw, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { ChatAttachment } from '@/types/chat';

interface AttachmentChipsProps {
    attachments: ChatAttachment[];
    onRemove?: (id: string) => void;
    onRetry?: (id: string) => void;
    className?: string;
}

export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function attachmentStatusLabel(attachment: ChatAttachment): string {
    if (attachment.status === 'failed') {
        return attachment.error ?? 'Error al procesar';
    }

    if (attachment.status === 'pending') {
        return attachment.id.startsWith('local-') ? 'Subiendo…' : 'Indexando…';
    }

    if (attachment.status === 'indexed') {
        return 'Indexado';
    }

    return formatBytes(attachment.size);
}

export function AttachmentChips({ attachments, onRemove, onRetry, className }: AttachmentChipsProps) {
    const [preview, setPreview] = useState<ChatAttachment | null>(null);

    if (attachments.length === 0) {
        return null;
    }

    return (
        <>
            <div className={cn('flex flex-wrap gap-2', className)}>
                {attachments.map((attachment) => {
                    const failed = attachment.status === 'failed';
                    const busy = attachment.status === 'pending';
                    const isImage = attachment.is_image || attachment.kind === 'image';

                    return (
                        <div
                            key={attachment.id}
                            className={cn(
                                'flex max-w-full items-center gap-2 rounded-lg border bg-background/60 p-1',
                                failed ? 'border-destructive/60' : 'border-border',
                            )}
                        >
                            {isImage && attachment.url !== '' ? (
                                <button
                                    type="button"
                                    onClick={() => setPreview(attachment)}
                                    aria-label={`Ver ${attachment.name}`}
                                    className="shrink-0"
                                >
                                    <img
                                        src={attachment.url}
                                        alt={attachment.name}
                                        className="h-10 w-10 rounded-lg object-cover"
                                    />
                                </button>
                            ) : (
                                <span
                                    className={cn(
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                        failed ? 'bg-destructive/15' : 'bg-muted',
                                    )}
                                >
                                    {busy ? (
                                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                    ) : (
                                        <FileText
                                            className={cn('h-4 w-4', failed ? 'text-destructive' : 'text-muted-foreground')}
                                        />
                                    )}
                                </span>
                            )}

                            <div className="min-w-0">
                                <p className="max-w-40 truncate text-xs text-foreground" title={attachment.name}>
                                    {attachment.name}
                                </p>
                                <p
                                    className={cn(
                                        'flex items-center gap-1 text-[10px]',
                                        failed ? 'text-destructive' : 'text-muted-foreground',
                                    )}
                                >
                                    {failed && <AlertCircle className="h-3 w-3 shrink-0" />}
                                    {busy && !failed && <Loader2 className="h-3 w-3 shrink-0 animate-spin" />}
                                    <span className="max-w-40 truncate" title={attachmentStatusLabel(attachment)}>
                                        {attachmentStatusLabel(attachment)}
                                    </span>
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center">
                                {failed && onRetry !== undefined && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRetry(attachment.id)}
                                        aria-label={`Reintentar ${attachment.name}`}
                                        className="h-6 w-6 text-destructive hover:text-destructive"
                                    >
                                        <RotateCcw className="h-3 w-3" />
                                    </Button>
                                )}

                                {onRemove !== undefined && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRemove(attachment.id)}
                                        aria-label={`Quitar ${attachment.name}`}
                                        className="h-6 w-6 text-muted-foreground hover:text-foreground"
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            <Dialog
                open={preview !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreview(null);
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-3xl">
                    <DialogTitle className="truncate text-sm text-foreground">{preview?.name}</DialogTitle>

                    {preview !== null && (
                        <img src={preview.url} alt={preview.name} className="max-h-[70vh] w-full rounded-lg object-contain" />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
