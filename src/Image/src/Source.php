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
    ) {
    }

    public static function create(string $srcset): self
    {
        return new self($srcset);
    }

    public function type(string $type): self
    {
        return new self($this->srcset, $type, $this->media, $this->sizes);
    }

    public function media(string $media): self
    {
        return new self($this->srcset, $this->type, $media, $this->sizes);
    }

    public function sizes(string $sizes): self
    {
        return new self($this->srcset, $this->type, $this->media, $sizes);
    }

    /**
     * Returns only non-null attribute values.
     *
     * @return array<string, string>
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

        return $data;
    }
}
