<?php

namespace App\Integrations\Support;

use App\Integrations\Actions\Param;

final class SecretRedactor
{
    private const REDACTED = '[redacted]';

    private const DEFAULT_KEYS = [
        'token', 'access_token', 'refresh_token', 'api_key', 'apikey', 'secret',
        'password', 'passwd', 'authorization', 'private_key', 'client_secret',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  Param[]  $params
     * @return array<string, mixed>
     */
    public static function redact(array $data, array $params = []): array
    {
        $sensitive = array_map(
            fn (Param $param): string => $param->name,
            array_filter($params, fn (Param $param): bool => $param->sensitive),
        );

        return self::walk($data, array_merge(self::DEFAULT_KEYS, $sensitive));
    }

    public static function redactString(string $value): string
    {
        foreach (config('integrations.redaction.patterns', []) as $pattern) {
            $value = preg_replace($pattern, self::REDACTED, $value) ?? $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $sensitiveKeys
     * @return array<string, mixed>
     */
    private static function walk(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::walk($value, $sensitiveKeys);
            }
        }

        return $data;
    }
}
