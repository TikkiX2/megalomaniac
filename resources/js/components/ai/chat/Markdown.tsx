import type { ComponentPropsWithoutRef } from 'react';
import ReactMarkdown from 'react-markdown';
import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';
import { CitationChip } from '@/components/ai/chat/CitationChip';
import { CodeBlock } from '@/components/ai/chat/CodeBlock';
import { remarkCitations } from '@/lib/remark-citations';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface MarkdownProps {
    content: string;
    citations?: Citation[];
    className?: string;
    onCitationClick?: (index: number) => void;
}

export function Markdown({ content, citations = [], className, onCitationClick }: MarkdownProps) {
    return (
        <div className={cn('text-sm leading-relaxed text-foreground', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm, [remarkCitations, { max: citations.length }]]}
                rehypePlugins={[rehypeHighlight]}
                components={{
                    p: ({ children }) => <p className="my-2 first:mt-0 last:mb-0">{children}</p>,
                    h1: ({ children }) => <h1 className="mt-4 mb-2 text-lg font-black tracking-tight">{children}</h1>,
                    h2: ({ children }) => <h2 className="mt-4 mb-2 text-base font-black tracking-tight">{children}</h2>,
                    h3: ({ children }) => <h3 className="mt-3 mb-1.5 text-sm font-bold tracking-tight">{children}</h3>,
                    ul: ({ children }) => <ul className="my-2 list-disc space-y-1 pl-5">{children}</ul>,
                    ol: ({ children }) => <ol className="my-2 list-decimal space-y-1 pl-5">{children}</ol>,
                    li: ({ children }) => <li className="marker:text-primary/70">{children}</li>,
                    blockquote: ({ children }) => (
                        <blockquote className="my-3 border-l-2 border-primary/50 pl-3 text-muted-foreground">
                            {children}
                        </blockquote>
                    ),
                    hr: () => <hr className="my-4 border-border" />,
                    table: ({ children }) => (
                        <div className="my-3 overflow-x-auto rounded-lg border border-border">
                            <table className="w-full border-collapse text-left text-xs">{children}</table>
                        </div>
                    ),
                    th: ({ children }) => (
                        <th className="border-b border-border bg-muted/40 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-muted-foreground">
                            {children}
                        </th>
                    ),
                    td: ({ children }) => <td className="border-b border-border/60 px-3 py-2 align-top">{children}</td>,
                    a: ({ href, children }) => {
                        const citationMatch = href?.match(/^#cite-(\d+)$/);

                        if (citationMatch) {
                            const index = Number(citationMatch[1]);

                            return (
                                <CitationChip
                                    index={index}
                                    citation={citations[index - 1]}
                                    onClick={() => onCitationClick?.(index)}
                                />
                            );
                        }

                        return (
                            <a
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-primary underline decoration-primary/40 underline-offset-2 hover:decoration-primary"
                            >
                                {children}
                            </a>
                        );
                    },
                    code: ({ className: codeClassName, children, ...props }: ComponentPropsWithoutRef<'code'>) => {
                        if (/language-/.test(codeClassName ?? '')) {
                            return (
                                <code className={codeClassName} {...props}>
                                    {children}
                                </code>
                            );
                        }

                        return (
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.8em] text-primary" {...props}>
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => <CodeBlock>{children}</CodeBlock>,
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
