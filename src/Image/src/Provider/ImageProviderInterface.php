<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider;

use Symfony\UX\Image\Exception\UnsupportedTransformationException;
use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Transformation;

/**
 * Produces delivery URLs for image variants. Implementations MUST be pure:
 * given the same source and transformation, they return the same URL.
 *
 * @author Symfony Community
 */
interface ImageProviderInterface
{
    /**
     * @throws UnsupportedTransformationException when the provider cannot honor the request
     */
    public function transform(ImageSource $source, Transformation $transformation): GeneratedImage;

    public function capabilities(): ProviderCapabilities;
}
