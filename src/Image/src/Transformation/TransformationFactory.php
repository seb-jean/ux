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
 * Builds transformations, applying the bundle configuration.
 *
 * Parsing lives in {@see Transformations}; policy — defaults, presets and the
 * limits a request may not exceed — lives here.
 */
final class TransformationFactory
{
    /** @var list<Format> */
    private readonly array $auto;

    /** @var list<Format> */
    private readonly array $allowedFormats;

    /**
     * @param list<string>                        $auto
     * @param list<string>                        $allowedFormats
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(
        private readonly int $quality = 85,
        private readonly int $maxWidth = 4000,
        private readonly int $maxHeight = 4000,
        array $auto = [],
        array $allowedFormats = [],
        private readonly array $presets = [],
    ) {
        $this->auto = array_map(static fn (string $format) => Format::from($format), $auto);
        $this->allowedFormats = [] === $allowedFormats ? Format::cases() : array_map(static fn (string $format) => Format::from($format), $allowedFormats);
    }

    /**
     * @param array<string, mixed>|string|null $transformations a map of transformations, or a preset name
     */
    public function create(array|string|null $transformations): Transformations
    {
        if (\is_string($transformations)) {
            if (!isset($this->presets[$transformations])) {
                throw new InvalidArgumentException(\sprintf('The image preset "%s" is not configured.%s', $transformations, [] === $this->presets ? '' : ' Available presets: "'.implode('", "', array_keys($this->presets)).'".'));
            }

            $transformations = $this->presets[$transformations];
        }

        return $this->apply(Transformations::fromArray($transformations ?? []));
    }

    /**
     * Rebuilds transformations from an image URL.
     *
     * The URL is signed, but the limits are enforced again: a leaked signature
     * must not become a way to ask for arbitrarily large images.
     *
     * @param array<string, mixed> $query
     */
    public function fromQuery(array $query): Transformations
    {
        unset($query['v'], $query['_hash']);

        return $this->apply(Transformations::fromArray($query));
    }

    /**
     * @return list<Format>
     */
    public function allowedFormats(): array
    {
        return $this->allowedFormats;
    }

    private function apply(Transformations $transformations): Transformations
    {
        if (null !== $transformations->width && $transformations->width > $this->maxWidth) {
            throw new InvalidArgumentException(\sprintf('The "width" transformation cannot exceed %d, got %d. Raise "ux_image.max_width" if this is intended.', $this->maxWidth, $transformations->width));
        }

        if (null !== $transformations->height && $transformations->height > $this->maxHeight) {
            throw new InvalidArgumentException(\sprintf('The "height" transformation cannot exceed %d, got %d. Raise "ux_image.max_height" if this is intended.', $this->maxHeight, $transformations->height));
        }

        if (null !== $transformations->format && !\in_array($transformations->format, $this->allowedFormats, true)) {
            throw new InvalidArgumentException(\sprintf('The "%s" output format is not allowed, expected one of: "%s".', $transformations->format->value, implode('", "', array_column($this->allowedFormats, 'value'))));
        }

        foreach ($transformations->auto as $format) {
            if (!\in_array($format, $this->allowedFormats, true)) {
                throw new InvalidArgumentException(\sprintf('The "auto" transformation cannot negotiate "%s", it is not an allowed format.', $format->value));
            }
        }

        if (null === $transformations->quality) {
            $transformations = $transformations->withQuality($this->quality);
        }

        if ([] === $transformations->auto && null === $transformations->format && [] !== $this->auto) {
            $transformations = $transformations->withAuto($this->auto);
        }

        return $transformations;
    }
}
