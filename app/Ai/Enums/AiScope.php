<?php

declare(strict_types=1);

namespace App\Ai\Enums;

enum AiScope: string
{
    case Global = 'global';
    case SurfaceChat = 'surface:chat';
    case SurfaceAgents = 'surface:agents';
    case SurfaceInsights = 'surface:insights';
    case SurfaceFeed = 'surface:feed';
    case SurfaceEmbeddings = 'surface:embeddings';
    case SurfaceFiles = 'surface:files';
    case ModuleGym = 'module:gym';
    case ModuleNutrition = 'module:nutrition';
    case ModuleGrocery = 'module:grocery';
    case ModuleFinance = 'module:finance';
    case ModuleFreelance = 'module:freelance';
    case ModuleHealth = 'module:health';
    case ModulePeople = 'module:people';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global',
            self::SurfaceChat => 'Chat',
            self::SurfaceAgents => 'Agentes',
            self::SurfaceInsights => 'Insights',
            self::SurfaceFeed => 'Feed',
            self::SurfaceEmbeddings => 'Embeddings',
            self::SurfaceFiles => 'Archivos (visión/OCR)',
            self::ModuleGym => 'Gimnasio',
            self::ModuleNutrition => 'Nutrición',
            self::ModuleGrocery => 'Grocery',
            self::ModuleFinance => 'Finanzas',
            self::ModuleFreelance => 'Freelance',
            self::ModuleHealth => 'Salud',
            self::ModulePeople => 'Personas',
        };
    }

    /** @return 'global'|'surface'|'module' */
    public function section(): string
    {
        return match ($this) {
            self::Global => 'global',
            self::SurfaceChat, self::SurfaceAgents, self::SurfaceInsights, self::SurfaceFeed, self::SurfaceEmbeddings, self::SurfaceFiles => 'surface',
            self::ModuleGym, self::ModuleNutrition, self::ModuleGrocery, self::ModuleFinance, self::ModuleFreelance, self::ModuleHealth, self::ModulePeople => 'module',
        };
    }

    /** Clave del módulo para scopes `module:*`; `null` en los demás. */
    public function moduleKey(): ?string
    {
        return $this->section() === 'module'
            ? substr($this->value, strlen('module:'))
            : null;
    }

    public static function fromModuleKey(string $key): self
    {
        return self::from('module:'.$key);
    }

    /**
     * Los keys de módulo (`gym`, `nutrition`, …) en orden de declaración.
     *
     * @return array<int, string>
     */
    public static function moduleKeys(): array
    {
        $keys = [];

        foreach (self::cases() as $case) {
            if ($case->moduleKey() !== null) {
                $keys[] = $case->moduleKey();
            }
        }

        return $keys;
    }

    /**
     * Los tres chips de sugerencia del empty state del asistente de cada
     * módulo, en el orden en que se muestran. Un módulo desconocido (o una
     * superficie que no es módulo) devuelve `[]` y el frontend no dibuja
     * chips en vez de inventar textos.
     *
     * `people` usa el chip genérico de contactos: el nombre de una persona
     * concreta depende del usuario, y este mapa es estático por módulo (no
     * recibe ni el usuario ni su agenda).
     *
     * @return array<int, string>
     */
    public static function moduleSuggestions(string $module): array
    {
        return match ($module) {
            'gym' => ['¿Cómo va mi semana?', 'Sugerí un ejercicio para pecho', 'Compará mi último PR'],
            'nutrition' => ['¿Cómo voy en proteína?', 'Armame un menú para mañana', '¿Qué comí esta semana?'],
            'grocery' => ['¿Qué me falta comprar?', 'Armame la lista del súper', '¿Venció algo?'],
            'finance' => ['¿Cómo voy este mes?', '¿Cuánto gasté en ocio?', '¿Qué deudas tengo?'],
            'freelance' => ['¿Qué proyectos tengo activos?', 'Redactá una cotización', '¿Qué tareas vencen?'],
            'health' => ['¿Cómo están mis mediciones?', '¿Qué medicamento me queda?', 'Resumí mis síntomas'],
            'people' => ['¿Con quién hablé hace días?', '¿Qué fechas tengo próximas?', '¿Quiénes son mis contactos frecuentes?'],
            default => [],
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
