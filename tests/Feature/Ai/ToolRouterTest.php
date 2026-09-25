<?php

use App\Ai\Tools\ToolRouter;

it('routes task questions to the tasks group', function () {
    expect(ToolRouter::route('¿Qué tareas tengo pendientes?'))->toBe(['tasks'])
        ->and(ToolRouter::route('¿Qué VENCE esta semana?'))->toBe(['tasks']);
});

it('routes domain keywords', function () {
    expect(ToolRouter::route('¿Cuánto gasté este mes?'))->toContain('finance')
        ->and(ToolRouter::route('¿Qué comí ayer?'))->toContain('nutrition')
        ->and(ToolRouter::route('¿Entrené esta semana?'))->toContain('workout')
        ->and(ToolRouter::route('¿Qué me falta comprar en el súper?'))->toContain('grocery')
        ->and(ToolRouter::route('Reiniciá el contenedor de docker'))->toContain('integrations')
        ->and(ToolRouter::route('¿Qué agentes tengo programados?'))->toContain('agents');
});

it('adds actions when the message contains a write verb', function () {
    expect(ToolRouter::route('Creá una tarea para mañana'))->toContain('tasks', 'actions')
        ->and(ToolRouter::route('Registrá un gasto de 5000'))->toContain('finance', 'actions')
        ->and(ToolRouter::route('Anotá que entrené pecho'))->toContain('workout', 'actions');
});

it('routes project creation and task moves to the action tools', function () {
    expect(ToolRouter::route('Creá un proyecto para el cliente'))->toContain('actions')
        ->and(ToolRouter::route('Moveme la tarea al proyecto Rediseño'))->toContain('tasks', 'actions')
        ->and(ToolRouter::route('Asigná esta tarea al proyecto nuevo'))->toContain('tasks', 'actions');
});

it('falls back to the cheap default set', function () {
    expect(ToolRouter::route('Hola, ¿cómo estás?'))->toBe(['tasks', 'integrations'])
        ->and(ToolRouter::route('Contame algo interesante'))->not->toContain('actions', 'agents');
});
