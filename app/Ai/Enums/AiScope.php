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
            self::SurfaceChat, self::SurfaceAgents, self::SurfaceInsights, self::SurfaceFeed, self::SurfaceEmbeddings => 'surface',
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

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
