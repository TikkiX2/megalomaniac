import { Check, Copy, Pencil } from 'lucide-react';
import { useState } from 'react';
import { AttachmentChips } from '@/components/ai/chat/AttachmentChips';
import { Button } from '@/components/ui/button';
import type { ChatMessage } from '@/types/chat';

interface UserMessageProps {
    message: ChatMessage;
    onEdit: (messageId: string, content: string) => void;
    disabled?: boolean;
}

export function UserMessage({ message, onEdit, disabled = false }: UserMessageProps) {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(message.content);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(message.content);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    const commit = () => {
        const trimmed = value.trim();

        setEditing(false);

        if (trimmed === '' || trimmed === message.content) {
            setValue(message.content);

            return;
        }

        onEdit(message.id, trimmed);
    };

    return (
        <article className="group flex justify-end">
            <div className="max-w-[85%]">
                {editing ? (
                    <div className="rounded-2xl border border-primary/40 bg-card p-3">
                        <textarea
                            autoFocus
                            value={value}
                            rows={Math.min(8, Math.max(2, value.split('\n').length))}
                            onChange={(event) => setValue(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !event.shiftKey) {
                                    event.preventDefault();
                                    commit();
                                }

                                if (event.key === 'Escape') {
                                    setValue(message.content);
                                    setEditing(false);
                                }
                            }}
                            className="w-full resize-none bg-transparent text-sm text-foreground focus:outline-none"
                            aria-label="Editar mensaje"
                        />
                        <div className="mt-2 flex justify-end gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setValue(message.content);
                                    setEditing(false);
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={commit} className="bg-primary text-primary-foreground hover:bg-primary/90">
                                Enviar
                            </Button>
                        </div>
                    </div>
                ) : (
                    <>
                        <AttachmentChips attachments={message.attachments ?? []} className="mb-1.5 justify-end" />

                        <div className="whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm border border-primary/20 bg-primary/10 px-4 py-2.5 text-sm text-foreground">
                            {message.content}
                        </div>

                        <div className="mt-1 flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={copy}
                                aria-label="Copiar mensaje"
                                className="h-6 w-6 text-muted-foreground hover:text-foreground"
                            >
                                {copied ? <Check className="h-3 w-3 text-primary" /> : <Copy className="h-3 w-3" />}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setEditing(true)}
                                disabled={disabled}
                                aria-label="Editar mensaje"
                                className="h-6 w-6 text-muted-foreground hover:text-foreground"
                            >
                                <Pencil className="h-3 w-3" />
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </article>
    );
}
