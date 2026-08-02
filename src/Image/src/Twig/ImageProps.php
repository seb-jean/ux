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
use Symfony\UX\Image\Fit;

/**
 * The props shared by <twig:ux:img> and <twig:ux:source>: everything describing
 * which variants to generate, as opposed to how to render the element.
 *
 * @author Symfony Community
 */
trait ImageProps
{
    /** The source image (asset path, public id or URL). */
    public string $src;

    /** Intrinsic width; read from the source when omitted. */
    public ?int $width = null;

    /** Intrinsic height; read from the source when omitted. */
    public ?int $height = null;

    /** The "sizes" attribute; named breakpoints are allowed ("100vw md:50vw"). */
    public ?string $sizes = null;

    /** @var int[]|null Candidate widths for the srcset */
    public ?array $widths = null;

    /** @var string[]|null Modern formats (e.g. ["avif", "webp"]) */
    public ?array $formats = null;

    /** @var float[]|null Pixel densities for fixed-size images (width given, no sizes) */
    public ?array $densities = null;

    /** One of "contain", "cover", "fill"; falls back to the configured default. */
    public ?string $fit = null;

    /** Compression quality (1-100); provider default/auto when null. */
    public ?int $quality = null;

    /** @var array<string, scalar>|null Provider-specific options */
    public ?array $modifiers = null;

    /** Named provider to use; the default provider when null. */
    public ?string $provider = null;

    /** Name of a configured preset to apply. */
    public ?string $preset = null;

    /**
     * @param array<string, mixed>                $data
     * @param array<string, array<string, mixed>> $presets
     *
     * @return array<string, mixed>
     */
    private function mountImageProps(array $data, array $presets): array
    {
        if (isset($data['preset'])) {
            if (!isset($presets[$data['preset']])) {
                throw new InvalidArgumentException(\sprintf('Unknown image preset "%s". Configured presets: "%s".', $data['preset'], implode('", "', array_keys($presets))));
            }

            // "+=" keeps the keys already present, so an explicitly passed prop
            // always wins over the preset.
            $data += $presets[$data['preset']];
        }

        if (isset($data['fit']) && null === Fit::tryFrom($data['fit'])) {
            throw new InvalidArgumentException(\sprintf('The "fit" prop must be one of "contain", "cover", "fill"; "%s" given.', $data['fit']));
        }

        foreach (['widths', 'formats', 'densities'] as $key) {
            if (isset($data[$key]) && !\is_array($data[$key])) {
                $data[$key] = [$data[$key]];
            }
        }

        return $data;
    }
}
