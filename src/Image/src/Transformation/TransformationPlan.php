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
 * The geometric operations a driver has to perform, in order.
 *
 * Drivers execute this plan, they never interpret transformations themselves,
 * so GD and Imagick cannot drift apart.
 */
final class TransformationPlan
{
    public function __construct(
        public readonly Size $source,
        public readonly ?Orientation $orientation,
        public readonly ?Rectangle $crop,
        public readonly Size $resize,
        public readonly ?Rectangle $finalCrop,
        public readonly Size $output,
        public readonly Size $displaySize,
    ) {
    }

    /**
     * Whether the pixels are left untouched, in which case a driver only has to
     * re-encode (or even copy) the source.
     */
    public function isIdentity(): bool
    {
        return (null === $this->orientation || $this->orientation->isIdentity())
            && null === $this->crop
            && null === $this->finalCrop
            && $this->resize->equals($this->source);
    }
}
