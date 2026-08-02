<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider;

/**
 * Declares what a provider can produce, so the component never asks the impossible.
 *
 * @author Symfony Community
 */
final class ProviderCapabilities
{
    /**
     * @param list<string>   $formats output formats in preference order ('auto' allowed)
     * @param list<int>|null $widths  allowed widths; null means arbitrary widths are supported
     */
    public function __construct(
        public readonly array $formats,
        public readonly ?array $widths = null,
        public readonly bool $canResize = true,
    ) {
    }

    public function supportsFormat(string $format): bool
    {
        return \in_array($format, $this->formats, true) || \in_array('auto', $this->formats, true);
    }

    public function supportsWidth(int $width): bool
    {
        return null === $this->widths || \in_array($width, $this->widths, true);
    }

    /**
     * Returns the nearest allowed width greater than or equal to $width, or the
     * largest allowed width when none is large enough. Returns null when the
     * provider cannot resize at all.
     */
    public function clampWidth(int $width): ?int
    {
        if (null === $this->widths) {
            return $width;
        }

        if (!$this->widths) {
            return null;
        }

        $sorted = $this->widths;
        sort($sorted);

        foreach ($sorted as $candidate) {
            if ($candidate >= $width) {
                return $candidate;
            }
        }

        return end($sorted);
    }
}
