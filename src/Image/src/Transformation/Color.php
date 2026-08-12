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
 * An RGBA color, as accepted by the "bg-color" transformation.
 *
 * @see https://www.fastly.com/documentation/reference/io/bg-color/
 */
final class Color
{
    public function __construct(
        public readonly int $red,
        public readonly int $green,
        public readonly int $blue,
        public readonly int $alpha = 255,
    ) {
        foreach (['red' => $red, 'green' => $green, 'blue' => $blue, 'alpha' => $alpha] as $name => $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidArgumentException(\sprintf('The "%s" channel must be between 0 and 255, got %d.', $name, $value));
            }
        }
    }

    /**
     * Parses "RGB", "RGBA", "RRGGBB", "RRGGBBAA" (with an optional leading "#"),
     * "rgb(r,g,b)" and "rgba(r,g,b,a)" where the alpha is a 0-1 float.
     */
    public static function fromString(string $color): self
    {
        $color = trim($color);

        if (preg_match('/^rgba?\(([^)]*)\)$/i', $color, $matches)) {
            $channels = array_map(trim(...), explode(',', $matches[1]));

            if (3 !== \count($channels) && 4 !== \count($channels)) {
                throw new InvalidArgumentException(\sprintf('The color "%s" must have 3 or 4 channels, got %d.', $color, \count($channels)));
            }

            foreach ($channels as $channel) {
                if (!is_numeric($channel)) {
                    throw new InvalidArgumentException(\sprintf('The color "%s" contains the non-numeric channel "%s".', $color, $channel));
                }
            }

            return new self(
                (int) $channels[0],
                (int) $channels[1],
                (int) $channels[2],
                isset($channels[3]) ? (int) round(((float) $channels[3]) * 255) : 255,
            );
        }

        $hex = ltrim($color, '#');

        if (!preg_match('/^[0-9a-f]+$/i', $hex)) {
            throw new InvalidArgumentException(\sprintf('The color "%s" is not a valid hexadecimal or rgb() color.', $color));
        }

        return match (\strlen($hex)) {
            3 => new self(...self::expand($hex, 3)),
            4 => new self(...self::expand($hex, 4)),
            6 => new self((int) hexdec($hex[0].$hex[1]), (int) hexdec($hex[2].$hex[3]), (int) hexdec($hex[4].$hex[5])),
            8 => new self((int) hexdec($hex[0].$hex[1]), (int) hexdec($hex[2].$hex[3]), (int) hexdec($hex[4].$hex[5]), (int) hexdec($hex[6].$hex[7])),
            default => throw new InvalidArgumentException(\sprintf('The color "%s" must have 3, 4, 6 or 8 hexadecimal digits, got %d.', $color, \strlen($hex))),
        };
    }

    public function isOpaque(): bool
    {
        return 255 === $this->alpha;
    }

    public function toString(): string
    {
        $hex = \sprintf('%02x%02x%02x', $this->red, $this->green, $this->blue);

        return $this->isOpaque() ? $hex : $hex.\sprintf('%02x', $this->alpha);
    }

    /**
     * @return list<int>
     */
    private static function expand(string $hex, int $length): array
    {
        $channels = [];
        for ($i = 0; $i < $length; ++$i) {
            $channels[] = (int) hexdec($hex[$i].$hex[$i]);
        }

        return $channels;
    }
}
