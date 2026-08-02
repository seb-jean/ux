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
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Responsive\SizesResolver;

final class SizesResolverTest extends TestCase
{
    private const SCREENS = ['xs' => 320, 'sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, 'xxl' => 1536];

    public function testNullSizes(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve(null);

        self::assertNull($resolved->sizes);
        self::assertSame([], $resolved->widths);
    }

    public function testABareSizeIsPassedThroughAndDerivesNoWidth(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('100vw');

        self::assertSame('100vw', $resolved->sizes);
        // Without a breakpoint there is no layout information to derive from.
        self::assertSame([], $resolved->widths);
    }

    /**
     * The first matching media condition wins, so breakpoints must be rendered
     * from the widest to the narrowest.
     */
    public function testBreakpointsAreRenderedWidestFirst(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('100vw md:50vw xl:400px');

        self::assertSame('(min-width: 1280px) 400px, (min-width: 768px) 50vw, 100vw', $resolved->sizes);
    }

    public function testDeclarationOrderDoesNotMatter(): void
    {
        $resolver = new SizesResolver(self::SCREENS);

        self::assertSame(
            $resolver->resolve('xl:400px md:50vw 100vw')->sizes,
            $resolver->resolve('100vw md:50vw xl:400px')->sizes,
        );
    }

    public function testWidthsAreDerivedFromTheBreakpoints(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('100vw md:50vw xl:400px');

        // 400px literal, 768 * 50% = 384, and the 100vw fallback at the
        // narrowest screen (320).
        self::assertSame([320, 384, 400], $resolved->widths);
    }

    public function testUnresolvableSizesDeriveNoWidth(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('md:calc(50vw - 2rem)');

        self::assertSame('(min-width: 768px) calc(50vw - 2rem)', $resolved->sizes);
        self::assertSame([], $resolved->widths);
    }

    /**
     * The "auto" keyword defers the choice to the browser's layout, so no width
     * can be derived from it.
     */
    public function testTheAutoKeywordIsPassedThroughWithoutDerivingWidths(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('auto');

        self::assertSame('auto', $resolved->sizes);
        self::assertSame([], $resolved->widths);
    }

    /**
     * The spec shows "sizes=auto, (max-width: 30em) 100vw, …": the list after the
     * keyword is the fallback for browsers that don't support it.
     */
    public function testAutoCanPrefixAFallbackList(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('auto 100vw xl:400px');

        self::assertSame('auto, (min-width: 1280px) 400px, 100vw', $resolved->sizes);
    }

    public function testAutoWithAFallbackStillDerivesWidths(): void
    {
        $resolved = new SizesResolver(self::SCREENS)->resolve('auto 100vw xl:400px');

        // the fallback is what a browser uses for the pre-layout fetch
        self::assertSame([320, 400], $resolved->widths);
    }

    public function testUnknownScreenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown screen "tablet" in the "sizes" prop.');

        new SizesResolver(self::SCREENS)->resolve('tablet:50vw');
    }
}
