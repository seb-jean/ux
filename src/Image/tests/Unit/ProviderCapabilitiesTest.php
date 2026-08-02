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
use Symfony\UX\Image\Provider\ProviderCapabilities;

final class ProviderCapabilitiesTest extends TestCase
{
    public function testArbitraryWidthsAreAlwaysSupported(): void
    {
        $caps = new ProviderCapabilities(formats: ['webp'], widths: null);

        self::assertTrue($caps->supportsWidth(1234));
        self::assertSame(1234, $caps->clampWidth(1234));
    }

    public function testClampWidthPicksNearestAllowedOrLargest(): void
    {
        $caps = new ProviderCapabilities(formats: ['webp'], widths: [320, 640, 1024]);

        self::assertSame(320, $caps->clampWidth(100));
        self::assertSame(640, $caps->clampWidth(500));
        self::assertSame(1024, $caps->clampWidth(5000));
    }

    public function testCannotResizeReturnsNull(): void
    {
        $caps = new ProviderCapabilities(formats: [], widths: [], canResize: false);

        self::assertNull($caps->clampWidth(800));
        self::assertFalse($caps->canResize);
    }

    public function testAutoFormatSupportsEverything(): void
    {
        $caps = new ProviderCapabilities(formats: ['auto']);

        self::assertTrue($caps->supportsFormat('avif'));
        self::assertTrue($caps->supportsFormat('webp'));
    }
}
