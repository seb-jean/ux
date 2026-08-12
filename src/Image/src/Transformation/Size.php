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

final class Size
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('An image size must be positive, got %dx%d.', $width, $height));
        }
    }

    public function aspectRatio(): float
    {
        return $this->width / $this->height;
    }

    public function scale(float $factor): self
    {
        return new self(
            max(1, (int) round($this->width * $factor)),
            max(1, (int) round($this->height * $factor)),
        );
    }

    public function transpose(): self
    {
        return new self($this->height, $this->width);
    }

    public function equals(self $other): bool
    {
        return $this->width === $other->width && $this->height === $other->height;
    }
}
