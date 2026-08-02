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
 * The intrinsic pixel dimensions of an image.
 *
 * @author Symfony Community
 */
final class ImageDimensions
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {
    }

    public function aspectRatio(): float
    {
        return 0 === $this->height ? 1.0 : $this->width / $this->height;
    }

    public function heightForWidth(int $width): int
    {
        return (int) round($width / $this->aspectRatio());
    }
}
