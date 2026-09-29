import type { YooptaBlock, YooptaElement, YooptaValue } from '@/types/personal';

/**
 * Text nodes live under `block.value[]` (Yoopta v4) or directly under
 * `block.children` (legacy/AI-generated blocks). Collect both.
 */
function blockTextNodes(block: YooptaBlock): Array<{ text?: string | null }> {
    const nodes: Array<{ text?: string | null }> = [];

    if (Array.isArray(block.value)) {
        for (const element of block.value) {
            if (Array.isArray(element?.children)) {
                nodes.push(...element.children);
            }
        }
    }

    if (Array.isArray(block.children)) {
        nodes.push(...block.children);
    }

    return nodes;
}

function blockOrder(block: YooptaBlock): number {
    const order = block.meta?.order;

    return typeof order === 'number' ? order : 0;
}

function orderedBlocks(value: YooptaBlock[] | Record<string, YooptaBlock>): YooptaBlock[] {
    const blocks = Array.isArray(value) ? [...value] : Object.values(value);

    return blocks
        .filter((block): block is YooptaBlock => block !== null && typeof block === 'object')
        .sort((left, right) => blockOrder(left) - blockOrder(right));
}

function stripHtml(value: string): string {
    return value
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function yooptaToText(value: YooptaValue | undefined): string {
    if (typeof value === 'string') {
        return stripHtml(value);
    }

    if (value === null || value === undefined) {
        return '';
    }

    return orderedBlocks(value)
        .map((block) => blockTextNodes(block).map((node) => String(node.text ?? '')).join(' '))
        .filter((text) => text.trim() !== '')
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();
}

const LEGACY_BLOCK_TYPES: Record<string, { block: string; element: string }> = {
    paragraph: { block: 'Paragraph', element: 'paragraph' },
    heading: { block: 'HeadingOne', element: 'heading-one' },
    'bulleted-list': { block: 'BulletedList', element: 'bulleted-list' },
    'numbered-list': { block: 'NumberedList', element: 'numbered-list' },
};

function newBlockId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `block-${Math.random().toString(36).slice(2)}`;
}

function buildParagraphBlock(text: string, order: number): YooptaBlock {
    return {
        id: newBlockId(),
        type: 'Paragraph',
        value: [
            {
                id: newBlockId(),
                type: 'paragraph',
                children: [{ text }],
            },
        ],
        meta: { align: 'left', depth: 0, order },
    };
}

function buildBlockFromLegacy(legacy: YooptaBlock, order: number): YooptaBlock {
    const mapping = LEGACY_BLOCK_TYPES[legacy.type] ?? LEGACY_BLOCK_TYPES.paragraph;
    const text = blockTextNodes(legacy)
        .map((node) => String(node.text ?? ''))
        .join(' ')
        .trim();

    return {
        id: legacy.id ?? newBlockId(),
        type: mapping.block,
        value: [
            {
                id: newBlockId(),
                type: mapping.element,
                children: [{ text }],
            },
        ],
        meta: { align: 'left', depth: 0, order },
    };
}

/**
 * Normalize any stored description into the Yoopta v4 value shape
 * (`Record<blockId, blockData>`) the editor expects. Strings and legacy
 * block lists are converted to paragraph/heading blocks.
 */
export function normalizeYooptaValue(value: YooptaValue | undefined): Record<string, YooptaBlock> | undefined {
    if (value === null || value === undefined) {
        return undefined;
    }

    if (typeof value === 'string') {
        const text = stripHtml(value);

        if (text === '') {
            return undefined;
        }

        const block = buildParagraphBlock(text, 0);

        return { [block.id]: block };
    }

    if (!Array.isArray(value)) {
        // Already a block map — but guard against a single block object.
        if (typeof value === 'object' && 'id' in value && 'type' in value) {
            const block = value as unknown as YooptaBlock;

            return { [block.id]: block };
        }

        // Legacy wrapper: { id, value: [ ...legacy blocks ] }
        const legacy = (value as { value?: unknown }).value;

        if (Array.isArray(legacy) && legacy.some((item) => item !== null && typeof item === 'object')) {
            return normalizeYooptaValue(legacy as YooptaValue);
        }

        return Object.keys(value).length > 0 ? value : undefined;
    }

    if (value.length === 0) {
        return undefined;
    }

    const normalized: Record<string, YooptaBlock> = {};

    value.forEach((block, index) => {
        const isV4 = Array.isArray(block.value) && block.value.length > 0;
        const converted = isV4 ? block : buildBlockFromLegacy(block, index);
        converted.meta = { ...(converted.meta ?? {}), order: converted.meta?.order ?? index };

        normalized[converted.id] = converted;
    });

    return Object.keys(normalized).length > 0 ? normalized : undefined;
}

export type { YooptaBlock, YooptaElement, YooptaValue };
