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

use Symfony\UX\Image\Asset\SourceResolverInterface;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Fit;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Responsive\SizesResolver;
use Symfony\UX\Image\Transformation;

/**
 * Turns component props into a {@see RenderedImage} by asking the selected
 * provider for one variant URL per candidate.
 *
 * Two srcset strategies are used:
 *  - density-based, when the image has an explicit width and no "sizes": the
 *    candidates are that width multiplied by each density, described as "1x",
 *    "2x"... and no "sizes" attribute is rendered;
 *  - width-based otherwise: the candidates are the configured widths, described
 *    as "640w", "1280w"... alongside a "sizes" attribute.
 *
 * @author Symfony Community
 */
final class ImageRenderer
{
    /**
     * @param array{widths: list<int>, formats: list<string>, densities: list<float>, sizes: ?string, quality: ?int, fit: string, modifiers: array<string, scalar>} $defaults
     * @param array{loading: string, decoding: string}                                                                                                              $config
     */
    public function __construct(
        private readonly SourceResolverInterface $resolver,
        private readonly Providers $providers,
        private readonly SizesResolver $sizes,
        private readonly array $defaults,
        private readonly array $config,
    ) {
    }

    public function render(ImageComponent $component): RenderedImage
    {
        $source = $this->resolver->resolve($component->src);
        $provider = $this->providers->get($component->provider);
        $capabilities = $provider->capabilities();

        [$width, $height] = $this->dimensions($component, $source);

        $loading = $component->priority ? 'eager' : $this->config['loading'];
        $decoding = $component->priority ? 'sync' : $this->config['decoding'];
        $fetchpriority = $component->priority ? 'high' : null;

        $prototype = new Transformation(
            quality: $component->quality ?? $this->defaults['quality'],
            fit: Fit::from($component->fit ?? $this->defaults['fit']),
            modifiers: $component->modifiers ?? $this->defaults['modifiers'],
        );

        // Provider cannot resize (e.g. the bundled passthrough): single <img>.
        if (!$capabilities->canResize) {
            return new RenderedImage(
                src: $provider->transform($source, $prototype)->url,
                srcset: null,
                sizes: null,
                width: $width,
                height: $height,
                alt: $component->alt,
                loading: $loading,
                decoding: $decoding,
                fetchpriority: $fetchpriority,
            );
        }

        [$candidates, $sizes, $fallback] = $this->plan($component, $source);

        // sizes="auto" (alone, or as a prefix before a fallback list) is only
        // valid on a lazily loaded image; "priority" (or a global loading: eager)
        // would silently produce invalid markup.
        if (null !== $sizes && str_starts_with($sizes, SizesResolver::AUTO) && 'lazy' !== $loading) {
            throw new InvalidArgumentException(\sprintf('The "sizes" prop cannot use "auto" on an image loaded with loading="%s": the keyword is only valid on a lazily loaded image. Drop "priority" or give an explicit "sizes".', $loading));
        }

        $formats = $this->formats($component, $capabilities);

        [$fallbackWidth, $fallbackHeight] = $fallback;
        $src = $provider->transform($source, $prototype
            ->withWidth($fallbackWidth > 0 ? $capabilities->clampWidth($fallbackWidth) : null)
            ->withHeight($fallbackHeight),
        );

        $sources = [];
        foreach ($formats as $format) {
            $sources[] = new RenderedSource(
                srcset: $this->buildSrcset($provider, $capabilities, $source, $candidates, $prototype->withFormat($format)),
                type: $this->mimeType($format),
                sizes: $sizes,
            );
        }

        return new RenderedImage(
            src: $src->url,
            srcset: $this->buildSrcset($provider, $capabilities, $source, $candidates, $prototype) ?: null,
            sizes: $sizes,
            width: $width,
            height: $height,
            alt: $component->alt,
            loading: $loading,
            decoding: $decoding,
            fetchpriority: $fetchpriority,
            sources: $sources,
        );
    }

    /**
     * Renders the <source> elements of a <twig:ux:source>: one per requested
     * format, plus a last one without a "type" as the fallback for that media
     * condition.
     *
     * @return list<RenderedSource>
     */
    public function renderSource(SourceComponent $component): array
    {
        $source = $this->resolver->resolve($component->src);
        $provider = $this->providers->get($component->provider);
        $capabilities = $provider->capabilities();

        $prototype = new Transformation(
            quality: $component->quality ?? $this->defaults['quality'],
            fit: Fit::from($component->fit ?? $this->defaults['fit']),
            modifiers: $component->modifiers ?? $this->defaults['modifiers'],
        );

        // Chrome and Safari read these off the selected <source> to compute the
        // aspect ratio; without them they fall back to the <img> dimensions, which
        // is exactly what art direction changes.
        [$width, $height] = $this->dimensions($component, $source);

        if (!$capabilities->canResize) {
            return [new RenderedSource(
                srcset: $provider->transform($source, $prototype)->url,
                media: $component->media,
                width: $width,
                height: $height,
            )];
        }

        [$candidates, $sizes] = $this->plan($component, $source);

        $rendered = [];
        foreach ($this->formats($component, $capabilities) as $format) {
            $rendered[] = new RenderedSource(
                srcset: $this->buildSrcset($provider, $capabilities, $source, $candidates, $prototype->withFormat($format)),
                type: $this->mimeType($format),
                sizes: $sizes,
                media: $component->media,
                width: $width,
                height: $height,
            );
        }

        // Fallback for this media condition, in the source's original format.
        $rendered[] = new RenderedSource(
            srcset: $this->buildSrcset($provider, $capabilities, $source, $candidates, $prototype),
            sizes: $sizes,
            media: $component->media,
            width: $width,
            height: $height,
        );

        return $rendered;
    }

    /**
     * The dimensions to declare on the element. When only one side is given, the
     * other follows the source aspect ratio, otherwise the declared ratio would
     * be wrong.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(ImageComponent|SourceComponent $component, ImageSource $source): array
    {
        [$width, $height] = [$component->width, $component->height];

        if (null === $ratio = $source->dimensions) {
            return [$width, $height];
        }

        if (null === $width && null === $height) {
            return [$ratio->width, $ratio->height];
        }

        if (null === $height) {
            return [$width, $ratio->heightForWidth($width)];
        }

        if (null === $width) {
            return [(int) round($height * $ratio->aspectRatio()), $height];
        }

        return [$width, $height];
    }

    /**
     * Decides which variants to ask the provider for.
     *
     * @return array{0: list<array{0: int, 1: int|null, 2: string|null}>, 1: string|null, 2: array{0: int|null, 1: int|null}}
     */
    private function plan(ImageComponent|SourceComponent $component, ImageSource $source): array
    {
        $resolved = $this->sizes->resolve($component->sizes ?? $this->defaults['sizes']);
        $sizes = $resolved->sizes;
        $natural = $source->dimensions?->width;

        if (null !== $component->width && null === $sizes) {
            // Fixed-size image: describe the candidates by pixel density.
            $candidates = [];
            foreach ($component->densities ?? $this->defaults['densities'] as $density) {
                $candidates[] = [
                    (int) round($component->width * $density),
                    null !== $component->height ? (int) round($component->height * $density) : null,
                    $density.'x',
                ];
            }

            return [$candidates, $sizes, [$component->width, $component->height]];
        }

        // The widths implied by a breakpoint-based "sizes" beat the generic
        // defaults, but never an explicit "widths" prop.
        $widths = $this->capWidths($component->widths ?? ($resolved->widths ?: $this->defaults['widths']), $natural);
        $candidates = array_map(static fn (int $candidate): array => [$candidate, null, null], $widths);

        return [$candidates, $sizes, [$widths ? max($widths) : $natural, null]];
    }

    /**
     * A width descriptor "must match the natural width in the resource", so a
     * candidate wider than the source would lie: providers do not invent detail,
     * they would return the original. Candidates above the natural width are
     * dropped and replaced by the natural width itself, which also avoids paying
     * for upscaled variants that carry no more information.
     *
     * @param list<int> $widths
     *
     * @return list<int>
     */
    private function capWidths(array $widths, ?int $natural): array
    {
        if (null === $natural) {
            return $widths;
        }

        $capped = array_values(array_filter($widths, static fn (int $width): bool => $width <= $natural));

        if (\count($capped) !== \count($widths)) {
            $capped[] = $natural;
        }

        $capped = array_values(array_unique($capped));
        sort($capped);

        return $capped;
    }

    /**
     * @return list<string>
     */
    private function formats(ImageComponent|SourceComponent $component, ProviderCapabilities $capabilities): array
    {
        return array_values(array_filter(
            $component->formats ?? $this->defaults['formats'],
            static fn (string $format): bool => $capabilities->supportsFormat($format),
        ));
    }

    /**
     * @param list<array{0: int, 1: int|null, 2: string|null}> $candidates width, height and descriptor
     *                                                                     (a null descriptor means "<width>w")
     */
    private function buildSrcset(
        ImageProviderInterface $provider,
        ProviderCapabilities $capabilities,
        ImageSource $source,
        array $candidates,
        Transformation $prototype,
    ): string {
        $entries = [];
        $seen = [];

        foreach ($candidates as [$requested, $targetHeight, $descriptor]) {
            $clamped = $capabilities->clampWidth($requested);
            if (null === $clamped) {
                continue;
            }

            // Clamping can collapse several candidates onto the same width.
            $key = $descriptor ?? $clamped;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $generated = $provider->transform($source, $prototype->withWidth($clamped)->withHeight($targetHeight));

            $entries[] = $this->escapeCandidateUrl($generated->url).' '.($descriptor ?? ($generated->width ?? $clamped).'w');
        }

        return implode(', ', $entries);
    }

    /**
     * A srcset candidate URL "must not start or end with a U+002C COMMA": a
     * leading one is eaten as a separator, a trailing one swallows the following
     * candidate's descriptor. Providers build URLs freely, so both ends are
     * percent-encoded defensively.
     */
    private function escapeCandidateUrl(string $url): string
    {
        if (str_starts_with($url, ',')) {
            $url = '%2C'.substr($url, 1);
        }

        if (str_ends_with($url, ',')) {
            $url = substr($url, 0, -1).'%2C';
        }

        return $url;
    }

    private function mimeType(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'image/'.$format,
        };
    }
}
