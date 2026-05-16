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
 * Supports two syntaxes:
 *
 *   {% component 'UX:Image' with { src: '/img/photo.jpg', alt: 'Photo', transform: { width: 800, format: 'webp' } } %}
 *
 *   <twig:UX:Image src="/img/photo.jpg" alt="Photo" />
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
}
