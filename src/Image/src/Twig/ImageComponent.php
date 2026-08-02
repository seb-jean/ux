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
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * The <twig:ux:img> component.
 *
 * @author Symfony Community
 */
final class ImageComponent
{
    use ImageProps;

    /**
     * The values each rendered attribute accepts, per the HTML spec.
     */
    private const ATTRIBUTE_VALUES = [
        'loading' => ['lazy', 'eager'],
        'decoding' => ['sync', 'async', 'auto'],
        'fetchpriority' => ['high', 'low', 'auto'],
    ];

    /** The alternative text; use "" for decorative images. */
    public string $alt = '';

    /** LCP image: eager loading, fetchpriority=high, synchronous decoding. */
    public bool $priority = false;

    /** "lazy" or "eager"; beats "priority" and the configured default. */
    public ?string $loading = null;

    /** "sync", "async" or "auto"; beats "priority" and the configured default. */
    public ?string $decoding = null;

    /** "high", "low" or "auto"; beats "priority", omitted altogether otherwise. */
    public ?string $fetchpriority = null;

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
        $data = $this->mountImageProps($data, $this->presets);

        foreach (self::ATTRIBUTE_VALUES as $prop => $allowed) {
            if (isset($data[$prop]) && !\in_array($data[$prop], $allowed, true)) {
                throw new InvalidArgumentException(\sprintf('The "%s" prop must be one of "%s"; "%s" given.', $prop, implode('", "', $allowed), $data[$prop]));
            }
        }

        return $data;
    }

    public function getRendered(): RenderedImage
    {
        return $this->rendered ??= $this->renderer->render($this);
    }
}
