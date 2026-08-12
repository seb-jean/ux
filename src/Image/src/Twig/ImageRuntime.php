<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Twig;

use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\ImageTransformer;
use Symfony\UX\Image\Source\ImageSourceResolver;
use Symfony\UX\Image\Transformation\TransformationFactory;
use Symfony\UX\Image\Url\ImageUrlGenerator;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @internal
 */
final class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ImageSourceResolver $sourceResolver,
        private readonly TransformationFactory $transformationFactory,
        private readonly ImageTransformer $transformer,
        private readonly ImageUrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed>|string|null $transformations a map of transformations, or a preset name
     * @param array<string, mixed>             $attributes      additional attributes for the <img> tag
     */
    public function renderImage(string $src, array|string|null $transformations = null, array $attributes = []): string
    {
        $source = $this->sourceResolver->resolve($src);
        $transformations = $this->transformationFactory->create($transformations);

        $defaults = [
            'src' => $this->urlGenerator->generate($source, $transformations),
            'alt' => '',
        ];

        // the dimensions are computed, never measured on the generated file: the
        // browser gets them without anything being transformed at render time
        if (null !== $source->size()) {
            $displaySize = $this->transformer->plan($source, $transformations)->displaySize;

            $defaults['width'] = $displaySize->width;
            $defaults['height'] = $displaySize->height;
        }

        $defaults['loading'] = 'lazy';
        $defaults['decoding'] = 'async';

        return \sprintf('<img%s>', self::renderAttributes(array_merge($defaults, $attributes)));
    }

    /**
     * @param array<string, mixed>|string|null $transformations a map of transformations, or a preset name
     */
    public function generateUrl(string $src, array|string|null $transformations = null): string
    {
        return $this->urlGenerator->generate(
            $this->sourceResolver->resolve($src),
            $this->transformationFactory->create($transformations),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function renderAttributes(array $attributes): string
    {
        $rendered = '';

        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value) {
                continue;
            }

            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new InvalidArgumentException(\sprintf('The "%s" attribute of an image must be a scalar, got "%s".', $name, get_debug_type($value)));
            }

            $rendered .= true === $value
                ? ' '.htmlspecialchars((string) $name, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
                : \sprintf(' %s="%s"', htmlspecialchars((string) $name, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
        }

        return $rendered;
    }
}
