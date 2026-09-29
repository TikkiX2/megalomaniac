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

it('routes colloquial write verbs to the action tools', function () {
    expect(ToolRouter::route('dale, conjunta con la de triaje de cajas de taller, de paso arregla la desc'))->toContain('actions')
        ->and(ToolRouter::route('corregí el título de la tarea'))->toContain('actions')
        ->and(ToolRouter::route('eliminá la tarea vieja'))->toContain('actions')
        ->and(ToolRouter::route('ordená las tareas por prioridad'))->toContain('actions')
        ->and(ToolRouter::route('renombrá el proyecto'))->toContain('actions');
});

it('falls back to data groups for ambiguous messages', function () {
    expect(ToolRouter::route('Hola, ¿cómo estás?'))
        ->toBe(['tasks', 'workout', 'finance', 'nutrition', 'grocery', 'supplements', 'freelance', 'integrations', 'skills'])
        ->and(ToolRouter::route('Contame algo interesante'))->not->toContain('actions', 'agents');
});

it('routes common imperatives and english verbs to actions', function (string $message) {
    expect(ToolRouter::route($message))->toContain('actions');
})->with([
    'paga la deuda',
    'apunta que gasté 5000',
    'archiva el proyecto viejo',
    'agenda una tarea para mañana',
    'termina la tarea',
    'duplica el proyecto',
    'pospon la reunión',
    'cancela la tarea',
    'abona 100 a la deuda',
    'retira de la reserva',
    'compra leche',
    'muevo la tarea a otro proyecto',
    'create a task for tomorrow',
    'update my project',
    'delete the old task',
    'archive the project',
    'add a purchase',
    'pay the debt',
]);

it('routes body-part, finance, supplement and client vocabulary to module groups', function (string $message, string $group) {
    expect(ToolRouter::route($message))->toContain($group);
})->with([
    ['hice pecho y espalda hoy', 'workout'],
    ['cómo va mi cardio', 'workout'],
    ['cuánto pagué este mes', 'finance'],
    ['cuánto tengo en la reserva', 'finance'],
    ['qué suplementos tomo', 'supplements'],
    ['qué me falta en la tienda', 'grocery'],
    ['qué clientes tengo', 'tasks'],
    ['qué cotizaciones tengo pendientes', 'tasks'],
]);

it('routes gym messages to the workout group with its own write tool', function () {
    expect(ToolRouter::route('¿Cómo viene mi entrenamiento?'))->toContain('workout')
        ->and(ToolRouter::route('Escribí mi entrenamiento de pecho'))->toContain('workout')
        ->and(ToolRouter::route('Anadí press banca a mi rutina'))->toContain('workout')
        ->and(ToolRouter::route('Hazme una rutina de piernas'))->toContain('workout');
});
