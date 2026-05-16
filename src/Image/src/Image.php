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
        private ?Transformation $transformation = null,
        private string $loading = 'lazy',
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

    public function transform(Transformation $transformation): self
    {
        $clone = clone $this;
        $clone->transformation = $transformation;

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

    public function getTransformation(): ?Transformation
    {
        return $this->transformation;
    }

    public function toArray(): array
    {
        $data = [
            'src' => $this->src,
            'loading' => $this->loading,
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
        if (null !== $this->transformation && !$this->transformation->isEmpty()) {
            $data['transformation'] = $this->transformation->toArray();
        }

        return $data;
    }
}
