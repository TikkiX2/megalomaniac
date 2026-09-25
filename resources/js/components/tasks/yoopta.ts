import type { YooptaBlock } from '@/types/personal';

export function yooptaToText(blocks: YooptaBlock[] | null | undefined): string {
    if (!Array.isArray(blocks)) {
        return '';
    }

    return blocks
        .map((block) =>
            Array.isArray(block.children)
                ? block.children.map((child) => String(child.text ?? '')).join(' ')
                : '',
        )
        .filter(Boolean)
        .join(' ')
        .trim();
}
