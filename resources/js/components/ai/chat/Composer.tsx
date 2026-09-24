import { ArrowUp, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ModelPicker } from '@/components/ai/chat/ModelPicker';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ComposerProps {
    models: string[];
    model: string | null;
    onModelChange: (model: string) => void;
    onSubmit: (message: string) => void;
    onStop?: () => void;
    streaming: boolean;
    disabled?: boolean;
    autoFocus?: boolean;
    large?: boolean;
    placeholder?: string;
}

const MAX_LENGTH = 4000;

export function Composer({
    models,
    model,
    onModelChange,
    onSubmit,
    onStop,
    streaming,
    disabled = false,
    autoFocus = false,
    large = false,
    placeholder = 'Pregunta lo que quieras…',
}: ComposerProps) {
    const [value, setValue] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (autoFocus) textareaRef.current?.focus();
    }, [autoFocus]);

    const resize = () => {
        const textarea = textareaRef.current;

        if (!textarea) return;

        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    };

    const submit = () => {
        const message = value.trim();

        if (message === '' || streaming || disabled) return;

        onSubmit(message);
        setValue('');
        requestAnimationFrame(resize);
    };

    const canSubmit = value.trim() !== '' && !streaming && !disabled;
    const nearLimit = value.length > MAX_LENGTH - 500;

    return (
        <div
            className={cn(
                'rounded-2xl border border-border bg-card shadow-lg shadow-black/20 transition-colors focus-within:border-primary/50',
                large ? 'p-3' : 'p-2',
            )}
        >
            <textarea
                ref={textareaRef}
                value={value}
                rows={1}
                maxLength={MAX_LENGTH}
                disabled={disabled}
                placeholder={placeholder}
                aria-label="Mensaje"
                onChange={(event) => {
                    setValue(event.target.value);
                    resize();
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        submit();
                    }
                }}
                className={cn(
                    'w-full resize-none bg-transparent px-2 py-1.5 text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none disabled:opacity-50',
                    large && 'min-h-12 text-base',
                )}
            />

            <div className="flex items-center justify-between gap-2 px-1 pt-1">
                <ModelPicker models={models} value={model} onChange={onModelChange} disabled={disabled || streaming} />

                <div className="flex items-center gap-3">
                    {nearLimit && (
                        <span
                            className={cn(
                                'text-[10px] tabular-nums',
                                value.length >= MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
                            )}
                        >
                            {value.length}/{MAX_LENGTH}
                        </span>
                    )}

                    {streaming ? (
                        <Button
                            type="button"
                            size="icon"
                            onClick={onStop}
                            aria-label="Detener generación"
                            className="h-8 w-8 rounded-full bg-card text-foreground ring-1 ring-border hover:bg-muted"
                        >
                            <Square className="h-3.5 w-3.5 fill-current" />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            size="icon"
                            onClick={submit}
                            disabled={!canSubmit}
                            aria-label="Enviar mensaje"
                            className="h-8 w-8 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-40"
                        >
                            <ArrowUp className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
