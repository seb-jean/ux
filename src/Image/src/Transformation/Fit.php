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

/**
 * How an image fits within the bounds given by "width" and "height".
 *
 * Like Fastly, this only has an effect when both dimensions are given.
 *
 * @see https://www.fastly.com/documentation/reference/io/fit/
 */
enum Fit: string
{
    /**
     * Resize to fit entirely within the region, making one dimension smaller if needed.
     */
    case Bounds = 'bounds';

    /**
     * Resize to entirely cover the region, making one dimension larger if needed.
     */
    case Cover = 'cover';

    /**
     * Resize and crop centrally to exactly fit the region.
     */
    case Crop = 'crop';
}
