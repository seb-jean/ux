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

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * A region of an image, in pixels.
 */
final class Rectangle
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $width,
        public readonly int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('A rectangle must be positive, got %dx%d.', $width, $height));
        }
    }

    public function size(): Size
    {
        return new Size($this->width, $this->height);
    }

    public function coversEntirely(Size $size): bool
    {
        return 0 === $this->x && 0 === $this->y && $this->width === $size->width && $this->height === $size->height;
    }
}
