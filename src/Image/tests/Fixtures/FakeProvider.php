<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Fixtures;

use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Transformation;

/**
 * A tiny query-param provider used to exercise the renderer without a CDN.
 */
final class FakeProvider implements ImageProviderInterface
{
    public ?Transformation $lastTransformation = null;

    public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
    {
        $this->lastTransformation = $transformation;

        $params = array_filter([
            'w' => $transformation->width,
            'h' => $transformation->height,
            'f' => $transformation->format,
        ], static fn ($v) => null !== $v);

        $url = $source->url.($params ? '?'.http_build_query($params) : '');

        $width = $transformation->width;
        $height = $transformation->height ?? ($width && $source->dimensions ? $source->dimensions->heightForWidth($width) : null);

        return new GeneratedImage($url, $width, $height, $transformation->format);
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(formats: ['avif', 'webp', 'jpg'], widths: null, canResize: true);
    }
}
