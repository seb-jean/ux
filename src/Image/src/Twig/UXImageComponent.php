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
 * Supports three syntaxes:
 *
 *   {{ ux_image('/img/photo.jpg', { transform: { width: 800, format: 'webp' } }) }}
 *
 *   {% component 'UX:Image' with { src: '/img/photo.jpg', transform: { width: 800, format: 'webp' } } %}
 *
 *   <twig:UX:Image src="/img/photo.jpg" :transform="{ width: 800, format: 'webp' }" />
 *
 * For <picture> support with multiple sources:
 *
 *   <twig:UX:Image src="/img/photo.jpg" :sources="[
 *       { srcset: '/img/photo.avif', type: 'image/avif' },
 *       { srcset: '/img/photo.webp', type: 'image/webp' },
 *   ]" />
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

    public ?string $decoding = null;

    public ?string $provider = null;

    /** @var array{width?: int, height?: int, format?: string, quality?: int, fit?: string}|null */
    public ?array $transform = null;

    /** @var array<array{srcset: string, type?: string, media?: string, sizes?: string}>|null */
    public ?array $sources = null;
}
