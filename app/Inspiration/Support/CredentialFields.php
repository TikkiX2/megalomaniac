<?php

declare(strict_types=1);

namespace App\Inspiration\Support;

/**
 * Resolves the canonical credential-field names for a source.
 *
 * The backend validation (`UpdateInspirationSettingsRequest`) and the settings
 * payload consumed by the UI must agree on which fields a source expects, so
 * both read the same `inspiration.credential_fields` map through this helper.
 */
final class CredentialFields
{
    /**
     * @return array<int, string>
     */
    public static function for(string $source): array
    {
        $fields = config("inspiration.credential_fields.{$source}", ['key']);

        return is_array($fields) && $fields !== [] ? array_values($fields) : ['key'];
    }

    /**
     * Whether the source accepts user credentials at all.
     *
     * Unlike `for()`, this does NOT fall back to a default field: a source
     * without a `credential_fields` entry (e.g. Openverse, Are.na) must not
     * render credential inputs nor accept `keys.{source}` payloads.
     */
    public static function has(string $source): bool
    {
        $fields = config("inspiration.credential_fields.{$source}");

        return is_array($fields) && $fields !== [];
    }
}
