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
 * The computed markup model consumed by the component template.
 *
 * @author Symfony Community
 */
final class RenderedImage
{
    /**
     * @param list<RenderedSource> $sources non-empty => render a <picture>
     */
    public function __construct(
        public readonly string $src,
        public readonly ?string $srcset,
        public readonly ?string $sizes,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly string $alt,
        public readonly string $loading,
        public readonly string $decoding,
        public readonly ?string $fetchpriority,
        public readonly array $sources = [],
    ) {
    }
}
