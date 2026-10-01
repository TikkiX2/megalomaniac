import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';

/**
 * Los siete módulos con asistente propio. El key es el que viaja en la URL
 * (`/ai/{module}`) y el que la columna `chat_threads.module` guarda; la
 * etiqueta es el nombre en castellano que ve la persona.
 */
export const AI_MODULE_LABELS: Record<string, string> = {
    gym: 'Gimnasio',
    nutrition: 'Nutrición',
    grocery: 'Grocery',
    finance: 'Finanzas',
    freelance: 'Freelance',
    health: 'Salud',
    people: 'Personas',
};

/** Etiqueta de un módulo; `null` en el chat general o ante un key desconocido. */
export function aiModuleLabel(module: string | null | undefined): string | null {
    if (module === null || module === undefined || module === '') return null;

    return AI_MODULE_LABELS[module] ?? null;
}

/** URL del asistente de un módulo, vía Wayfinder (`ai.module`). */
export function aiModuleUrl(module: string): string {
    return ChatController.module.url(module);
}