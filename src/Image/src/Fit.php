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

/**
 * How an image should be resized to fit the requested box.
 *
 * @author Symfony Community
 */
enum Fit: string
{
    /** Fit inside the box, keep the aspect ratio, no crop. */
    case Contain = 'contain';

    /** Fill the box, keep the aspect ratio, crop the overflow. */
    case Cover = 'cover';

    /** Stretch to the box, ignore the aspect ratio. */
    case Fill = 'fill';
}
