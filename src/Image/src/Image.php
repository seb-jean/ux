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
 * Represents an image to be rendered.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class Image
{
    public function __construct(
        private string $src,
        private ?string $alt = null,
        private ?int $width = null,
        private ?int $height = null,
        private string $loading = 'lazy',
        private string $decoding = 'async',
        private ?string $srcset = null,
        private ?string $sizes = null,
        private ?string $fetchpriority = null,
        private array $sources = [],
        private ?string $provider = null,
    ) {
    }

    public function src(string $src): self
    {
        $clone = clone $this;
        $clone->src = $src;

        return $clone;
    }

    public function alt(string $alt): self
    {
        $clone = clone $this;
        $clone->alt = $alt;

        return $clone;
    }

    public function width(int $width): self
    {
        $clone = clone $this;
        $clone->width = $width;

        return $clone;
    }

    public function height(int $height): self
    {
        $clone = clone $this;
        $clone->height = $height;

        return $clone;
    }

    /**
     * @param 'lazy'|'eager' $loading
     */
    public function loading(string $loading): self
    {
        $clone = clone $this;
        $clone->loading = $loading;

        return $clone;
    }

    /**
     * @param 'async'|'sync'|'auto' $decoding
     */
    public function decoding(string $decoding): self
    {
        $clone = clone $this;
        $clone->decoding = $decoding;

        return $clone;
    }

    public function srcset(string $srcset): self
    {
        $clone = clone $this;
        $clone->srcset = $srcset;

        return $clone;
    }

    public function sizes(string $sizes): self
    {
        $clone = clone $this;
        $clone->sizes = $sizes;

        return $clone;
    }

    /**
     * @param 'high'|'low'|'auto' $fetchpriority
     */
    public function fetchpriority(string $fetchpriority): self
    {
        $clone = clone $this;
        $clone->fetchpriority = $fetchpriority;

        return $clone;
    }

    public function addSource(Source $source): self
    {
        $clone = clone $this;
        $clone->sources[] = $source;

        return $clone;
    }

    /**
     * @param Source[] $sources
     */
    public function sources(array $sources): self
    {
        $clone = clone $this;
        $clone->sources = $sources;

        return $clone;
    }

    public function provider(string $provider): self
    {
        $clone = clone $this;
        $clone->provider = $provider;

        return $clone;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @return Source[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function hasSources(): bool
    {
        return [] !== $this->sources;
    }

    public function toArray(): array
    {
        $data = [
            'src' => $this->src,
            'loading' => $this->loading,
            'decoding' => $this->decoding,
        ];

        if (null !== $this->alt) {
            $data['alt'] = $this->alt;
        }
        if (null !== $this->width) {
            $data['width'] = $this->width;
        }
        if (null !== $this->height) {
            $data['height'] = $this->height;
        }
        if (null !== $this->srcset) {
            $data['srcset'] = $this->srcset;
        }
        if (null !== $this->sizes) {
            $data['sizes'] = $this->sizes;
        }
        if (null !== $this->fetchpriority) {
            $data['fetchpriority'] = $this->fetchpriority;
        }

        return $data;
    }
}
