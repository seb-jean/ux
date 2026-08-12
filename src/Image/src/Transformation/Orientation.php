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
 * The cardinal orientation of an image.
 *
 * Numeric cases mirror the EXIF orientation tag, and describe the correction
 * to apply. Letter cases are the shorthands accepted by Fastly.
 *
 * @see https://www.fastly.com/documentation/reference/io/orient/
 */
enum Orientation: string
{
    case Normal = '1';
    case MirrorHorizontal = '2';
    case Rotate180 = '3';
    case MirrorVertical = '4';
    case Transpose = '5';
    case Rotate90 = '6';
    case Transverse = '7';
    case Rotate270 = '8';

    /** Rotate 90° clockwise. */
    case Right = 'r';

    /** Rotate 90° counter-clockwise. */
    case Left = 'l';

    /** Mirror horizontally. */
    case FlipHorizontal = 'h';

    /** Mirror vertically. */
    case FlipVertical = 'v';

    public static function tryFromExif(int $exifOrientation): ?self
    {
        return self::tryFrom((string) $exifOrientation);
    }

    /**
     * Whether the image must be mirrored horizontally, before rotating.
     */
    public function mirrorsHorizontally(): bool
    {
        return match ($this) {
            self::MirrorHorizontal, self::Transpose, self::Transverse, self::FlipHorizontal => true,
            default => false,
        };
    }

    public function mirrorsVertically(): bool
    {
        return match ($this) {
            self::MirrorVertical, self::FlipVertical => true,
            default => false,
        };
    }

    /**
     * The clockwise rotation, in degrees, applied after mirroring.
     */
    public function rotation(): int
    {
        return match ($this) {
            self::Rotate90, self::Transverse, self::Right => 90,
            self::Rotate180 => 180,
            self::Rotate270, self::Transpose, self::Left => 270,
            default => 0,
        };
    }

    /**
     * Whether the transformation swaps the width and the height.
     */
    public function swapsAxes(): bool
    {
        return 0 !== $this->rotation() % 180;
    }

    public function isIdentity(): bool
    {
        return self::Normal === $this;
    }
}
