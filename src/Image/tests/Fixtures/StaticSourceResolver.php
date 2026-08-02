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

use Symfony\UX\Image\Asset\SourceResolverInterface;
use Symfony\UX\Image\ImageDimensions;
use Symfony\UX\Image\ImageSource;

/**
 * Resolves any src to a fixed public URL with known dimensions, so tests do not
 * depend on the filesystem or on the Asset component configuration.
 */
final class StaticSourceResolver implements SourceResolverInterface
{
    /**
     * A "<width>x<height>" marker anywhere in the src sets its dimensions, so
     * that art direction can be tested with genuinely different aspect ratios.
     */
    public function resolve(string $src): ImageSource
    {
        $dimensions = new ImageDimensions(1600, 900);
        if (preg_match('/(\d+)x(\d+)/', $src, $matches)) {
            $dimensions = new ImageDimensions((int) $matches[1], (int) $matches[2]);
        }

        return new ImageSource(
            src: $src,
            url: '/'.ltrim($src, '/'),
            path: null,
            dimensions: $dimensions,
        );
    }
}
