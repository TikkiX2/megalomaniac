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
}
