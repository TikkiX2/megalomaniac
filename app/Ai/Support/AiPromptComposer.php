<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Ai\Enums\AiScope;
use App\Models\User;
use App\Models\UserAiScope;

/**
 * Stacks the editable personalization layers (global → module → surface) into a
 * single markdown block that gets injected right after the agent's base
 * instructions.
 */
class AiPromptComposer
{
    /**
     * Maximum characters allowed per layer. Enforced by the settings form
     * request; the composer only documents the limit and never truncates.
     */
    public const MAX_CHARS = 8000;

    /**
     * Ordered personalization layers for the given context.
     *
     * @param  string|null  $moduleKey  Module key (`gym`, `nutrition`, …).
     * @param  string|null  $surface  Scope value of the surface, e.g. `surface:chat`.
     */
    public function personalizationBlock(User $user, ?string $moduleKey, ?string $surface): ?string
    {
        $layers = [];

        if ($moduleKey !== null) {
            $layers[] = AiScope::fromModuleKey($moduleKey);
        }

        if ($surface !== null && AiScope::tryFrom($surface) !== null) {
            $layers[] = AiScope::from($surface);
        }

        $scopes = array_merge([AiScope::Global], $layers);
        $scopeValues = array_map(fn (AiScope $scope): string => $scope->value, $scopes);

        $prompts = UserAiScope::query()
            ->where('user_id', $user->getKey())
            ->whereIn('scope', $scopeValues)
            ->pluck('prompt', 'scope');

        $sections = [];

        foreach ($scopes as $scope) {
            $prompt = trim((string) ($prompts[$scope->value] ?? ''));

            if ($prompt === '') {
                continue;
            }

            $heading = $scope === AiScope::Global
                ? '## Personalización global'
                : '## Personalización: '.$scope->label();

            $sections[] = $heading."\n\n".$prompt;
        }

        return $sections === [] ? null : implode("\n\n", $sections);
    }

    /**
     * Plain concatenation used by the settings preview: the block is already
     * part of the agent instructions, so this only mirrors the final layout.
     */
    public function preview(?string $baseInstructions, ?string $block): string
    {
        $base = (string) $baseInstructions;

        if ($block === null || trim($block) === '') {
            return $base;
        }

        return $base === '' ? (string) $block : $base."\n\n".$block;
    }
}
