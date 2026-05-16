<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Transformer;

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * Applies image transformations using the GD extension.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class ImageTransformer
{
    private const SUPPORTED_FORMATS = ['webp', 'jpeg', 'png', 'avif'];
    private const SUPPORTED_FITS = ['contain', 'cover', 'fill', 'crop'];
    private const MIME_TYPES = [
        'webp' => 'image/webp',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'avif' => 'image/avif',
    ];

    /**
     * @param array{width?: int|null, height?: int|null, format?: string|null, quality?: int|null, fit?: string|null} $options
     *
     * @return array{0: string, 1: string} [binary data, MIME type]
     */
    public function transform(string $srcPath, array $options): array
    {
        $this->validateOptions($options);

        $source = $this->loadImage($srcPath);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $targetWidth = $options['width'] ?? null;
        $targetHeight = $options['height'] ?? null;
        $fit = $options['fit'] ?? 'contain';
        $format = $options['format'] ?? $this->detectFormat($srcPath);
        $quality = $options['quality'] ?? $this->defaultQuality($format);

        $output = $this->resize($source, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight, $fit);
        imagedestroy($source);

        $data = $this->encode($output, $format, $quality);
        imagedestroy($output);

        return [$data, self::MIME_TYPES[$format]];
    }

    private function resize(\GdImage $source, int $sw, int $sh, ?int $tw, ?int $th, string $fit): \GdImage
    {
        if (null === $tw && null === $th) {
            return $source;
        }

        $tw ??= (int) round($sw * ($th / $sh));
        $th ??= (int) round($sh * ($tw / $sw));

        return match ($fit) {
            'fill' => $this->resizeFill($source, $sw, $sh, $tw, $th),
            'cover' => $this->resizeCover($source, $sw, $sh, $tw, $th),
            'crop' => $this->resizeCrop($source, $sw, $sh, $tw, $th),
            default => $this->resizeContain($source, $sw, $sh, $tw, $th),
        };
    }

    /** Scale to exact dimensions, ignoring aspect ratio. */
    private function resizeFill(\GdImage $src, int $sw, int $sh, int $tw, int $th): \GdImage
    {
        $dst = $this->createCanvas($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);

        return $dst;
    }

    /** Scale to fit within dimensions, preserving aspect ratio (no crop). */
    private function resizeContain(\GdImage $src, int $sw, int $sh, int $tw, int $th): \GdImage
    {
        $ratio = min($tw / $sw, $th / $sh);
        $w = (int) round($sw * $ratio);
        $h = (int) round($sh * $ratio);

        $dst = $this->createCanvas($w, $h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

        return $dst;
    }

    /** Scale and crop to fill exact dimensions, preserving aspect ratio. */
    private function resizeCover(\GdImage $src, int $sw, int $sh, int $tw, int $th): \GdImage
    {
        $ratio = max($tw / $sw, $th / $sh);
        $scaledW = (int) round($sw * $ratio);
        $scaledH = (int) round($sh * $ratio);

        $srcX = (int) round(($scaledW - $tw) / 2 / $ratio);
        $srcY = (int) round(($scaledH - $th) / 2 / $ratio);
        $srcW = (int) round($tw / $ratio);
        $srcH = (int) round($th / $ratio);

        $dst = $this->createCanvas($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $tw, $th, $srcW, $srcH);

        return $dst;
    }

    /** Crop to exact dimensions from the center, no scaling. */
    private function resizeCrop(\GdImage $src, int $sw, int $sh, int $tw, int $th): \GdImage
    {
        $srcX = (int) max(0, round(($sw - $tw) / 2));
        $srcY = (int) max(0, round(($sh - $th) / 2));
        $cropW = min($tw, $sw);
        $cropH = min($th, $sh);

        $dst = $this->createCanvas($cropW, $cropH);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $cropW, $cropH, $cropW, $cropH);

        return $dst;
    }

    private function createCanvas(int $w, int $h): \GdImage
    {
        $image = imagecreatetruecolor($w, $h);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        return $image;
    }

    private function loadImage(string $path): \GdImage
    {
        $image = @imagecreatefromstring(file_get_contents($path));

        if (false === $image) {
            throw new \RuntimeException(\sprintf('Cannot load image "%s".', basename($path)));
        }

        return $image;
    }

    private function encode(\GdImage $image, string $format, int $quality): string
    {
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, $quality),
            'webp' => imagewebp($image, null, $quality),
            'avif' => imageavif($image, null, $quality),
            'png' => imagepng($image, null, (int) round((100 - $quality) / 10)),
        };

        return ob_get_clean();
    }

    private function detectFormat(string $path): string
    {
        return match (strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'avif' => 'avif',
            default => 'webp',
        };
    }

    private function defaultQuality(string $format): int
    {
        return 'png' === $format ? 90 : 85;
    }

    private function validateOptions(array $options): void
    {
        if (isset($options['format']) && !\in_array($options['format'], self::SUPPORTED_FORMATS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid format "%s". Supported: %s.', $options['format'], implode(', ', self::SUPPORTED_FORMATS)));
        }

        if (isset($options['fit']) && !\in_array($options['fit'], self::SUPPORTED_FITS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid fit "%s". Supported: %s.', $options['fit'], implode(', ', self::SUPPORTED_FITS)));
        }

        if (isset($options['quality']) && ($options['quality'] < 1 || $options['quality'] > 100)) {
            throw new InvalidArgumentException('Quality must be between 1 and 100.');
        }
    }
}
