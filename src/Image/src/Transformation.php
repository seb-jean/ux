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

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * Represents the set of transformations to apply to an image.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class Transformation
{
    private const SUPPORTED_FORMATS = ['webp', 'jpeg', 'png', 'avif'];
    private const SUPPORTED_FITS = ['contain', 'cover', 'fill', 'crop'];

    public function __construct(
        private ?int $width = null,
        private ?int $height = null,
        private ?string $format = null,
        private ?int $quality = null,
        private ?string $fit = null,
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public function width(int $width): self
    {
        if ($width < 1) {
            throw new InvalidArgumentException(\sprintf('The image width must be greater than 0, %d given.', $width));
        }

        $clone = clone $this;
        $clone->width = $width;

        return $clone;
    }

    public function height(int $height): self
    {
        if ($height < 1) {
            throw new InvalidArgumentException(\sprintf('The image height must be greater than 0, %d given.', $height));
        }

        $clone = clone $this;
        $clone->height = $height;

        return $clone;
    }

    /**
     * @param 'webp'|'jpeg'|'png'|'avif' $format
     */
    public function format(string $format): self
    {
        if (!\in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid format "%s". Supported formats are: %s.', $format, implode(', ', self::SUPPORTED_FORMATS)));
        }

        $clone = clone $this;
        $clone->format = $format;

        return $clone;
    }

    /**
     * @param int $quality Between 1 and 100
     */
    public function quality(int $quality): self
    {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException(\sprintf('Quality must be between 1 and 100, %d given.', $quality));
        }

        $clone = clone $this;
        $clone->quality = $quality;

        return $clone;
    }

    /**
     * @param 'contain'|'cover'|'fill'|'crop' $fit
     */
    public function fit(string $fit): self
    {
        if (!\in_array($fit, self::SUPPORTED_FITS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid fit "%s". Supported fits are: %s.', $fit, implode(', ', self::SUPPORTED_FITS)));
        }

        $clone = clone $this;
        $clone->fit = $fit;

        return $clone;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function getFit(): ?string
    {
        return $this->fit;
    }

    public function isEmpty(): bool
    {
        return null === $this->width
            && null === $this->height
            && null === $this->format
            && null === $this->quality
            && null === $this->fit;
    }

    public function toArray(): array
    {
        $data = [];

        if (null !== $this->width) {
            $data['width'] = $this->width;
        }
        if (null !== $this->height) {
            $data['height'] = $this->height;
        }
        if (null !== $this->format) {
            $data['format'] = $this->format;
        }
        if (null !== $this->quality) {
            $data['quality'] = $this->quality;
        }
        if (null !== $this->fit) {
            $data['fit'] = $this->fit;
        }

        return $data;
    }
}
