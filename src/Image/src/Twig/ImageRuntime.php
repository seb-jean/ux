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
use Symfony\UX\Image\Transformation;
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
        $knownOptions = ['src', 'alt', 'width', 'height', 'loading', 'provider', 'transform'];

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
     *     provider?: string,
     *     transform?: array{
     *         width?: int,
     *         height?: int,
     *         format?: string,
     *         quality?: int,
     *         fit?: string,
     *     },
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
        if (isset($options['provider'])) {
            $image = $image->provider($options['provider']);
        }
        if (isset($options['transform']) && \is_array($options['transform'])) {
            $image = $image->transform($this->buildTransformation($options['transform']));
        }

        return $this->providers->renderImage($image, $attributes);
    }

    /**
     * @param array{width?: int, height?: int, format?: string, quality?: int, fit?: string} $data
     */
    private function buildTransformation(array $data): Transformation
    {
        $t = Transformation::create();

        if (isset($data['width'])) {
            $t = $t->width($data['width']);
        }
        if (isset($data['height'])) {
            $t = $t->height($data['height']);
        }
        if (isset($data['format'])) {
            $t = $t->format($data['format']);
        }
        if (isset($data['quality'])) {
            $t = $t->quality($data['quality']);
        }
        if (isset($data['fit'])) {
            $t = $t->fit($data['fit']);
        }

        return $t;
    }
}
