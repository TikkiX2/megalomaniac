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
            'hito', 'milestone', 'cliente', 'clientes', 'cotiza', 'quote',
            'entrega', 'recordatorio', 'subtarea', 'tablero', 'columna',
            'comentario', 'archivado',
        ],
        'workout' => [
            'entren', 'ejercicio', 'rutina', 'serie', 'repeticion', 'press',
            'sentadilla', 'gym', 'workout', 'peso muerto', 'pesa', 'banca',
            'dominada', 'curl', 'pecho', 'espalda', 'pierna', 'hombro',
            'biceps', 'triceps', 'gluteo', 'abdominal', 'cardio', 'correr',
            'caminar', 'plancha', 'remo', 'jalon', 'reps', 'rpe', 'record',
            'progreso', 'volumen', 'fuerza', 'hipertrofia', 'peso corporal',
        ],
        'finance' => [
            'gasto', 'gaste', 'gasta', 'plata', 'dinero', 'presupuesto', 'deuda',
            'tarjeta', 'ingreso', 'cobro', 'cobra', 'factura', 'ahorro', 'finanz',
            'sueldo', 'saldo', 'pago', 'pague', 'retiro', 'extraccion', 'reserva',
            'inversion', 'cripto', 'moneda', 'divisa', 'credito', 'prestamo',
            'cuota', 'suscripcion', 'balance', 'impuesto', 'comision',
            'transferencia',
        ],
        'nutrition' => [
            'comida', 'comi', 'caloria', 'proteina', 'macro', 'dieta', 'almuerzo',
            'cena', 'desayuno', 'nutricion', 'comiste', 'alimento', 'merienda',
            'snack', 'batido', 'agua', 'hidratacion', 'carbo', 'carbohidrato',
            'grasa', 'kcal', 'gramos', 'ayuno', 'receta',
        ],
        'grocery' => [
            'compra', 'comprar', 'super', 'supermercado', 'stock', 'inventario',
            'grocer', 'lista de compras', 'mercado', 'tienda', 'despensa',
            'reponer', 'reposicion', 'agotado', 'abarrote', 'mandado', 'falta',
        ],
        'supplements' => [
            'suplemento', 'vitamina', 'creatina', 'pastilla', 'medicamento',
        ],
        'freelance' => [
            'cliente', 'clientes', 'cotiza', 'quote', 'freelance', 'contrato',
            'presupuesto', 'factura',
        ],
        'people' => [
            'persona', 'personas', 'contacto', 'contactos', 'amig', 'familia',
            'cumple', 'cumpleanos', 'vinculo', 'interaccion', 'hable', 'llam',
            'escribi', 'conoci', 'regalo', 'aniversario',
        ],
        'health' => [
            'salud', 'médic', 'medic', 'remedio', 'pastilla', 'síntoma', 'sintoma',
            'dolor', 'presión', 'presion', 'glucosa', 'análisis', 'analisis',
            'estudio', 'laboratorio', 'doctor', 'doctora', 'turno', 'consulta',
            'tsh', 'tiroides', 'peso', 'orina', 'calambre', 'cansancio',
        ],
        'integrations' => [
            'github', 'docker', 'proxmox', 'servidor', 'contenedor', 'reddit',
            'telegram', 'notion', 'youtube', 'feed', 'drive', 's3', 'storage',
            'archivo', 'descarga', 'subi', 'repositorio', 'issue', 'pull request',
            'home assistant', 'jellyfin', 'sonarr', 'radarr',
        ],
        'agents' => [
            'agente', 'schedule', 'programa', 'automatiza', 'cada dia',
            'background', 'cada hora', 'cada semana', 'cron', 'monitor',
            'vigila', 'diario', 'recordatorio',
        ],
        'web' => [
            'busca', 'buscar', 'busqueda', 'internet', 'web', 'noticia', 'google',
            'ultima hora', 'actualidad', 'en linea', 'online', 'en la red',
            'clima', 'traduce', 'wiki', 'documentacion', 'investiga', 'precio',
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
        'anotar', 'loguea', 'loguear', 'guarda', 'guardar', 'anad', 'suma',
        'sumar', 'marca', 'marcar', 'actualiza', 'actualizar', 'completa',
        'completar', 'borra', 'borrar', 'modifica', 'modificar', 'cambia',
        'cambiar', 'mov', 'mueve', 'mueva', 'muevo', 'mover', 'asigna',
        'asignar', 'reasigna', 'reasignar', 'traslada', 'trasladar', 'arregl',
        'corrig', 'correg', 'edita', 'editar', 'renombr', 'conjunta', 'fusiona',
        'fusionar', 'combina', 'combinar', 'unir', 'elimina', 'eliminar',
        'quita', 'quitar', 'ordena', 'ordenar', 'reordena', 'reordenar',
        'prioriza', 'priorizar', 'cierra', 'cerrar', 'reabre', 'reabrir',
        'desmarca', 'desmarcar', 'escrib', 'ingres', 'carg', 'hazme', 'prepara',
        'paga', 'pago', 'pague', 'pagar', 'abona', 'abonar', 'deposita',
        'depositar', 'retir', 'transfi', 'transfer', 'apunta', 'apuntar',
        'archiva', 'archivar', 'agenda', 'agendar', 'pone', 'poner', 'termina',
        'terminar', 'finaliza', 'finalizar', 'duplica', 'duplicar', 'copia',
        'copiar', 'pospon', 'postpon', 'aplaza', 'aplazar', 'reprograma',
        'reprogramar', 'cancela', 'cancelar', 'envia', 'enviar', 'vacia',
        'vaciar', 'compra',
        'create', 'add', 'update', 'edit', 'delete', 'remove', 'log', 'pay',
        'archive', 'move', 'rename', 'assign', 'schedule', 'complete',
        'finish', 'mark', 'close', 'reopen', 'merge', 'duplicate', 'cancel',
        'reschedule', 'buy', 'send',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback groups
    |--------------------------------------------------------------------------
    |
    | Groups used when the router finds no clear match. They are data modules
    | in read mode plus the cheap integrations/skills sets; the "actions"
    | group is only added when a write verb is present.
    |
    */

    'fallback' => ['tasks', 'workout', 'finance', 'nutrition', 'grocery', 'supplements', 'freelance', 'people', 'health', 'integrations', 'skills'],

];
