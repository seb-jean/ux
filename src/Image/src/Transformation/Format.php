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
 * The output formats supported by UX Image.
 *
 * @see https://www.fastly.com/documentation/reference/io/format/
 */
enum Format: string
{
    case Avif = 'avif';
    case Gif = 'gif';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Webp = 'webp';

    public static function tryFromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'avif' => self::Avif,
            'gif' => self::Gif,
            'jpg', 'jpeg', 'jpe' => self::Jpeg,
            'png' => self::Png,
            'webp' => self::Webp,
            default => null,
        };
    }

    public static function tryFromPath(string $path): ?self
    {
        return self::tryFromExtension(pathinfo($path, \PATHINFO_EXTENSION));
    }

    public static function tryFromMimeType(string $mimeType): ?self
    {
        return match (strtolower($mimeType)) {
            'image/avif' => self::Avif,
            'image/gif' => self::Gif,
            'image/jpeg', 'image/jpg' => self::Jpeg,
            'image/png' => self::Png,
            'image/webp' => self::Webp,
            default => null,
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Avif => 'image/avif',
            self::Gif => 'image/gif',
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            default => $this->value,
        };
    }

    /**
     * Whether the "quality" transformation applies to this format.
     */
    public function isLossy(): bool
    {
        return match ($this) {
            self::Avif, self::Jpeg, self::Webp => true,
            self::Gif, self::Png => false,
        };
    }

    public function supportsAlpha(): bool
    {
        return self::Jpeg !== $this;
    }
}
