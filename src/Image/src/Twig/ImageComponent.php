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

use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * The <twig:ux:img> component.
 *
 * @author Symfony Community
 */
final class ImageComponent
{
    use ImageProps;

    /** The alternative text; use "" for decorative images. */
    public string $alt = '';

    /** LCP image: eager loading, fetchpriority=high, synchronous decoding. */
    public bool $priority = false;

    private ?RenderedImage $rendered = null;

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(
        private readonly ImageRenderer $renderer,
        private readonly array $presets = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        return $this->mountImageProps($data, $this->presets);
    }

    public function getRendered(): RenderedImage
    {
        return $this->rendered ??= $this->renderer->render($this);
    }
}
