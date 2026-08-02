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
 * An immutable description of the variant a provider must produce.
 *
 * @author Symfony Community
 */
final class Transformation
{
    /**
     * @param array<string, scalar> $modifiers provider-specific options, passed through as-is
     */
    public function __construct(
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $format = null, // null => keep original / let the provider negotiate
        public readonly ?int $quality = null,   // null => provider default / auto
        public readonly Fit $fit = Fit::Contain,
        public readonly array $modifiers = [],
    ) {
    }

    public function withWidth(?int $width): self
    {
        return new self($width, $this->height, $this->format, $this->quality, $this->fit, $this->modifiers);
    }

    public function withHeight(?int $height): self
    {
        return new self($this->width, $height, $this->format, $this->quality, $this->fit, $this->modifiers);
    }

    public function withFormat(?string $format): self
    {
        return new self($this->width, $this->height, $format, $this->quality, $this->fit, $this->modifiers);
    }
}
