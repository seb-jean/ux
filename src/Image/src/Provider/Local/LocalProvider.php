<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider\Local;

use Symfony\UX\Image\Image;
use Symfony\UX\Image\Provider\ProviderInterface;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class LocalProvider implements ProviderInterface
{
    public function renderImage(Image $image, array $attributes = []): string
    {
        $imageData = $image->toArray();

        $imgAttrs = ['src' => $image->getSrc()];
        foreach (['alt', 'width', 'height', 'loading', 'decoding'] as $key) {
            if (isset($imageData[$key]) && !isset($attributes[$key])) {
                $imgAttrs[$key] = $imageData[$key];
            }
        }
        foreach ($attributes as $k => $v) {
            if (!isset($imgAttrs[$k])) {
                $imgAttrs[$k] = $v;
            }
        }

        $imgTag = \sprintf('<img %s />', $this->buildAttrString($imgAttrs));

        if (!$image->hasSources()) {
            return $imgTag;
        }

        $sources = '';
        foreach ($image->getSources() as $source) {
            $sources .= \sprintf('<source %s>', $this->buildAttrString($source->toArray()));
        }

        return \sprintf('<picture>%s%s</picture>', $sources, $imgTag);
    }

    private function buildAttrString(array $attrs): string
    {
        return implode(' ', array_map(
            static fn (string $k, mixed $v) => \sprintf('%s="%s"', $k, htmlspecialchars((string) $v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')),
            array_keys($attrs),
            $attrs,
        ));
    }

    public function __toString(): string
    {
        return 'local';
    }
}
