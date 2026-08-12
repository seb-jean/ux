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
use Symfony\UX\Image\Exception\UnsupportedTransformationException;

/**
 * The region to keep, expressed either in pixels or as an aspect ratio.
 *
 * Supported syntaxes, all compatible with Fastly:
 *
 *     crop=1000,500
 *     crop=16:9
 *     crop=1000,500,x400,y50
 *     crop=1000,500,x25p,y10p
 *     crop=1:1,offset-x90,offset-y50
 *     crop=3000,600,x100,y100,safe
 *
 * @see https://www.fastly.com/documentation/reference/io/crop/
 */
final class Crop
{
    private function __construct(
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?float $ratioWidth,
        public readonly ?float $ratioHeight,
        public readonly ?float $x,
        public readonly bool $xIsPercent,
        public readonly ?float $y,
        public readonly bool $yIsPercent,
        public readonly ?float $offsetX,
        public readonly ?float $offsetY,
        public readonly bool $safe,
    ) {
    }

    public static function pixels(int $width, int $height): self
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('A crop must be positive, got %dx%d.', $width, $height));
        }

        return new self($width, $height, null, null, null, false, null, false, null, null, false);
    }

    public static function ratio(float $ratioWidth, float $ratioHeight): self
    {
        if ($ratioWidth <= 0 || $ratioHeight <= 0) {
            throw new InvalidArgumentException(\sprintf('A crop ratio must be positive, got %s:%s.', $ratioWidth, $ratioHeight));
        }

        return new self(null, null, $ratioWidth, $ratioHeight, null, false, null, false, null, null, false);
    }

    public static function fromString(string $crop): self
    {
        $tokens = array_map(trim(...), explode(',', $crop));
        $tokens = array_values(array_filter($tokens, static fn (string $token) => '' !== $token));

        if ([] === $tokens) {
            throw new InvalidArgumentException('The "crop" transformation cannot be empty.');
        }

        $size = array_shift($tokens);

        if (str_contains($size, ':')) {
            [$ratioWidth, $ratioHeight] = self::parseRatio($size, $crop);
            $instance = self::ratio($ratioWidth, $ratioHeight);
        } else {
            $height = array_shift($tokens);

            if (null === $height) {
                throw new InvalidArgumentException(\sprintf('The "crop" transformation "%s" must provide a height, or use the "width:height" ratio syntax.', $crop));
            }

            $instance = self::pixels(self::parseDimension($size, $crop), self::parseDimension($height, $crop));
        }

        foreach ($tokens as $token) {
            $instance = $instance->withModifier($token, $crop);
        }

        if ((null !== $instance->x) !== (null !== $instance->y) || (null !== $instance->offsetX) !== (null !== $instance->offsetY)) {
            throw new InvalidArgumentException(\sprintf('The "crop" transformation "%s" must provide both coordinates of a position.', $crop));
        }

        return $instance;
    }

    public function isRatio(): bool
    {
        return null !== $this->ratioWidth;
    }

    public function toString(): string
    {
        $parts = [$this->isRatio()
            ? self::formatNumber($this->ratioWidth).':'.self::formatNumber($this->ratioHeight)
            : $this->width.','.$this->height];

        if (null !== $this->x) {
            $parts[] = 'x'.self::formatNumber($this->x).($this->xIsPercent ? 'p' : '');
            $parts[] = 'y'.self::formatNumber($this->y).($this->yIsPercent ? 'p' : '');
        }

        if (null !== $this->offsetX) {
            $parts[] = 'offset-x'.self::formatNumber($this->offsetX);
            $parts[] = 'offset-y'.self::formatNumber($this->offsetY);
        }

        if ($this->safe) {
            $parts[] = 'safe';
        }

        return implode(',', $parts);
    }

    private function withModifier(string $token, string $crop): self
    {
        $lower = strtolower($token);

        if ('safe' === $lower) {
            return new self($this->width, $this->height, $this->ratioWidth, $this->ratioHeight, $this->x, $this->xIsPercent, $this->y, $this->yIsPercent, $this->offsetX, $this->offsetY, true);
        }

        if ('smart' === $lower) {
            throw new UnsupportedTransformationException('crop', 'content-aware cropping ("smart") requires image analysis that UX Image does not perform');
        }

        if (preg_match('/^offset-([xy])(-?\d+(?:\.\d+)?)$/i', $token, $matches)) {
            $value = (float) $matches[2];

            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException(\sprintf('The "crop" offset "%s" must be a percentage between 0 and 100, got %s.', $token, $matches[2]));
            }

            return 'x' === strtolower($matches[1])
                ? new self($this->width, $this->height, $this->ratioWidth, $this->ratioHeight, $this->x, $this->xIsPercent, $this->y, $this->yIsPercent, $value, $this->offsetY, $this->safe)
                : new self($this->width, $this->height, $this->ratioWidth, $this->ratioHeight, $this->x, $this->xIsPercent, $this->y, $this->yIsPercent, $this->offsetX, $value, $this->safe);
        }

        if (preg_match('/^([xy])(-?\d+(?:\.\d+)?)(p?)$/i', $token, $matches)) {
            $value = (float) $matches[2];
            $isPercent = '' !== $matches[3];

            if ($isPercent && ($value < 0 || $value > 100)) {
                throw new InvalidArgumentException(\sprintf('The "crop" coordinate "%s" must be a percentage between 0 and 100, got %s.', $token, $matches[2]));
            }

            return 'x' === strtolower($matches[1])
                ? new self($this->width, $this->height, $this->ratioWidth, $this->ratioHeight, $value, $isPercent, $this->y, $this->yIsPercent, $this->offsetX, $this->offsetY, $this->safe)
                : new self($this->width, $this->height, $this->ratioWidth, $this->ratioHeight, $this->x, $this->xIsPercent, $value, $isPercent, $this->offsetX, $this->offsetY, $this->safe);
        }

        throw new InvalidArgumentException(\sprintf('The "crop" transformation "%s" contains the unknown modifier "%s".', $crop, $token));
    }

    /**
     * @return array{float, float}
     */
    private static function parseRatio(string $ratio, string $crop): array
    {
        $parts = explode(':', $ratio);

        if (2 !== \count($parts) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            throw new InvalidArgumentException(\sprintf('The "crop" transformation "%s" has an invalid ratio "%s", expected "width:height".', $crop, $ratio));
        }

        return [(float) $parts[0], (float) $parts[1]];
    }

    private static function parseDimension(string $value, string $crop): int
    {
        if (!preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException(\sprintf('The "crop" transformation "%s" has an invalid dimension "%s", expected a number of pixels.', $crop, $value));
        }

        return (int) $value;
    }

    private static function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.') ?: '0';
    }
}
