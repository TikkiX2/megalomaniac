<?php

namespace App\Ai\Tools;

use Illuminate\Support\Str;

final class ToolRouter
{
    /**
     * Route a user message to tool groups. Returns a cheap fallback when no
     * group matches; adds "actions" when the message contains a write verb.
     *
     * @return string[]
     */
    public static function route(string $message): array
    {
        $normalized = self::normalize($message);
        $groups = [];

        foreach (config('ai_tools.keywords', []) as $group => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, self::normalize($keyword))) {
                    $groups[] = $group;

                    break;
                }
            }
        }

        foreach (config('ai_tools.write_verbs', []) as $verb) {
            if (str_contains($normalized, self::normalize($verb))) {
                $groups[] = 'actions';

                break;
            }
        }

        if ($groups === []) {
            $groups = config('ai_tools.fallback', ['tasks', 'integrations']);
        }

        return array_values(array_unique(array_filter(
            $groups,
            fn (string $group): bool => ToolCatalog::isValidGroup($group),
        )));
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii($value));
    }
}
