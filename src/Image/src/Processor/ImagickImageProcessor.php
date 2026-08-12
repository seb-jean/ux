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
use Symfony\UX\Image\Transformation\TransformationPlan;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Transforms images with the "imagick" extension.
 *
 * @see https://www.php.net/manual/en/book.imagick.php
 */
final class ImagickImageProcessor implements ImageProcessorInterface
{
    public function isAvailable(): bool
    {
        return \extension_loaded('imagick');
    }

    public function supports(Format $format): bool
    {
        return $this->isAvailable() && [] !== \Imagick::queryFormats(strtoupper($format->value));
    }

    public function process(ImageSource $source, TransformationPlan $plan, Transformations $transformations, Format $outputFormat): string
    {
        try {
            $image = new \Imagick();
            $image->readImage($source->path);
        } catch (\ImagickException $e) {
            throw new RuntimeException(\sprintf('Imagick cannot read the image "%s".', $source->relativePath), previous: $e);
        }

        try {
            // animated images are reduced to their first frame
            $image->setFirstIterator();

            $this->orient($image, $plan->orientation);

            if (null !== $crop = $plan->crop) {
                $image->cropImage($crop->width, $crop->height, $crop->x, $crop->y);
                $image->setImagePage(0, 0, 0, 0);
            }

            $image->resizeImage($plan->resize->width, $plan->resize->height, \Imagick::FILTER_LANCZOS, 1);

            if (null !== $finalCrop = $plan->finalCrop) {
                $image->cropImage($finalCrop->width, $finalCrop->height, $finalCrop->x, $finalCrop->y);
                $image->setImagePage(0, 0, 0, 0);
            }

            $this->applyFilters($image, $transformations);
            $image = $this->flatten($image, $transformations->backgroundColor, $outputFormat);

            return $this->encode($image, $outputFormat, $transformations->quality);
        } catch (\ImagickException $e) {
            throw new RuntimeException(\sprintf('Imagick cannot transform the image "%s": %s', $source->relativePath, $e->getMessage()), previous: $e);
        } finally {
            $image->clear();
        }
    }

    private function orient(\Imagick $image, ?Orientation $orientation): void
    {
        if (null === $orientation || $orientation->isIdentity()) {
            return;
        }

        if ($orientation->mirrorsHorizontally()) {
            $image->flopImage();
        }

        if ($orientation->mirrorsVertically()) {
            $image->flipImage();
        }

        if (0 !== $rotation = $orientation->rotation()) {
            // rotateImage() turns clockwise, like our rotations
            $image->rotateImage(new \ImagickPixel('transparent'), $rotation);
        }

        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private function applyFilters(\Imagick $image, Transformations $transformations): void
    {
        if ($transformations->blackAndWhite) {
            $image->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
        }

        if (null !== $transformations->brightness || null !== $transformations->contrast) {
            $image->brightnessContrastImage($transformations->brightness ?? 0, $transformations->contrast ?? 0);
        }

        if (null !== $transformations->blur && $transformations->blur > 0) {
            // the same 0-1000 scale as GD, expressed as a gaussian sigma
            $image->blurImage(0, max(0.1, $transformations->blur / 100));
        }
    }

    private function flatten(\Imagick $image, ?Color $background, Format $outputFormat): \Imagick
    {
        if (null === $background && $outputFormat->supportsAlpha()) {
            return $image;
        }

        $background ??= new Color(255, 255, 255);

        $image->setImageBackgroundColor(new \ImagickPixel(\sprintf(
            'rgba(%d,%d,%d,%s)',
            $background->red,
            $background->green,
            $background->blue,
            rtrim(rtrim(number_format($background->alpha / 255, 4, '.', ''), '0'), '.') ?: '0',
        )));

        $flattened = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $image->clear();

        return $flattened;
    }

    private function encode(\Imagick $image, Format $format, ?int $quality): string
    {
        $image->setImageFormat($format->value);
        $image->stripImage();

        if ($format->isLossy() && null !== $quality) {
            $image->setImageCompressionQuality($quality);
        }

        $contents = $image->getImageBlob();

        if ('' === $contents) {
            throw new RuntimeException(\sprintf('Imagick cannot encode the image as "%s".', $format->value));
        }

        return $contents;
    }
}
