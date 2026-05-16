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
 * Represents a <source> element inside a <picture> tag.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class Source
{
    public function __construct(
        private readonly string $srcset,
        private readonly ?string $type = null,
        private readonly ?string $media = null,
        private readonly ?string $sizes = null,
        private readonly ?int $width = null,
        private readonly ?int $height = null,
    ) {
    }

    public static function create(string $srcset): self
    {
        return new self($srcset);
    }

    public function type(string $type): self
    {
        return new self($this->srcset, $type, $this->media, $this->sizes, $this->width, $this->height);
    }

    public function media(string $media): self
    {
        return new self($this->srcset, $this->type, $media, $this->sizes, $this->width, $this->height);
    }

    public function sizes(string $sizes): self
    {
        return new self($this->srcset, $this->type, $this->media, $sizes, $this->width, $this->height);
    }

    public function width(int $width): self
    {
        return new self($this->srcset, $this->type, $this->media, $this->sizes, $width, $this->height);
    }

    public function height(int $height): self
    {
        return new self($this->srcset, $this->type, $this->media, $this->sizes, $this->width, $height);
    }

    /**
     * Returns only non-null attribute values.
     *
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        $data = ['srcset' => $this->srcset];

        if (null !== $this->type) {
            $data['type'] = $this->type;
        }
        if (null !== $this->media) {
            $data['media'] = $this->media;
        }
        if (null !== $this->sizes) {
            $data['sizes'] = $this->sizes;
        }
        if (null !== $this->width) {
            $data['width'] = $this->width;
        }
        if (null !== $this->height) {
            $data['height'] = $this->height;
        }

        return $data;
    }
}
