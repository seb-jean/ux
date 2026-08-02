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
 * The delivery URL of a single variant, produced by a provider.
 *
 * @author Symfony Community
 */
final class GeneratedImage
{
    public function __construct(
        public readonly string $url,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $format = null,
    ) {
    }
}
