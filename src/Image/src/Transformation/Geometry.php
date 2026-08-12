<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Transformation;

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * Turns a set of transformations and a source size into a plan.
 *
 * This is pure arithmetic: no image is opened here, which is what lets Twig
 * emit the final "width" and "height" attributes without any processing.
 */
final class Geometry
{
    public static function plan(Size $source, Transformations $transformations): TransformationPlan
    {
        $orientation = $transformations->orientation;
        $oriented = $orientation?->swapsAxes() ? $source->transpose() : $source;

        $crop = null !== $transformations->crop ? self::cropRectangle($oriented, $transformations->crop) : null;
        $working = $crop?->size() ?? $oriented;

        $dpr = $transformations->dpr ?? 1.0;
        $targetWidth = null !== $transformations->width ? max(1, (int) round($transformations->width * $dpr)) : null;
        $targetHeight = null !== $transformations->height ? max(1, (int) round($transformations->height * $dpr)) : null;

        $finalCrop = null;

        if (null === $targetWidth && null === $targetHeight) {
            $resize = 1.0 === $dpr ? $working : $working->scale($dpr);
        } elseif (null === $targetHeight) {
            $resize = $working->scale($targetWidth / $working->width);
        } elseif (null === $targetWidth) {
            $resize = $working->scale($targetHeight / $working->height);
        } else {
            $widthFactor = $targetWidth / $working->width;
            $heightFactor = $targetHeight / $working->height;

            $resize = match ($transformations->fit ?? Fit::Bounds) {
                Fit::Bounds => $working->scale(min($widthFactor, $heightFactor)),
                Fit::Cover => $working->scale(max($widthFactor, $heightFactor)),
                Fit::Crop => $working->scale(max($widthFactor, $heightFactor)),
            };

            if (Fit::Crop === $transformations->fit) {
                $width = min($targetWidth, $resize->width);
                $height = min($targetHeight, $resize->height);

                $finalCrop = new Rectangle(
                    (int) round(($resize->width - $width) / 2),
                    (int) round(($resize->height - $height) / 2),
                    $width,
                    $height,
                );
            }
        }

        $output = $finalCrop?->size() ?? $resize;

        return new TransformationPlan(
            source: $source,
            orientation: $orientation,
            crop: null !== $crop && !$crop->coversEntirely($oriented) ? $crop : null,
            resize: $resize,
            finalCrop: $finalCrop,
            output: $output,
            displaySize: 1.0 === $dpr ? $output : $output->scale(1 / $dpr),
        );
    }

    private static function cropRectangle(Size $source, Crop $crop): Rectangle
    {
        if ($crop->isRatio()) {
            $ratio = $crop->ratioWidth / $crop->ratioHeight;

            if ($source->aspectRatio() > $ratio) {
                $height = $source->height;
                $width = max(1, (int) round($height * $ratio));
            } else {
                $width = $source->width;
                $height = max(1, (int) round($width / $ratio));
            }
        } else {
            $width = $crop->width;
            $height = $crop->height;

            if ($width > $source->width || $height > $source->height) {
                if (!$crop->safe) {
                    throw new InvalidArgumentException(\sprintf('The crop %dx%d does not fit in the %dx%d source image; append ",safe" to the "crop" transformation to shrink it instead.', $width, $height, $source->width, $source->height));
                }

                $width = min($width, $source->width);
                $height = min($height, $source->height);
            }
        }

        if (null !== $crop->x) {
            $x = $crop->xIsPercent ? $crop->x / 100 * $source->width : $crop->x;
            $y = $crop->yIsPercent ? $crop->y / 100 * $source->height : $crop->y;
        } else {
            // "offset-x" positions the region within the leftover space, 50% being centered
            $x = ($source->width - $width) * ($crop->offsetX ?? 50.0) / 100;
            $y = ($source->height - $height) * ($crop->offsetY ?? 50.0) / 100;
        }

        return new Rectangle(
            max(0, min((int) round($x), $source->width - $width)),
            max(0, min((int) round($y), $source->height - $height)),
            $width,
            $height,
        );
    }
}
