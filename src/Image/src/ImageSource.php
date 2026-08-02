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
 * A resolved source: what the user wrote, the public URL it maps to, the local
 * filesystem path (when resolvable) and its intrinsic dimensions (when known).
 *
 * @author Symfony Community
 */
final class ImageSource
{
    public function __construct(
        public readonly string $src,
        public readonly string $url,
        public readonly ?string $path = null,
        public readonly ?ImageDimensions $dimensions = null,
    ) {
    }
}
