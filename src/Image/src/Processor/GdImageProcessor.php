<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Processor;

use Symfony\UX\Image\Exception\RuntimeException;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Color;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Orientation;
use Symfony\UX\Image\Transformation\Rectangle;
use Symfony\UX\Image\Transformation\Size;
use Symfony\UX\Image\Transformation\TransformationPlan;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Transforms images with the "gd" extension.
 *
 * @see https://www.php.net/manual/en/book.image.php
 */
final class GdImageProcessor implements ImageProcessorInterface
{
    /**
     * GD only exposes a fixed 3x3 gaussian kernel, so a blur radius is
     * approximated by running it several times.
     */
    private const MAX_BLUR_PASSES = 50;

    public function isAvailable(): bool
    {
        return \extension_loaded('gd');
    }

    public function supports(Format $format): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $info = gd_info();

        return match ($format) {
            Format::Avif => (bool) ($info['AVIF Support'] ?? false),
            Format::Gif => ($info['GIF Read Support'] ?? false) && ($info['GIF Create Support'] ?? false),
            Format::Jpeg => (bool) ($info['JPEG Support'] ?? false),
            Format::Png => (bool) ($info['PNG Support'] ?? false),
            Format::Webp => (bool) ($info['WebP Support'] ?? false),
        };
    }

    public function process(ImageSource $source, TransformationPlan $plan, Transformations $transformations, Format $outputFormat): string
    {
        $image = $this->load($source);

        try {
            $image = $this->orient($image, $plan->orientation);

            if (null !== $plan->crop) {
                $image = $this->crop($image, $plan->crop);
            }

            $image = $this->resize($image, $plan->resize);

            if (null !== $plan->finalCrop) {
                $image = $this->crop($image, $plan->finalCrop);
            }

            $this->applyFilters($image, $transformations);

            $image = $this->flatten($image, $transformations->backgroundColor, $outputFormat);

            return $this->encode($image, $outputFormat, $transformations->quality);
        } finally {
            imagedestroy($image);
        }
    }

    private function load(ImageSource $source): \GdImage
    {
        $image = match ($source->format) {
            Format::Avif => @imagecreatefromavif($source->path),
            Format::Gif => @imagecreatefromgif($source->path),
            Format::Jpeg => @imagecreatefromjpeg($source->path),
            Format::Png => @imagecreatefrompng($source->path),
            Format::Webp => @imagecreatefromwebp($source->path),
        };

        if (false === $image) {
            throw new RuntimeException(\sprintf('GD cannot read the image "%s".', $source->relativePath));
        }

        // without this, an image that is never resized loses its alpha channel on encoding
        if (imageistruecolor($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }

    private function orient(\GdImage $image, ?Orientation $orientation): \GdImage
    {
        if (null === $orientation || $orientation->isIdentity()) {
            return $image;
        }

        if ($orientation->mirrorsHorizontally()) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }

        if ($orientation->mirrorsVertically()) {
            imageflip($image, \IMG_FLIP_VERTICAL);
        }

        if (0 !== $rotation = $orientation->rotation()) {
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

            // imagerotate() turns counter-clockwise, our rotations are clockwise
            $rotated = imagerotate($image, 360 - $rotation, false !== $transparent ? $transparent : 0);

            if (false === $rotated) {
                throw new RuntimeException(\sprintf('GD cannot rotate the image by %d degrees.', $rotation));
            }

            imagedestroy($image);
            $image = $rotated;
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }

    private function crop(\GdImage $image, Rectangle $rectangle): \GdImage
    {
        $cropped = imagecrop($image, ['x' => $rectangle->x, 'y' => $rectangle->y, 'width' => $rectangle->width, 'height' => $rectangle->height]);

        if (false === $cropped) {
            throw new RuntimeException(\sprintf('GD cannot crop a %dx%d region at %d,%d.', $rectangle->width, $rectangle->height, $rectangle->x, $rectangle->y));
        }

        imagedestroy($image);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        return $cropped;
    }

    private function resize(\GdImage $image, Size $size): \GdImage
    {
        if (imagesx($image) === $size->width && imagesy($image) === $size->height) {
            return $image;
        }

        $resized = imagecreatetruecolor($size->width, $size->height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $size->width, $size->height, imagesx($image), imagesy($image));

        imagedestroy($image);

        return $resized;
    }

    private function applyFilters(\GdImage $image, Transformations $transformations): void
    {
        if ($transformations->blackAndWhite) {
            imagefilter($image, \IMG_FILTER_GRAYSCALE);
        }

        if (null !== $transformations->brightness) {
            // GD works on a -255..255 scale
            imagefilter($image, \IMG_FILTER_BRIGHTNESS, (int) round($transformations->brightness * 2.55));
        }

        if (null !== $transformations->contrast) {
            // GD's contrast is inverted: negative values increase it
            imagefilter($image, \IMG_FILTER_CONTRAST, -$transformations->contrast);
        }

        if (null !== $transformations->blur && $transformations->blur > 0) {
            $passes = min(self::MAX_BLUR_PASSES, max(1, (int) round($transformations->blur / 20)));

            for ($i = 0; $i < $passes; ++$i) {
                imagefilter($image, \IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
    }

    private function flatten(\GdImage $image, ?Color $background, Format $outputFormat): \GdImage
    {
        if (null === $background && $outputFormat->supportsAlpha()) {
            return $image;
        }

        $background ??= new Color(255, 255, 255);
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        // GD's alpha channel is 7 bits, and inverted
        $color = imagecolorallocatealpha($canvas, $background->red, $background->green, $background->blue, 127 - (int) round($background->alpha / 255 * 127));

        if (false === $color) {
            throw new RuntimeException('GD cannot allocate the background color.');
        }

        imagefilledrectangle($canvas, 0, 0, imagesx($image) - 1, imagesy($image) - 1, $color);
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagealphablending($canvas, false);

        imagedestroy($image);

        return $canvas;
    }

    private function encode(\GdImage $image, Format $format, ?int $quality): string
    {
        ob_start();

        try {
            $encoded = match ($format) {
                Format::Avif => imageavif($image, null, $quality ?? -1),
                Format::Gif => imagegif($image),
                Format::Jpeg => imagejpeg($image, null, $quality ?? 85),
                Format::Png => imagepng($image),
                Format::Webp => imagewebp($image, null, $quality ?? 85),
            };
        } finally {
            $contents = ob_get_clean();
        }

        if (!$encoded || '' === $contents) {
            throw new RuntimeException(\sprintf('GD cannot encode the image as "%s".', $format->value));
        }

        return $contents;
    }
}
