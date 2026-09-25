<?php

namespace App\Ai\Agents;

use App\Models\AgentDefinition;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentDefinitionService
{
    public const MAX_AGENTS = 10;

    public function __construct(
        private readonly AgentScheduler $scheduler,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, ?Model $createdBy = null): AgentDefinition
    {
        if (AgentDefinition::query()->forUser($user)->count() >= self::MAX_AGENTS) {
            throw ValidationException::withMessages([
                'name' => 'Límite de '.self::MAX_AGENTS.' agentes alcanzado.',
            ]);
        }

        $scheduleType = (string) ($data['schedule_type'] ?? 'interval');
        $scheduleValue = (string) ($data['schedule_value'] ?? '1h');
        $this->validateSchedule($scheduleType, $scheduleValue);

        $key = (string) ($data['key'] ?? Str::slug((string) $data['name']));

        if ($key === '' || AgentDefinition::query()->forUser($user)->where('key', $key)->exists()) {
            throw ValidationException::withMessages([
                'key' => 'Ya existe un agente con esa clave.',
            ]);
        }

        $definition = AgentDefinition::create([
            'user_id' => $user->id,
            'key' => $key,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'],
            'tools_policy' => $data['tools_policy'] ?? ['internal' => [], 'integrations' => []],
            'schedule_type' => $scheduleType,
            'schedule_value' => $scheduleValue,
            'timezone' => $data['timezone'] ?? 'UTC',
            'enabled' => $data['enabled'] ?? true,
            'max_runs_per_day' => $data['max_runs_per_day'] ?? 24,
            'max_tokens_per_run' => $data['max_tokens_per_run'] ?? 2000,
            'created_by_type' => $createdBy?->getMorphClass(),
            'created_by_id' => $createdBy?->getKey(),
        ]);

        $definition->forceFill(['next_run_at' => $this->scheduler->nextRunAt($definition)])->save();

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AgentDefinition $definition, array $data): AgentDefinition
    {
        if (isset($data['schedule_type']) || isset($data['schedule_value'])) {
            $this->validateSchedule(
                (string) ($data['schedule_type'] ?? $definition->schedule_type),
                (string) ($data['schedule_value'] ?? $definition->schedule_value),
            );
        }

        $definition->fill($data)->save();

        if (isset($data['schedule_type']) || isset($data['schedule_value'])) {
            $definition->forceFill(['next_run_at' => $this->scheduler->nextRunAt($definition)])->save();
        }

        return $definition;
    }

    public function toggle(AgentDefinition $definition, bool $enabled): AgentDefinition
    {
        $definition->forceFill([
            'enabled' => $enabled,
            'next_run_at' => $enabled
                ? $this->scheduler->nextRunAt($definition)
                : null,
        ])->save();

        return $definition;
    }

    public function delete(AgentDefinition $definition): void
    {
        $definition->delete();
    }

    /**
     * @throws ValidationException
     */
    public function validateSchedule(string $type, string $value): void
    {
        if (! in_array($type, ['interval', 'cron'], true)) {
            throw ValidationException::withMessages(['schedule_type' => 'Tipo de schedule inválido.']);
        }

        if ($type === 'interval' && ! array_key_exists($value, AgentScheduler::INTERVALS)) {
            throw ValidationException::withMessages([
                'schedule_value' => 'Intervalo inválido. Usá: '.implode(', ', array_keys(AgentScheduler::INTERVALS)).'.',
            ]);
        }

        if ($type === 'cron' && ! CronExpression::isValidExpression($value)) {
            throw ValidationException::withMessages(['schedule_value' => 'Expresión cron inválida.']);
        }
    }
}
