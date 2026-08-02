<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider\Local;

use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Transformation;

/**
 * Zero-config baseline provider. It performs no server-side transformation: it
 * serves the original asset, but still exposes the real intrinsic dimensions
 * read at resolution time (so the component can emit correct width/height).
 *
 * @author Symfony Community
 */
final class LocalImageProvider implements ImageProviderInterface
{
    public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
    {
        return new GeneratedImage(
            url: $source->url,
            width: $source->dimensions?->width,
            height: $source->dimensions?->height,
        );
    }

    public function capabilities(): ProviderCapabilities
    {
        // Cannot resize nor convert: a single <img> at native size is emitted.
        return new ProviderCapabilities(formats: [], widths: [], canResize: false);
    }
}
