<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Dimensions;

use Symfony\UX\Image\ImageDimensions;

/**
 * Reads dimensions with the native getimagesize() (GD-free; supports JPEG, PNG,
 * GIF, WebP and, on PHP 8.1+, AVIF).
 *
 * @author Symfony Community
 */
final class NativeDimensionsReader implements DimensionsReaderInterface
{
    public function read(string $path): ?ImageDimensions
    {
        if (!is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);
        if (false === $info || !isset($info[0], $info[1]) || 0 === $info[0]) {
            return null;
        }

        return new ImageDimensions((int) $info[0], (int) $info[1]);
    }
}
