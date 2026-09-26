import { ArrowUp, Paperclip, Square } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { AgentPicker } from '@/components/ai/chat/AgentPicker';
import { AttachmentChips } from '@/components/ai/chat/AttachmentChips';
import { ModelPicker } from '@/components/ai/chat/ModelPicker';
import { SourceModeMenu } from '@/components/ai/chat/SourceModeMenu';
import { ToolsPicker } from '@/components/ai/chat/ToolsPicker';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ChatAttachment, SourceMode, ToolPolicy } from '@/types/chat';

interface ComposerProps {
    models: string[];
    model: string | null;
    onModelChange: (model: string) => void;
    agents?: { key: string; name: string }[];
    agent?: string;
    onAgentChange?: (key: string) => void;
    toolGroups?: { key: string; label: string }[];
    toolsPolicy?: ToolPolicy;
    onToolsPolicyChange?: (policy: ToolPolicy) => void;
    sourceMode?: SourceMode;
    forceWeb?: boolean;
    onSourceModeChange?: (mode: SourceMode) => void;
    onForceWebChange?: (forceWeb: boolean) => void;
    hasTavilyKey?: boolean;
    attachments?: ChatAttachment[];
    onAddFiles?: (files: File[]) => void;
    onRemoveAttachment?: (id: string) => void;
    onRetryAttachment?: (id: string) => void;
    uploading?: boolean;
    attachmentFailed?: boolean;
    attachmentError?: string | null;
    onDismissAttachmentError?: () => void;
    onSubmit: (message: string) => void;
    onStop?: () => void;
    streaming: boolean;
    disabled?: boolean;
    autoFocus?: boolean;
    large?: boolean;
    placeholder?: string;
    initialValue?: string;
}

const MAX_LENGTH = 4000;
const FILE_ACCEPT = 'image/*,.txt,.md,.docx';
const VISION_MODELS = ['deepseek-v4-flash-vision-exp', 'gpt-', 'gemini-', 'claude-', 'grok-'];

function isVisionModel(model: string | null): boolean {
    return model !== null && VISION_MODELS.some((prefix) => model.startsWith(prefix));
}

export function Composer({
    models,
    model,
    onModelChange,
    agents = [],
    agent = 'megalomaniac',
    onAgentChange,
    toolGroups = [],
    toolsPolicy,
    onToolsPolicyChange,
    sourceMode,
    forceWeb = false,
    onSourceModeChange,
    onForceWebChange,
    hasTavilyKey = false,
    attachments = [],
    onAddFiles,
    onRemoveAttachment,
    onRetryAttachment,
    uploading = false,
    attachmentFailed = false,
    attachmentError = null,
    onDismissAttachmentError,
    onSubmit,
    onStop,
    streaming,
    disabled = false,
    autoFocus = false,
    large = false,
    placeholder = 'Pregunta lo que quieras…',
    initialValue = '',
}: ComposerProps) {
    const [value, setValue] = useState(initialValue);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const attachmentsDisabled = disabled || streaming;

    const { getRootProps, isDragActive } = useDropzone({
        onDrop: (files) => onAddFiles?.(files),
        noClick: true,
        disabled: attachmentsDisabled || onAddFiles === undefined,
    });

    useEffect(() => {
        if (autoFocus) textareaRef.current?.focus();
    }, [autoFocus]);

    const resize = () => {
        const textarea = textareaRef.current;

        if (!textarea) return;

        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    };

    const hasFailed = attachmentFailed || attachments.some((attachment) => attachment.status === 'failed');
    const hasReadyImage = attachments.some(
        (attachment) => (attachment.is_image || attachment.kind === 'image') && attachment.status === 'ready',
    );
    const showVisionWarning = hasReadyImage && !isVisionModel(model);
    const blocked = streaming || disabled || uploading || hasFailed;

    const submit = () => {
        const message = value.trim();

        if (message === '' || blocked) return;

        onSubmit(message);
        setValue('');
        requestAnimationFrame(resize);
    };

    const canSubmit = value.trim() !== '' && !blocked;
    const nearLimit = value.length > MAX_LENGTH - 500;

    return (
        <div
            {...getRootProps({
                className: cn(
                    'rounded-2xl border bg-card shadow-lg shadow-black/20 transition-colors focus-within:border-primary/50',
                    isDragActive ? 'border-primary/60 bg-primary/5' : 'border-border',
                    large ? 'p-3' : 'p-2',
                ),
            })}
        >
            <input
                ref={fileInputRef}
                type="file"
                multiple
                hidden
                accept={FILE_ACCEPT}
                onChange={(event) => {
                    if (event.target.files !== null && event.target.files.length > 0) {
                        onAddFiles?.(Array.from(event.target.files));
                    }

                    event.target.value = '';
                }}
            />

            {attachments.length > 0 && (
                <div className="px-1 pb-1">
                    <AttachmentChips attachments={attachments} onRemove={onRemoveAttachment} onRetry={onRetryAttachment} />
                </div>
            )}

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

            {attachmentError !== null && attachmentError !== '' && (
                <div className="flex items-center justify-between gap-2 px-2 pt-1" role="alert">
                    <p className="text-xs text-destructive">{attachmentError}</p>

                    {onDismissAttachmentError !== undefined && (
                        <button
                            type="button"
                            onClick={onDismissAttachmentError}
                            className="shrink-0 text-[10px] text-muted-foreground hover:text-foreground"
                        >
                            Descartar
                        </button>
                    )}
                </div>
            )}

            {showVisionWarning && (
                <p className="px-2 pt-1 text-xs text-muted-foreground">El modelo seleccionado podría no soportar imágenes.</p>
            )}

            <div className="flex items-center justify-between gap-2 px-1 pt-1">
                <div className="flex items-center gap-1">
                    {onAddFiles !== undefined && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={attachmentsDisabled}
                            aria-label="Adjuntar archivos"
                            className="h-8 w-8 text-muted-foreground hover:text-foreground"
                        >
                            <Paperclip className="h-4 w-4" />
                        </Button>
                    )}
                    {onAgentChange && (
                        <AgentPicker
                            agents={agents}
                            value={agent}
                            onChange={onAgentChange}
                            disabled={disabled || streaming}
                        />
                    )}
                    <ModelPicker models={models} value={model} onChange={onModelChange} disabled={disabled || streaming} />
                    {onToolsPolicyChange && toolsPolicy && toolGroups.length > 0 && (
                        <ToolsPicker
                            groups={toolGroups}
                            policy={toolsPolicy}
                            onChange={onToolsPolicyChange}
                            disabled={disabled || streaming}
                        />
                    )}
                    {sourceMode !== undefined && (onSourceModeChange !== undefined || onForceWebChange !== undefined) && (
                        <SourceModeMenu
                            mode={sourceMode}
                            forceWeb={forceWeb}
                            onChangeMode={onSourceModeChange}
                            onChangeForceWeb={onForceWebChange}
                            hasTavilyKey={hasTavilyKey}
                            disabled={disabled || streaming}
                        />
                    )}
                </div>

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
