<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use InvalidArgumentException;

final class SafeCatalogImageNormalizer
{
    public const MAX_INPUT_BYTES = 10 * 1024 * 1024;

    private const MAX_DIMENSION = 6000;

    private const MAX_PIXELS = 16_000_000;

    private const MAX_OUTPUT_BYTES = 10 * 1024 * 1024;

    /**
     * @return array{contents:string,mime:string,width:int,height:int}
     */
    public function normalize(string $contents): array
    {
        if (strlen($contents) > self::MAX_INPUT_BYTES) {
            throw new InvalidArgumentException('The source image exceeds the safe size limit.');
        }

        $imageInfo = @getimagesizefromstring($contents);

        if (! is_array($imageInfo)) {
            throw new InvalidArgumentException('The file is not a decodable image.');
        }

        [$width, $height, $type] = $imageInfo;
        $mime = image_type_to_mime_type($type);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('Only JPEG, PNG and WebP images are supported.');
        }

        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('Image dimensions exceed the safe limit.');
        }

        if ($width > intdiv(self::MAX_PIXELS, $height)) {
            throw new InvalidArgumentException('Image pixel count exceeds the safe limit.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new InvalidArgumentException('The image payload could not be decoded safely.');
        }

        try {
            ob_start();
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, 88),
                'image/png' => imagepng($image, null, 8),
                'image/webp' => imagewebp($image, null, 85),
            };
            $normalized = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if ($encoded !== true || $normalized === '') {
            throw new InvalidArgumentException('The image could not be normalized.');
        }

        if (strlen($normalized) > self::MAX_OUTPUT_BYTES) {
            throw new InvalidArgumentException('The normalized image exceeds the safe size limit.');
        }

        return [
            'contents' => $normalized,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
        ];
    }
}
