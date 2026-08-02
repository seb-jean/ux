<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\Image\Tests\Fixtures\ImageTestKernel;

class ImageComponentTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return ImageTestKernel::class;
    }

    public function testDefaultProviderRendersSingleImageWithDimensions(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="Hero" />');

        self::assertStringContainsString('<img', $html);
        self::assertStringContainsString('src="/images/hero.jpg"', $html);
        self::assertStringContainsString('alt="Hero"', $html);
        self::assertStringContainsString('width="1600"', $html);
        self::assertStringContainsString('height="900"', $html);
        self::assertStringContainsString('loading="lazy"', $html);
        self::assertStringContainsString('decoding="async"', $html);
        self::assertStringNotContainsString('<picture', $html);
        self::assertStringNotContainsString('srcset', $html);
    }

    public function testResizingProviderRendersPictureWithSources(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="Hero" provider="cdn" :widths="[640, 1280]" :formats="[\'avif\', \'webp\']" sizes="100vw" />');

        self::assertStringContainsString('<picture>', $html);
        self::assertStringContainsString('<source type="image/avif"', $html);
        self::assertStringContainsString('<source type="image/webp"', $html);
        self::assertStringContainsString('srcset="/images/hero.jpg?w=640&amp;f=avif 640w, /images/hero.jpg?w=1280&amp;f=avif 1280w"', $html);
        self::assertStringContainsString('sizes="100vw"', $html);
        self::assertStringContainsString('src="/images/hero.jpg?w=1280"', $html);
    }

    public function testPriorityImage(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="Hero" provider="cdn" priority />');

        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringContainsString('decoding="sync"', $html);
        self::assertStringContainsString('fetchpriority="high"', $html);
    }

    /**
     * Regression: this used to render sizes="100vw" and a srcset up to 1920w for
     * a 96px avatar.
     */
    public function testFixedSizeImageRendersADensitySrcset(): void
    {
        $html = $this->render('<twig:ux:img src="user/42.png" alt="…" width="96" height="96" provider="cdn" :formats="[]" />');

        self::assertStringContainsString('srcset="/user/42.png?w=96&amp;h=96 1x, /user/42.png?w=192&amp;h=192 2x"', $html);
        self::assertStringNotContainsString('sizes=', $html);
        self::assertStringNotContainsString('1920', $html);
    }

    public function testPresetAppliesItsProps(): void
    {
        $html = $this->render('<twig:ux:img src="user/42.png" alt="…" preset="avatar" :formats="[]" />');

        self::assertStringContainsString('width="96"', $html);
        self::assertStringContainsString('height="96"', $html);
        self::assertStringContainsString('1x, ', $html);
    }

    public function testAnExplicitPropOverridesThePreset(): void
    {
        $html = $this->render('<twig:ux:img src="user/42.png" alt="…" preset="avatar" width="48" :formats="[]" />');

        self::assertStringContainsString('width="48"', $html);
        self::assertStringContainsString('/user/42.png?w=48&amp;h=96 1x', $html);
    }

    public function testUnknownPresetThrows(): void
    {
        try {
            $this->render('<twig:ux:img src="user/42.png" alt="…" preset="nope" />');
            self::fail('Expected an exception for an unknown preset.');
        } catch (\Throwable $e) {
            $previous = $e->getPrevious() ?? $e;
            self::assertStringContainsString('Unknown image preset "nope"', $previous->getMessage());
        }
    }

    public function testBreakpointSizesAreExpanded(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="…" provider="cdn" sizes="100vw xl:400px" :formats="[]" />');

        self::assertStringContainsString('sizes="(min-width: 1280px) 400px, 100vw"', $html);
    }

    public function testArtDirectionWithPictureAndSources(): void
    {
        $html = $this->render(<<<'TWIG'
            <twig:ux:picture>
                <twig:ux:source media="(min-width: 1024px)" src="hero-wide-1920x600.jpg" provider="cdn" :widths="[1024, 1920]" :formats="['webp']" />
                <twig:ux:img src="hero-portrait.jpg" alt="Hero" provider="cdn" :widths="[320, 640]" :formats="[]" />
            </twig:ux:picture>
            TWIG);

        self::assertStringContainsString('<picture>', $html);
        // a source per format, then a fallback without "type", all sharing the media
        self::assertStringContainsString('<source media="(min-width: 1024px)" type="image/webp" srcset="/hero-wide-1920x600.jpg?w=1024&amp;f=webp 1024w, /hero-wide-1920x600.jpg?w=1920&amp;f=webp 1920w"', $html);
        self::assertStringContainsString('<source media="(min-width: 1024px)" srcset="/hero-wide-1920x600.jpg?w=1024 1024w, /hero-wide-1920x600.jpg?w=1920 1920w"', $html);
        // the img is the fallback and must not open a nested <picture>
        self::assertStringContainsString('<img src="/hero-portrait.jpg?w=640"', $html);
        self::assertSame(1, substr_count($html, '<picture'));
    }

    /**
     * Chrome and Safari read width/height off the selected <source> to compute the
     * aspect ratio. Without them they use the <img> dimensions, which is exactly
     * what art direction changes: the layout shift the package claims to prevent
     * came straight back.
     */
    public function testEachSourceCarriesItsOwnDimensions(): void
    {
        $html = $this->render(<<<'TWIG'
            <twig:ux:picture>
                <twig:ux:source media="(min-width: 1024px)" src="hero-1920x600.jpg" provider="cdn" :widths="[1920]" :formats="[]" />
                <twig:ux:img src="hero-600x900.jpg" alt="Hero" provider="cdn" :widths="[600]" :formats="[]" />
            </twig:ux:picture>
            TWIG);

        // the landscape source declares its own ratio...
        self::assertStringContainsString('width="1920" height="600"', $html);
        // ...while the portrait fallback keeps its own
        self::assertStringContainsString('width="600" height="900"', $html);
    }

    /**
     * Nested in a <twig:ux:picture>, the img is the fallback: it must not open a
     * second <picture>, but it still contributes the format sources for its own
     * variant, which apply when no media condition matched.
     */
    public function testAnImgNestedInAPictureDoesNotWrapItself(): void
    {
        $html = $this->render(<<<'TWIG'
            <twig:ux:picture>
                <twig:ux:img src="hero.jpg" alt="Hero" provider="cdn" :widths="[640]" />
            </twig:ux:picture>
            TWIG);

        self::assertSame(1, substr_count($html, '<picture'));
        self::assertStringContainsString('<source type="image/avif"', $html);
        self::assertStringContainsString('<source type="image/webp"', $html);
        // <source> must precede <img>
        self::assertLessThan(strpos($html, '<img'), strrpos($html, '<source'));
    }

    public function testAStandaloneImgStillWrapsItselfWhenFormatsAreRequested(): void
    {
        $html = $this->render('<twig:ux:img src="hero.jpg" alt="Hero" provider="cdn" :widths="[640]" :formats="[\'webp\']" />');

        self::assertStringContainsString('<picture>', $html);
        self::assertStringContainsString('<source type="image/webp"', $html);
    }

    public function testSizesAutoWithAFallbackList(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="…" provider="cdn" sizes="auto 100vw xl:400px" :formats="[]" />');

        self::assertStringContainsString('sizes="auto, (min-width: 1280px) 400px, 100vw"', $html);
        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testSizesAutoRejectedOnAPriorityImage(): void
    {
        try {
            $this->render('<twig:ux:img src="images/hero.jpg" alt="…" provider="cdn" sizes="auto" priority />');
            self::fail('Expected an exception for sizes="auto" on an eager image.');
        } catch (\Throwable $e) {
            $previous = $e->getPrevious() ?? $e;
            self::assertStringContainsString('only valid on a lazily loaded image', $previous->getMessage());
        }
    }

    /**
     * A <twig:ux:source> expands to one <source> per format; a unique attribute
     * such as "id" must not be repeated on each of them.
     */
    public function testExtraAttributesOnASourceAreRenderedOnce(): void
    {
        $html = $this->render(<<<'TWIG'
            <twig:ux:picture>
                <twig:ux:source media="(min-width: 1024px)" src="hero.jpg" id="wide" provider="cdn" :widths="[1024]" :formats="['avif', 'webp']" />
                <twig:ux:img src="hero.jpg" alt="Hero" provider="cdn" :widths="[640]" :formats="[]" />
            </twig:ux:picture>
            TWIG);

        self::assertGreaterThan(1, substr_count($html, '<source'));
        self::assertSame(1, substr_count($html, 'id="wide"'));
    }

    public function testExtraAttributesArePassedThrough(): void
    {
        $html = $this->render('<twig:ux:img src="images/hero.jpg" alt="Hero" class="rounded" id="hero" />');

        self::assertStringContainsString('class="rounded"', $html);
        self::assertStringContainsString('id="hero"', $html);
    }

    private function render(string $template): string
    {
        $twig = self::getContainer()->get('twig');

        return trim($twig->createTemplate($template)->render());
    }
}
