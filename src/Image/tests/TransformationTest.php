<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformation;

class TransformationTest extends TestCase
{
    public function testDefaultIsEmpty(): void
    {
        $t = Transformation::create();
        $this->assertTrue($t->isEmpty());
        $this->assertSame([], $t->toArray());
    }

    public function testWidth(): void
    {
        $t = Transformation::create()->width(800);
        $this->assertSame(['width' => 800], $t->toArray());
        $this->assertFalse($t->isEmpty());
    }

    public function testWidthMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Transformation::create()->width(0);
    }

    public function testHeight(): void
    {
        $t = Transformation::create()->height(600);
        $this->assertSame(['height' => 600], $t->toArray());
    }

    public function testHeightMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Transformation::create()->height(-1);
    }

    public function testFormat(): void
    {
        foreach (['webp', 'jpeg', 'png', 'avif'] as $format) {
            $t = Transformation::create()->format($format);
            $this->assertSame(['format' => $format], $t->toArray());
        }
    }

    public function testUnsupportedFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid format "gif"');
        Transformation::create()->format('gif');
    }

    public function testQuality(): void
    {
        $t = Transformation::create()->quality(85);
        $this->assertSame(['quality' => 85], $t->toArray());
    }

    public function testQualityBounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Transformation::create()->quality(0);
    }

    public function testQualityUpperBound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Transformation::create()->quality(101);
    }

    public function testFit(): void
    {
        foreach (['contain', 'cover', 'fill', 'crop'] as $fit) {
            $t = Transformation::create()->fit($fit);
            $this->assertSame(['fit' => $fit], $t->toArray());
        }
    }

    public function testUnsupportedFit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fit "stretch"');
        Transformation::create()->fit('stretch');
    }

    public function testCombinedTransformations(): void
    {
        $t = Transformation::create()
            ->width(1280)
            ->height(720)
            ->format('webp')
            ->quality(90)
            ->fit('cover');

        $this->assertSame([
            'width' => 1280,
            'height' => 720,
            'format' => 'webp',
            'quality' => 90,
            'fit' => 'cover',
        ], $t->toArray());
    }

    public function testIsImmutable(): void
    {
        $original = Transformation::create();
        $modified = $original->width(800);

        $this->assertNotSame($original, $modified);
        $this->assertTrue($original->isEmpty());
        $this->assertFalse($modified->isEmpty());
    }
}
