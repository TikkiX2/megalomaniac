<?php

namespace App\Ai\Agents;

use App\Ai\Support\AiProviderResolver;
use App\Integrations\Support\SecretRedactor;
use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\AgentSuggestion;
use App\Models\ApprovalRequest;
use App\Models\IntegrationActionLog;
use Illuminate\Support\Str;
use Throwable;

class AgentRunner
{
    public function __construct(
        private readonly TelegramNotifier $notifier,
    ) {}

    public function run(AgentDefinition $definition, string $triggeredBy): AgentRun
    {
        $user = $definition->user;

        if (! $definition->enabled) {
            return $this->skip($definition, 'Agente deshabilitado.', $triggeredBy);
        }

        if ($definition->isRunning()) {
            return $this->skip($definition, 'Ya hay una ejecución en curso.', $triggeredBy);
        }

        if ($definition->runsToday() >= $definition->max_runs_per_day) {
            return $this->skip($definition, 'Presupuesto diario de ejecuciones alcanzado.', $triggeredBy);
        }

        [$provider, $model] = AiProviderResolver::for($user);

        if ($provider === null) {
            return $this->skip($definition, 'IA no configurada en Settings → IA.', $triggeredBy);
        }

        $context = $this->context($definition);
        $startedAt = now();

        $run = AgentRun::create([
            'agent_definition_id' => $definition->id,
            'user_id' => $user->id,
            'status' => AgentRun::STATUS_RUNNING,
            'triggered_by' => $triggeredBy,
            'started_at' => $startedAt,
            'context' => $context,
        ]);

        try {
            $response = (new RuntimeAgent($definition, $context))->prompt(
                'Generá el informe ahora.',
                provider: $provider,
                model: $model,
                timeout: 120,
            );

            $output = $this->structured($response);
            $report = SecretRedactor::redactString(trim((string) ($output['report'] ?? '')));
            $suggestions = $this->storeSuggestions($definition, $this->parseSuggestions($output['suggestions'] ?? null));

            $approvals = IntegrationActionLog::query()
                ->where('user_id', $user->id)
                ->where('source', 'schedule')
                ->where('created_at', '>=', $startedAt)
                ->count();

            $run->update([
                'status' => AgentRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'report' => $report,
                'usage' => property_exists($response, 'usage') ? $response->usage->toArray() : null,
                'suggestions_created' => $suggestions,
                'approvals_created' => $approvals,
            ]);

            $definition->forceFill([
                'last_run_at' => now(),
                'failure_count' => 0,
            ])->save();

            $notify = trim((string) ($output['notify'] ?? ''));

            if ($notify !== '' && $this->notifier->send($user, "[{$definition->name}] {$notify}")) {
                $run->update(['notified_at' => now()]);
            }

            return $run->refresh();
        } catch (Throwable $e) {
            report($e);

            $failures = $definition->failure_count + 1;

            $run->update([
                'status' => AgentRun::STATUS_FAILED,
                'finished_at' => now(),
                'error' => Str::limit($e->getMessage(), 500),
            ]);

            $definition->forceFill([
                'last_run_at' => now(),
                'failure_count' => $failures,
                'next_run_at' => $triggeredBy === 'schedule'
                    ? now()->addMinutes(min(2 ** $failures, 1440))
                    : $definition->next_run_at,
            ])->save();

            return $run->refresh();
        }
    }

    protected function skip(AgentDefinition $definition, string $reason, string $triggeredBy): AgentRun
    {
        return AgentRun::create([
            'agent_definition_id' => $definition->id,
            'user_id' => $definition->user_id,
            'status' => AgentRun::STATUS_SKIPPED,
            'triggered_by' => $triggeredBy,
            'started_at' => now(),
            'finished_at' => now(),
            'error' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function context(AgentDefinition $definition): array
    {
        $user = $definition->user;

        return [
            'last_runs' => $definition->agentRuns()
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (AgentRun $run): array => [
                    'status' => $run->status,
                    'at' => $run->created_at?->toIso8601String(),
                    'report' => Str::limit(SecretRedactor::redactString((string) $run->report), 300),
                ])
                ->all(),
            'pending_approvals' => ApprovalRequest::query()->forUser($user)->pending()->count(),
            'open_suggestions' => AgentSuggestion::query()
                ->where('user_id', $user->id)
                ->active()
                ->latest()
                ->limit(5)
                ->pluck('title')
                ->all(),
        ];
    }

    /**
     * @return array{report: string, suggestions: mixed, notify: mixed}
     */
    protected function structured(mixed $response): array
    {
        if ($response instanceof \ArrayAccess && isset($response['report'])) {
            return [
                'report' => (string) $response['report'],
                'suggestions' => $response['suggestions'] ?? null,
                'notify' => $response['notify'] ?? null,
            ];
        }

        $text = (string) $response;
        $json = $this->extractJson($text);

        return $json ?? ['report' => $text, 'suggestions' => null, 'notify' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) && isset($decoded['report']) ? $decoded : null;
    }

    /**
     * @return array<int, array{title: string, content: string}>
     */
    protected function parseSuggestions(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded)) {
            return [];
        }

        $suggestions = [];

        foreach ($decoded as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }

            $suggestions[] = [
                'title' => (string) $item['title'],
                'content' => (string) ($item['content'] ?? ''),
            ];
        }

        return array_slice($suggestions, 0, 3);
    }

    /**
     * @param  array<int, array{title: string, content: string}>  $suggestions
     */
    protected function storeSuggestions(AgentDefinition $definition, array $suggestions): int
    {
        foreach ($suggestions as $suggestion) {
            AgentSuggestion::updateOrCreate(
                [
                    'user_id' => $definition->user_id,
                    'type' => 'agent:'.$definition->key,
                    'title' => $suggestion['title'],
                ],
                [
                    'content' => $suggestion['content'],
                    'data' => ['agent' => $definition->key],
                    'dismissed_at' => null,
                ],
            );
        }

        return count($suggestions);
    }
}
