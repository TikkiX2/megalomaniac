<?php

use App\Integrations\Actions\Param;
use App\Integrations\Actions\ParamRules;

it('maps params to validation rules', function () {
    $rules = ParamRules::forParams([
        new Param('owner', 'string', true, 'Dueño'),
        new Param('count', 'integer', false, 'Cantidad', default: 10),
        new Param('dry_run', 'boolean', false, 'Simular'),
        new Param('labels', 'array', false, 'Etiquetas'),
        new Param('state', 'string', false, 'Estado', enum: ['open', 'closed']),
    ]);

    expect($rules['owner'])->toBe(['required', 'string'])
        ->and($rules['count'])->toBe(['nullable', 'integer'])
        ->and($rules['dry_run'])->toBe(['nullable', 'boolean'])
        ->and($rules['labels'])->toBe(['nullable', 'array'])
        ->and($rules['state'])->toBe(['nullable', 'string', 'in:open,closed']);
});

it('maps params to human labels', function () {
    $labels = ParamRules::labels([
        new Param('owner', 'string', true, 'Dueño del repo'),
    ]);

    expect($labels['owner'])->toBe('Dueño del repo');
});
