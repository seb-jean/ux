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
 * Reads the intrinsic dimensions of a local image file.
 *
 * @author Symfony Community
 */
interface DimensionsReaderInterface
{
    public function read(string $path): ?ImageDimensions;
}
