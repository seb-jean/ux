<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image;

use Symfony\UX\Image\Cache\ImageCache;
use Symfony\UX\Image\Processor\ProcessorRegistry;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Geometry;
use Symfony\UX\Image\Transformation\TransformationPlan;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Applies transformations to a source image, through the disk cache.
 *
 * Twig and the controller both go through this class, so the size an <img> tag
 * advertises is always the size the generated file really has.
 */
final class ImageTransformer
{
    public function __construct(
        private readonly ProcessorRegistry $processors,
        private readonly ImageCache $cache,
    ) {
    }

    public function plan(ImageSource $source, Transformations $transformations): TransformationPlan
    {
        return Geometry::plan($source->sizeOrFail(), $this->resolveOrientation($source, $transformations));
    }

    /**
     * @return string the path of the transformed image on disk
     */
    public function transform(ImageSource $source, Transformations $transformations, Format $outputFormat): string
    {
        $key = $this->cache->key($source, $transformations, $outputFormat);

        if (null !== $path = $this->cache->lookup($key, $outputFormat)) {
            return $path;
        }

        $processor = $this->processors->get($source->format, $outputFormat);
        $plan = $this->plan($source, $transformations);

        return $this->cache->write($key, $outputFormat, $processor->process($source, $plan, $this->resolveOrientation($source, $transformations), $outputFormat));
    }

    /**
     * Picks the output format, negotiating "auto" against what the client accepts.
     *
     * @param list<string> $acceptedMimeTypes
     */
    public function resolveFormat(ImageSource $source, Transformations $transformations, array $acceptedMimeTypes = []): Format
    {
        if (null !== $transformations->format) {
            return $transformations->format;
        }

        foreach ($transformations->auto as $candidate) {
            if (\in_array($candidate->mimeType(), $acceptedMimeTypes, true) && $this->processors->supports($source->format, $candidate)) {
                return $candidate;
            }
        }

        return $source->format;
    }

    /**
     * An image without an explicit "orient" is straightened using its EXIF data,
     * like Fastly does.
     */
    private function resolveOrientation(ImageSource $source, Transformations $transformations): Transformations
    {
        return null !== $transformations->orientation
            ? $transformations
            : $transformations->withOrientation($source->exifOrientation());
    }
}
