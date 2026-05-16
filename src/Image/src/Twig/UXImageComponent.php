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

/**
 * Twig Component for rendering images.
 *
 * Three syntaxes are supported:
 *
 *   1. Twig function:
 *      {{ ux_image('/img/photo.jpg', { transform: { width: 800, format: 'webp' } }) }}
 *
 *   2. {% component %} tag:
 *      {% component 'UX:Image' with { src: '/img/photo.jpg',
 *          transform: { width: 800, format: 'webp' } } %}
 *
 *   3. <twig:> HTML tag — two equivalent forms:
 *
 *      a) Array expression with the ":" prefix (evaluated as Twig expression):
 *         <twig:UX:Image src="/img/photo.jpg" :transform="{ width: 800, format: 'webp' }" />
 *
 *      b) Flat scalar attributes (one per transformation option):
 *         <twig:UX:Image src="/img/photo.jpg"
 *             transform-width="800" transform-format="webp"
 *             transform-quality="85" transform-fit="cover" />
 *
 * Any extra attribute is forwarded as an HTML attribute on the rendered <img>.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class UXImageComponent
{
    public string $src;

    public ?string $alt = null;

    public ?int $width = null;

    public ?int $height = null;

    public ?string $loading = null;

    public ?string $provider = null;

    /** @var array{width?: int, height?: int, format?: string, quality?: int, fit?: string}|null */
    public ?array $transform = null;

    // Flat transform-* attributes for the <twig:UX:Image> HTML syntax (form b above)
    public ?int $transformWidth = null;

    public ?int $transformHeight = null;

    public ?string $transformFormat = null;

    public ?int $transformQuality = null;

    public ?string $transformFit = null;
}
