<?php

namespace App\Ai\Support;

use GdImage;

/**
 * Downscales chat images before they are stored, so every later turn that
 * re-embeds the conversation history as base64 stays small. A phone photo of
 * 5 MB becomes a few hundred KB without a visible quality loss in chat.
 */
final class ChatImageOptimizer
{
    public const MAX_DIMENSION = 1600;

    public const QUALITY = 80;

    /**
     * Re-encode an image at a bounded size.
     *
     * @return array{contents: string, extension: string, mime: string, width: int, height: int, size: int}|null
     *                                                                                                           null when the file is not a supported image or is already optimal
     */
    public function optimize(string $sourcePath): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $bytes = @file_get_contents($sourcePath);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            return null;
        }

        $format = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        $width = (int) $info[0];
        $height = (int) $info[1];

        if ($format === null || $width < 1 || $height < 1) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            return null;
        }

        $needsResize = max($width, $height) > self::MAX_DIMENSION;

        try {
            $image = $this->applyExifOrientation($image, $format, $sourcePath, $width, $height);

            if ($needsResize) {
                $ratio = self::MAX_DIMENSION / max($width, $height);

                $target = imagescale(
                    $image,
                    max(1, (int) round($width * $ratio)),
                    max(1, (int) round($height * $ratio)),
                    IMG_BICUBIC,
                );

                if ($target instanceof GdImage) {
                    imagedestroy($image);
                    $image = $target;
                    $width = imagesx($target);
                    $height = imagesy($target);
                }
            }

            $contents = $this->encode($image, $format);
        } finally {
            imagedestroy($image);
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        // Keep the original when re-encoding did not actually help.
        if (! $needsResize && strlen($contents) >= strlen($bytes)) {
            return null;
        }

        return [
            'contents' => $contents,
            'extension' => $format === 'jpeg' ? 'jpg' : $format,
            'mime' => $info['mime'],
            'width' => $width,
            'height' => $height,
            'size' => strlen($contents),
        ];
    }

    /**
     * Re-optimize a stored file in place, keeping its path and format. Returns
     * true when the file was replaced with a smaller version.
     */
    public function optimizeInPlace(string $absolutePath): bool
    {
        $result = $this->optimize($absolutePath);

        if ($result === null || $result['size'] >= (int) @filesize($absolutePath)) {
            return false;
        }

        return @file_put_contents($absolutePath, $result['contents']) !== false;
    }

    protected function applyExifOrientation(GdImage $image, string $format, string $sourcePath, int &$width, int &$height): GdImage
    {
        if ($format !== 'jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);

        if ($exif === false || ! isset($exif['Orientation'])) {
            return $image;
        }

        $rotation = match ((int) $exif['Orientation']) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        $width = imagesx($rotated);
        $height = imagesy($rotated);

        return $rotated;
    }

    protected function encode(GdImage $image, string $format): ?string
    {
        ob_start();

        $ok = match ($format) {
            'jpeg' => imagejpeg($image, null, self::QUALITY),
            'png' => imagepng($image, null, 6),
            'webp' => function_exists('imagewebp') && imagewebp($image, null, self::QUALITY),
            default => false,
        };

        $contents = ob_get_clean();

        return $ok === true && is_string($contents) ? $contents : null;
    }
}
