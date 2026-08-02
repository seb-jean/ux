<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Fit;
use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\Local\LocalImageProviderFactory;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Responsive\SizesResolver;
use Symfony\UX\Image\Tests\Fixtures\FakeProvider;
use Symfony\UX\Image\Tests\Fixtures\StaticSourceResolver;
use Symfony\UX\Image\Transformation;
use Symfony\UX\Image\Twig\ImageComponent;
use Symfony\UX\Image\Twig\ImageRenderer;
use Symfony\UX\Image\Twig\SourceComponent;

final class ImageRendererTest extends TestCase
{
    private const SCREENS = ['xs' => 320, 'sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, 'xxl' => 1536];

    public function testResizingProviderBuildsSrcsetAndPictureSources(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640, 1280], formats: ['avif', 'webp']),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer, src: 'images/hero.jpg', alt: 'Hero');
        $rendered = $renderer->render($component);

        self::assertSame('Hero', $rendered->alt);
        self::assertSame('/images/hero.jpg?w=1280', $rendered->src);
        self::assertSame('/images/hero.jpg?w=640 640w, /images/hero.jpg?w=1280 1280w', $rendered->srcset);
        self::assertCount(2, $rendered->sources);
        self::assertSame('image/avif', $rendered->sources[0]->type);
        self::assertSame('/images/hero.jpg?w=640&f=avif 640w, /images/hero.jpg?w=1280&f=avif 1280w', $rendered->sources[0]->srcset);
        self::assertSame('image/webp', $rendered->sources[1]->type);
        // dimensions come from the resolved source
        self::assertSame(1600, $rendered->width);
        self::assertSame(900, $rendered->height);
    }

    public function testPriorityFlipsLoadingDecodingAndFetchpriority(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer, priority: true));

        self::assertSame('eager', $rendered->loading);
        self::assertSame('sync', $rendered->decoding);
        self::assertSame('high', $rendered->fetchpriority);
    }

    public function testExplicitHintsBeatBothPriorityAndTheConfiguredDefaults(): void
    {
        $renderer = $this->renderer($this->defaults(formats: []));

        $component = $this->component($renderer, priority: true);
        $component->loading = 'lazy';
        $component->decoding = 'async';
        $component->fetchpriority = 'low';

        $rendered = $renderer->render($component);

        self::assertSame('lazy', $rendered->loading);
        self::assertSame('async', $rendered->decoding);
        self::assertSame('low', $rendered->fetchpriority);
    }

    public function testTheConfiguredFitIsUsedWhenThePropIsNotSet(): void
    {
        $provider = new FakeProvider();
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [640], formats: []), 'fit' => 'cover'],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $renderer->render($this->component($renderer));

        self::assertSame(Fit::Cover, $provider->lastTransformation?->fit);
    }

    public function testThePropOverridesTheConfiguredFit(): void
    {
        $provider = new FakeProvider();
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [640], formats: []), 'fit' => 'cover'],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->fit = 'fill';
        $renderer->render($component);

        self::assertSame(Fit::Fill, $provider->lastTransformation?->fit);
    }

    /**
     * Regression: a fixed-size image used to inherit defaults.widths (320-1920)
     * and sizes="100vw", making a 96px avatar download a 1920w image.
     */
    public function testAFixedSizeImageUsesDensitiesInsteadOfWidths(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(formats: []), 'sizes' => null],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer, src: 'user/42.png');
        $component->width = 96;
        $component->height = 96;
        $rendered = $renderer->render($component);

        self::assertSame('/user/42.png?w=96&h=96', $rendered->src);
        self::assertSame('/user/42.png?w=96&h=96 1x, /user/42.png?w=192&h=192 2x', $rendered->srcset);
        self::assertNull($rendered->sizes);
        self::assertSame(96, $rendered->width);
    }

    /**
     * A width given alone must not be paired with the raw intrinsic height, which
     * would declare a distorted aspect ratio.
     */
    public function testTheMissingSideFollowsTheSourceAspectRatio(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(), // 1600x900
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(formats: []), 'sizes' => null],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->width = 200;
        $rendered = $renderer->render($component);

        self::assertSame(200, $rendered->width);
        self::assertSame(113, $rendered->height);
    }

    public function testAnExplicitSizesKeepsTheWidthBasedSrcset(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [640, 1280], formats: []), 'sizes' => null],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->width = 96;
        $component->sizes = '50vw';
        $rendered = $renderer->render($component);

        self::assertSame('50vw', $rendered->sizes);
        self::assertStringContainsString('640w', (string) $rendered->srcset);
    }

    public function testBreakpointSizesDeriveTheCandidateWidths(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [9999], formats: []), 'sizes' => null],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->sizes = '100vw xl:400px';
        $rendered = $renderer->render($component);

        self::assertSame('(min-width: 1280px) 400px, 100vw', $rendered->sizes);
        // derived from the breakpoints, not from defaults.widths
        self::assertSame('/images/hero.jpg?w=320 320w, /images/hero.jpg?w=400 400w', $rendered->srcset);
    }

    public function testAnExplicitWidthsPropBeatsTheDerivedOnes(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(formats: []), 'sizes' => null],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->sizes = '100vw xl:400px';
        $component->widths = [800];
        $rendered = $renderer->render($component);

        self::assertSame('/images/hero.jpg?w=800 800w', $rendered->srcset);
    }

    public function testModifiersReachTheProvider(): void
    {
        $provider = new FakeProvider();
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640], formats: []),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer);
        $component->modifiers = ['e' => 'grayscale'];
        $renderer->render($component);

        self::assertSame(['e' => 'grayscale'], $provider->lastTransformation?->modifiers);
    }

    public function testSizesAutoIsAllowedOnALazyImage(): void
    {
        $rendered = $this->renderer($this->defaults(formats: []))->render(
            $this->componentWithSizes('auto', priority: false),
        );

        self::assertSame('auto', $rendered->sizes);
        self::assertSame('lazy', $rendered->loading);
    }

    /**
     * The "auto" keyword is only valid on a lazily loaded image, so combining it
     * with "priority" would silently emit invalid markup.
     */
    public function testSizesAutoOnAnEagerImageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only valid on a lazily loaded image');

        $this->renderer($this->defaults(formats: []))->render(
            $this->componentWithSizes('auto', priority: true),
        );
    }

    /**
     * A <source> resolves "auto" against the <img> that follows it, so it is only
     * valid when images are loaded lazily.
     */
    public function testSizesAutoOnASourceIsAllowedWhenLoadingIsLazy(): void
    {
        $renderer = $this->renderer($this->defaults(formats: []), loading: 'lazy');
        $rendered = $renderer->renderSource($this->sourceComponent($renderer, 'auto'));

        self::assertSame('auto', $rendered[0]->sizes);
    }

    public function testSizesAutoOnASourceThrowsWhenLoadingIsEager(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only valid on a lazily loaded image');

        $renderer = $this->renderer($this->defaults(formats: []), loading: 'eager');
        $renderer->renderSource($this->sourceComponent($renderer, 'auto'));
    }

    /**
     * A candidate is read up to the first ASCII whitespace, so a space inside the
     * URL would truncate it and turn its tail into bogus descriptors.
     */
    public function testWhitespaceInAProviderUrlIsEscaped(): void
    {
        $provider = new class implements ImageProviderInterface {
            public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
            {
                return new GeneratedImage($source->url."/l_text:Arial_60:Hello World\t".$transformation->width, $transformation->width);
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(formats: [], widths: null, canResize: true);
            }
        };

        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640], formats: []),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer));

        self::assertSame('/images/hero.jpg/l_text:Arial_60:Hello%20World%09640 640w', $rendered->srcset);
    }

    /**
     * "There must not be an image candidate string for an element that has the
     * same width descriptor value as another": a provider reporting the width it
     * actually serves can collapse two requests onto one descriptor.
     */
    public function testCandidatesCollapsingOntoOneWidthEmitASingleDescriptor(): void
    {
        $provider = new class implements ImageProviderInterface {
            public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
            {
                // Only serves multiples of 100, and says so.
                $served = 100 * intdiv((int) $transformation->width, 100);

                return new GeneratedImage($source->url.'?w='.$served, $served);
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(formats: [], widths: null, canResize: true);
            }
        };

        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640, 660, 1280], formats: []),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer));

        self::assertSame('/images/hero.jpg?w=600 600w, /images/hero.jpg?w=1200 1200w', $rendered->srcset);
    }

    /**
     * A URL ending with a comma would be swallowed by the srcset parser, taking
     * the next candidate's descriptor with it.
     */
    public function testATrailingCommaInAProviderUrlIsEscaped(): void
    {
        $provider = new class implements ImageProviderInterface {
            public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
            {
                return new GeneratedImage($source->url.'/w_'.$transformation->width.',', $transformation->width);
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(formats: [], widths: null, canResize: true);
            }
        };

        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640], formats: []),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer));

        self::assertSame('/images/hero.jpg/w_640%2C 640w', $rendered->srcset);
    }

    /**
     * The spec defines the density descriptor as a floating-point number, and its
     * own example uses "1.5x".
     */
    public function testFractionalDensities(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(formats: []), 'sizes' => null, 'densities' => [1, 1.5, 2]],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $component = $this->component($renderer, src: 'user/100x100.png');
        $component->width = 100;
        $rendered = $renderer->render($component);

        self::assertSame(
            '/user/100x100.png?w=100 1x, /user/100x100.png?w=150 1.5x, /user/100x100.png?w=200 2x',
            $rendered->srcset,
        );
    }

    /**
     * A candidate URL "must not start or end with a U+002C COMMA": a leading one
     * is eaten as a separator, a trailing one swallows the next descriptor.
     */
    public function testALeadingCommaInAProviderUrlIsEscaped(): void
    {
        $provider = new class implements ImageProviderInterface {
            public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
            {
                return new GeneratedImage(','.$source->url, $transformation->width);
            }

            public function capabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(formats: [], widths: null, canResize: true);
            }
        };

        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers($provider, 'fake://x'),
            new SizesResolver(self::SCREENS),
            $this->defaults(widths: [640], formats: []),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        self::assertSame('%2C/images/hero.jpg 640w', $renderer->render($this->component($renderer))->srcset);
    }

    /**
     * A width descriptor "must match the natural width in the resource": asking a
     * provider for more pixels than the source has returns the original, so the
     * descriptor would lie. Oversized candidates collapse onto the natural width.
     */
    public function testCandidatesAreCappedAtTheSourceNaturalWidth(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [320, 640, 1280, 1920], formats: []), 'sizes' => '100vw'],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer, src: 'photo-800x600.jpg'));

        self::assertSame(
            '/photo-800x600.jpg?w=320 320w, /photo-800x600.jpg?w=640 640w, /photo-800x600.jpg?w=800 800w',
            $rendered->srcset,
        );
    }

    public function testCappingKeepsAtLeastTheNaturalWidth(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            [...$this->defaults(widths: [1280, 1920], formats: []), 'sizes' => '100vw'],
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        // every candidate is wider than this 200px source
        $rendered = $renderer->render($this->component($renderer, src: 'icon-200x200.png'));

        self::assertSame('/icon-200x200.png?w=200 200w', $rendered->srcset);
    }

    public function testDefaultProviderEmitsSingleImageWithoutSrcset(): void
    {
        $renderer = new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(null, 'default://local'),
            new SizesResolver(self::SCREENS),
            $this->defaults(),
            ['loading' => 'lazy', 'decoding' => 'async'],
        );

        $rendered = $renderer->render($this->component($renderer));

        self::assertSame('/images/hero.jpg', $rendered->src);
        self::assertNull($rendered->srcset);
        self::assertSame([], $rendered->sources);
        self::assertSame(1600, $rendered->width);
        self::assertSame(900, $rendered->height);
    }

    private function renderer(array $defaults, string $loading = 'lazy'): ImageRenderer
    {
        return new ImageRenderer(
            new StaticSourceResolver(),
            $this->providers(new FakeProvider(), 'fake://x'),
            new SizesResolver(self::SCREENS),
            $defaults,
            ['loading' => $loading, 'decoding' => 'async'],
        );
    }

    private function sourceComponent(ImageRenderer $renderer, string $sizes): SourceComponent
    {
        $component = new SourceComponent($renderer);
        $component->src = 'images/hero.jpg';
        $component->media = '(min-width: 1024px)';
        $component->sizes = $sizes;

        return $component;
    }

    private function componentWithSizes(string $sizes, bool $priority): ImageComponent
    {
        $component = new ImageComponent($this->renderer($this->defaults()));
        $component->src = 'images/hero.jpg';
        $component->alt = 'Hero';
        $component->sizes = $sizes;
        $component->priority = $priority;

        return $component;
    }

    private function providers(?ImageProviderInterface $provider, string $dsn): Providers
    {
        $factory = null === $provider
            ? new LocalImageProviderFactory()
            : new class($provider) implements ImageProviderFactoryInterface {
                public function __construct(private readonly ImageProviderInterface $provider)
                {
                }

                public function create(Dsn $dsn): ImageProviderInterface
                {
                    return $this->provider;
                }

                public function supports(Dsn $dsn): bool
                {
                    return true;
                }
            };

        return new Providers(['default' => $dsn], [$factory], 'default');
    }

    /**
     * @param list<int>    $widths
     * @param list<string> $formats
     *
     * @return array{widths: list<int>, formats: list<string>, densities: list<int>, sizes: ?string, quality: ?int, fit: string, modifiers: array<string, scalar>}
     */
    private function defaults(array $widths = [640, 1280], array $formats = ['avif', 'webp']): array
    {
        return [
            'widths' => $widths,
            'formats' => $formats,
            'densities' => [1, 2],
            'sizes' => '100vw',
            'quality' => null,
            'fit' => 'contain',
            'modifiers' => [],
        ];
    }

    private function component(ImageRenderer $renderer, string $src = 'images/hero.jpg', string $alt = 'Hero', bool $priority = false): ImageComponent
    {
        $component = new ImageComponent($renderer);
        $component->src = $src;
        $component->alt = $alt;
        $component->priority = $priority;

        return $component;
    }
}
