<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Journal\JournalReflectionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class JournalRecapTool implements Tool
{
    private const string INVALID_WEEK = 'La semana debe ser un lunes en formato Y-m-d.';

    public function __construct(
        protected User $user,
        protected JournalReflectionService $reflections,
    ) {}

    public function description(): Stringable|string
    {
        return 'Devuelve la reflexión semanal del diario. Sólo lee memorias públicas; si la semana no tiene resumen, lo genera. No accede a memorias privadas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $weekStart = $request['week_start'] ?? null;

        if ($weekStart !== null && $weekStart !== '') {
            if (! is_string($weekStart)) {
                return self::INVALID_WEEK;
            }

            try {
                $parsed = CarbonImmutable::parse($weekStart, $this->user->localTimezone());
            } catch (Throwable) {
                return self::INVALID_WEEK;
            }

            if (! $parsed->isMonday()) {
                return self::INVALID_WEEK;
            }

            $weekStart = $parsed->toDateString();
        } else {
            $weekStart = null;
        }

        try {
            $reflection = $this->reflections->recap($this->user, $weekStart);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        if ($reflection === null) {
            return 'No hay memorias públicas en esa semana.';
        }

        return 'Semana del '.$reflection->week_start->toDateString()."\n\n".$reflection->body;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'week_start' => $schema->string()
                ->description('Lunes de la semana en formato Y-m-d (por defecto, la semana actual)'),
        ];
    }
}
