import React, { useMemo, useEffect } from 'react';
import YooptaEditor, { createYooptaEditor } from '@yoopta/editor';
import Paragraph from '@yoopta/paragraph';
import Blockquote from '@yoopta/blockquote';
import Code from '@yoopta/code';
import Embed from '@yoopta/embed';
import File from '@yoopta/file';
import Headings from '@yoopta/headings';
import Image from '@yoopta/image';
import Link from '@yoopta/link';
import Lists from '@yoopta/lists';
import ActionMenu, { DefaultActionMenuRender } from '@yoopta/action-menu-list';
import Toolbar, { DefaultToolbarRender } from '@yoopta/toolbar';

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

interface RichTextEditorProps {
    value?: any;
    onChange?: (value: any) => void;
    readOnly?: boolean;
    className?: string;
}

export default function RichTextEditor({ value, onChange, readOnly = false, className }: RichTextEditorProps) {
    const editor = useMemo(() => createYooptaEditor(), []);

    // Convert initial value if string (from DB text) to Yoopta format or keep as is if JSON behavior is correct
    // Yoopta usually handles its own format.

    useEffect(() => {
        // If value changes externally and is different, set it? 
        // Yoopta is uncontrolled mainly but can be controlled.
        // For simplicity we rely on internal state and onChange.
    }, [value]);

    return (
        <div className={`yoopta-wrapper border rounded-md p-2 ${className}`}>
            <YooptaEditor
                editor={editor}
                plugins={plugins}
                tools={TOOLS}
                readOnly={readOnly}
                value={value}
                onChange={onChange}
                placeholder="Escribe aquí..."
                width="100%"
            />
        </div>
    );
}
