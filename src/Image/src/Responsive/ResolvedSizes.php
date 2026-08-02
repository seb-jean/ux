<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Responsive;

/**
 * The "sizes" attribute to render, and the candidate widths it implies.
 *
 * @author Symfony Community
 */
final class ResolvedSizes
{
    /**
     * @param list<int> $widths empty when the sizes could not be resolved to widths
     */
    public function __construct(
        public readonly ?string $sizes,
        public readonly array $widths,
    ) {
    }
}
