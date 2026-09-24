import type { Plugin } from 'unified';

interface MarkdownNode {
    type: string;
    value?: string;
    url?: string;
    children?: MarkdownNode[];
}

function citationNodes(value: string, maxIndex: number): MarkdownNode[] {
    const nodes: MarkdownNode[] = [];
    const pattern = /\[(\d+)\]/g;
    let lastIndex = 0;

    for (const match of value.matchAll(pattern)) {
        const index = Number(match[1]);
        const position = match.index ?? 0;

        if (index < 1 || index > maxIndex) continue;

        if (position > lastIndex) {
            nodes.push({ type: 'text', value: value.slice(lastIndex, position) });
        }

        nodes.push({
            type: 'link',
            url: `#cite-${index}`,
            children: [{ type: 'text', value: match[0] }],
        });

        lastIndex = position + match[0].length;
    }

    if (lastIndex < value.length) {
        nodes.push({ type: 'text', value: value.slice(lastIndex) });
    }

    return nodes;
}

function walk(node: MarkdownNode, maxIndex: number): void {
    if (!node.children) return;

    const next: MarkdownNode[] = [];

    for (const child of node.children) {
        if (child.type === 'text' && typeof child.value === 'string' && /\[\d+\]/.test(child.value)) {
            next.push(...citationNodes(child.value, maxIndex));
        } else {
            walk(child, maxIndex);
            next.push(child);
        }
    }

    node.children = next;
}

export const remarkCitations: Plugin<[{ max: number }]> = (options) => (tree) => {
    walk(tree as unknown as MarkdownNode, options.max);
};
