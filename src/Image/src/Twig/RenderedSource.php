<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Twig;

/**
 * A single <source> element of a <picture>: a format, a media condition, or both.
 *
 * @author Symfony Community
 */
final class RenderedSource
{
    /**
     * @param int|null $width  intrinsic width of this source, so that browsers can
     *                         reserve the right space when art direction changes
     *                         the aspect ratio
     * @param int|null $height intrinsic height of this source
     */
    public function __construct(
        public readonly string $srcset,
        public readonly ?string $type = null,
        public readonly ?string $sizes = null,
        public readonly ?string $media = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {
    }
}
