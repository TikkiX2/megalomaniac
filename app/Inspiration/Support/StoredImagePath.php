<?php

declare(strict_types=1);

namespace App\Inspiration\Support;

use App\Models\SavedImage;

/**
 * Canonical local storage paths for inspiration downloads.
 *
 * Paths stay `inspiration/{user_id}/{source}/{source_id}.{kind}.{ext}` with a
 * `jpg` fallback when the remote URL has no usable extension.
 */
final class StoredImagePath
{
    public static function thumb(SavedImage $saved, string $url): string
    {
        return self::make($saved, 'thumb', $url);
    }

    public static function full(SavedImage $saved, string $url): string
    {
        return self::make($saved, 'full', $url);
    }

    public static function extension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : 'jpg';
    }

    private static function make(SavedImage $saved, string $kind, string $url): string
    {
        // Defensive: the request already constrains source_id, but a row created
        // through another path must never be able to escape the storage prefix.
        $sourceId = preg_replace('/[^A-Za-z0-9._-]/', '', $saved->source_id);

        if (! is_string($sourceId) || $sourceId === '') {
            $sourceId = 'image';
        }

        return sprintf(
            'inspiration/%d/%s/%s.%s.%s',
            $saved->user_id,
            $saved->source,
            $sourceId,
            $kind,
            self::extension($url),
        );
    }
}
