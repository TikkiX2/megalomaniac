<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Keyword routing per tool group
    |--------------------------------------------------------------------------
    |
    | The ToolRouter matches the user message against these stems (accent and
    | case insensitive) to decide which tool groups the chat agent receives.
    |
    */

    'keywords' => [
        'tasks' => [
            'tarea', 'backlog', 'pendiente', 'vence', 'vencimiento', 'plazo',
            'deadline', 'kanban', 'por hacer', 'proyecto', 'proyectos',
        ],
        'workout' => [
            'entren', 'ejercicio', 'rutina', 'serie', 'repeticion', 'press',
            'sentadilla', 'gym', 'workout', 'peso muerto',
        ],
        'finance' => [
            'gasto', 'gaste', 'plata', 'dinero', 'presupuesto', 'deuda', 'tarjeta',
            'ingreso', 'cobro', 'factura', 'ahorro', 'finanz', 'sueldo', 'saldo',
        ],
        'nutrition' => [
            'comida', 'comi', 'caloria', 'proteina', 'macro', 'dieta', 'almuerzo',
            'cena', 'desayuno', 'nutricion', 'comiste', 'alimento',
        ],
        'grocery' => [
            'compra', 'comprar', 'super', 'supermercado', 'stock', 'inventario',
            'grocer', 'lista de compras',
        ],
        'integrations' => [
            'github', 'docker', 'proxmox', 'servidor', 'contenedor', 'reddit',
            'telegram', 'notion', 'youtube', 'feed', 'drive', 's3', 'storage',
            'archivo', 'descarga', 'subi', 'repositorio', 'issue', 'pull request',
            'home assistant', 'jellyfin', 'sonarr', 'radarr',
        ],
        'agents' => [
            'agente', 'schedule', 'programa', 'automatiza', 'cada dia',
            'background', 'cada hora', 'cada semana',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Write verbs
    |--------------------------------------------------------------------------
    |
    | When one of these appears, the "actions" group is added so the agent can
    | create or update records.
    |
    */

    'write_verbs' => [
        'crea', 'crear', 'agrega', 'agregar', 'registra', 'registrar', 'anota',
        'anotar', 'loguea', 'loguear', 'guarda', 'guardar', 'anadi', 'suma',
        'sumar', 'marca', 'marcar', 'actualiza', 'actualizar', 'completa',
        'completar', 'borra', 'borrar', 'modifica', 'modificar', 'cambia',
        'cambiar', 'mov', 'mueve', 'mueva', 'asigna', 'asignar', 'reasigna',
        'reasignar', 'traslada', 'trasladar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback groups
    |--------------------------------------------------------------------------
    |
    | Cheap, commonly useful groups used when the router finds no clear match.
    |
    */

    'fallback' => ['tasks', 'integrations'],

];
