<?php

use App\Ai\Support\MarkdownToYoopta;

/**
 * @param  array<string, array<string, mixed>>  $blocks
 * @return array<int, array<string, mixed>>
 */
function orderedBlocks(array $blocks): array
{
    return collect($blocks)->sortBy(fn (array $block): int => $block['meta']['order'] ?? 0)->values()->all();
}

it('converts paragraphs to yoopta v4 blocks', function () {
    $blocks = orderedBlocks(MarkdownToYoopta::convert("Primer párrafo.\n\nSegundo párrafo."));

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('Paragraph')
        ->and($blocks[0]['value'][0]['type'])->toBe('paragraph')
        ->and($blocks[0]['value'][0]['children'][0]['text'])->toBe('Primer párrafo.')
        ->and($blocks[0]['meta']['order'])->toBe(0)
        ->and($blocks[1]['value'][0]['children'][0]['text'])->toBe('Segundo párrafo.')
        ->and($blocks[1]['meta']['order'])->toBe(1);
});

it('keys blocks by their id', function () {
    $blocks = MarkdownToYoopta::convert('Hola');

    $id = array_key_first($blocks);

    expect($id)->toBeString()
        ->and($blocks[$id]['id'])->toBe($id);
});

it('converts headings and bullets with their v4 types', function () {
    $blocks = orderedBlocks(MarkdownToYoopta::convert("# Título\n- Uno\n- Dos"));

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]['type'])->toBe('HeadingOne')
        ->and($blocks[0]['value'][0]['type'])->toBe('heading-one')
        ->and($blocks[0]['value'][0]['children'][0]['text'])->toBe('Título')
        ->and($blocks[1]['type'])->toBe('BulletedList')
        ->and($blocks[1]['value'][0]['type'])->toBe('bulleted-list')
        ->and($blocks[1]['value'][0]['children'][0]['text'])->toBe('Uno')
        ->and($blocks[2]['value'][0]['children'][0]['text'])->toBe('Dos');
});

it('maps heading levels to their yoopta types', function () {
    $blocks = orderedBlocks(MarkdownToYoopta::convert("## Dos\n### Tres\n#### Cuatro"));

    expect($blocks[0]['type'])->toBe('HeadingTwo')
        ->and($blocks[0]['value'][0]['type'])->toBe('heading-two')
        ->and($blocks[1]['type'])->toBe('HeadingThree')
        ->and($blocks[1]['value'][0]['type'])->toBe('heading-three')
        ->and($blocks[2]['type'])->toBe('HeadingThree');
});

it('converts numbered lists', function () {
    $blocks = orderedBlocks(MarkdownToYoopta::convert("1. Primero\n2. Segundo"));

    expect($blocks[0]['type'])->toBe('NumberedList')
        ->and($blocks[0]['value'][0]['type'])->toBe('numbered-list')
        ->and($blocks[0]['value'][0]['children'][0]['text'])->toBe('Primero');
});

it('returns empty array for blank input', function () {
    expect(MarkdownToYoopta::convert("  \n "))->toBe([]);
});
