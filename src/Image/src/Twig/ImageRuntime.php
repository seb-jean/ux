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

use Symfony\UX\Image\Image;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Source;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Providers $providers,
    ) {
    }

    /**
     * Called by the Twig Component renderer — receives all component props as $args,
     * separates known image options from extra HTML attributes.
     */
    public function render(array $args = []): string
    {
        $knownOptions = ['src', 'alt', 'width', 'height', 'loading', 'decoding', 'srcset', 'sizes', 'fetchpriority', 'provider', 'sources'];

        $options = array_intersect_key($args, array_flip($knownOptions));
        $attributes = array_diff_key($args, array_flip($knownOptions));

        $src = $options['src'] ?? throw new \InvalidArgumentException('The "src" option is required when using the UX:Image component.');
        unset($options['src']);

        return $this->renderImage($src, $options, $attributes);
    }

    /**
     * @param string                     $src
     * @param array{
     *     alt?: string,
     *     width?: int,
     *     height?: int,
     *     loading?: string,
     *     decoding?: string,
     *     srcset?: string,
     *     sizes?: string,
     *     fetchpriority?: string,
     *     provider?: string,
     *     sources?: array<array{srcset: string, type?: string, media?: string, sizes?: string, width?: int, height?: int}>,
     * }                                $options
     * @param array<string, string|bool> $attributes HTML attributes added to the <img> tag
     */
    public function renderImage(string $src, array $options = [], array $attributes = []): string
    {
        $image = new Image($src);

        if (isset($options['alt'])) {
            $image = $image->alt($options['alt']);
        }
        if (isset($options['width'])) {
            $image = $image->width($options['width']);
        }
        if (isset($options['height'])) {
            $image = $image->height($options['height']);
        }
        if (isset($options['loading'])) {
            $image = $image->loading($options['loading']);
        }
        if (isset($options['decoding'])) {
            $image = $image->decoding($options['decoding']);
        }
        if (isset($options['srcset'])) {
            $image = $image->srcset($options['srcset']);
        }
        if (isset($options['sizes'])) {
            $image = $image->sizes($options['sizes']);
        }
        if (isset($options['fetchpriority'])) {
            $image = $image->fetchpriority($options['fetchpriority']);
        }
        if (isset($options['provider'])) {
            $image = $image->provider($options['provider']);
        }
        if (isset($options['sources']) && \is_array($options['sources'])) {
            foreach ($options['sources'] as $sourceData) {
                $image = $image->addSource($this->buildSource($sourceData));
            }
        }

        return $this->providers->renderImage($image, $attributes);
    }

    /**
     * @param array{srcset: string, type?: string, media?: string, sizes?: string, width?: int, height?: int} $data
     */
    private function buildSource(array $data): Source
    {
        $source = Source::create($data['srcset']);

        if (isset($data['type'])) {
            $source = $source->type($data['type']);
        }
        if (isset($data['media'])) {
            $source = $source->media($data['media']);
        }
        if (isset($data['sizes'])) {
            $source = $source->sizes($data['sizes']);
        }
        if (isset($data['width'])) {
            $source = $source->width($data['width']);
        }
        if (isset($data['height'])) {
            $source = $source->height($data['height']);
        }

        return $source;
    }
}
