import ActionMenu, { DefaultActionMenuRender } from '@yoopta/action-menu-list';
import Blockquote from '@yoopta/blockquote';
import Code from '@yoopta/code';
import YooptaEditor, { createYooptaEditor } from '@yoopta/editor';
import Embed from '@yoopta/embed';
import File from '@yoopta/file';
import Headings from '@yoopta/headings';
import Image from '@yoopta/image';
import Link from '@yoopta/link';
import Lists from '@yoopta/lists';
import Paragraph from '@yoopta/paragraph';
import Toolbar, { DefaultToolbarRender } from '@yoopta/toolbar';
import React, { useMemo, type ComponentProps } from 'react';
import { normalizeYooptaValue } from '@/components/tasks/yoopta';
import type { YooptaBlock, YooptaValue } from '@/types/personal';

// Styles - Yoopta v4 is headless, but we can add custom styles here if needed.
// For now, removing invalid imports.

const plugins = [
    Paragraph,
    ...Object.values(Headings),
    ...Object.values(Lists),
    Blockquote,
    Code,
    Link,
    Image,
    Embed,
    File,
];

const TOOLS = {
    ActionMenu: {
        render: DefaultActionMenuRender,
        tool: ActionMenu,
    },
    Toolbar: {
        render: DefaultToolbarRender,
        tool: Toolbar,
    },
};

type EditorValue = ComponentProps<typeof YooptaEditor>['value'];
type EditorOnChange = NonNullable<ComponentProps<typeof YooptaEditor>['onChange']>;

interface RichTextEditorProps {
    value?: YooptaValue;
    onChange?: (value: YooptaValue) => void;
    readOnly?: boolean;
    className?: string;
}

/**
 * Accepts Yoopta v4 block maps, legacy block lists and plain strings, and
 * returns a v4 block map with text nodes never null (React requires a node).
 */
function sanitizeYooptaValue(value: YooptaValue | undefined): Record<string, YooptaBlock> | undefined {
    const normalized = normalizeYooptaValue(value);

    if (!normalized) return undefined;

    return Object.fromEntries(
        Object.entries(normalized).map(([id, block]) => [
            id,
            {
                ...block,
                value: Array.isArray(block.value)
                    ? block.value.map((element) => ({
                          ...element,
                          children: Array.isArray(element.children)
                              ? element.children.map((child) => ({
                                    ...child,
                                    text: child.text ?? '',
                                }))
                              : element.children,
                      }))
                    : block.value,
            },
        ]),
    );
}

export default function RichTextEditor({ value, onChange, readOnly = false, className }: RichTextEditorProps) {
    const editor = useMemo(() => createYooptaEditor(), []);

    const sanitizedValue = useMemo(() => sanitizeYooptaValue(value), [value]);

    return (
        <div className={`yoopta-wrapper border rounded-md p-2 ${className}`}>
            <YooptaEditor
                editor={editor}
                plugins={plugins}
                tools={TOOLS}
                readOnly={readOnly}
                value={sanitizedValue as EditorValue}
                onChange={onChange as EditorOnChange}
                placeholder="Escribe aquí..."
                width="100%"
            />
        </div>
    );
}
