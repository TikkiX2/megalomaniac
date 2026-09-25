<?php

use App\Ai\Support\MarkdownToYoopta;

it('converts paragraphs to yoopta blocks', function () {
    $blocks = MarkdownToYoopta::convert("Primer párrafo.\n\nSegundo párrafo.");

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['children'][0]['text'])->toBe('Primer párrafo.')
        ->and($blocks[1]['children'][0]['text'])->toBe('Segundo párrafo.');
});

it('converts headings and bullets', function () {
    $blocks = MarkdownToYoopta::convert("# Título\n- Uno\n- Dos");

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]['type'])->toBe('heading')
        ->and($blocks[0]['children'][0]['text'])->toBe('Título')
        ->and($blocks[1]['type'])->toBe('bulleted-list')
        ->and($blocks[1]['children'][0]['text'])->toBe('Uno')
        ->and($blocks[2]['children'][0]['text'])->toBe('Dos');
});

it('converts numbered lists', function () {
    $blocks = MarkdownToYoopta::convert("1. Primero\n2. Segundo");

    expect($blocks[0]['type'])->toBe('numbered-list')
        ->and($blocks[0]['children'][0]['text'])->toBe('Primero');
});

it('returns empty array for blank input', function () {
    expect(MarkdownToYoopta::convert("  \n "))->toBe([]);
});
