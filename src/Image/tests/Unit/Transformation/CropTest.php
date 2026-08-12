<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Transformation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\UnsupportedTransformationException;
use Symfony\UX\Image\Transformation\Crop;

class CropTest extends TestCase
{
    public function testPixels(): void
    {
        $crop = Crop::fromString('1000,500');

        $this->assertFalse($crop->isRatio());
        $this->assertSame(1000, $crop->width);
        $this->assertSame(500, $crop->height);
        $this->assertNull($crop->x);
        $this->assertFalse($crop->safe);
    }

    public function testRatio(): void
    {
        $crop = Crop::fromString('16:9');

        $this->assertTrue($crop->isRatio());
        $this->assertSame(16.0, $crop->ratioWidth);
        $this->assertSame(9.0, $crop->ratioHeight);
    }

    public function testAbsoluteCoordinates(): void
    {
        $crop = Crop::fromString('1000,500,x400,y50');

        $this->assertSame(400.0, $crop->x);
        $this->assertFalse($crop->xIsPercent);
        $this->assertSame(50.0, $crop->y);
    }

    public function testPercentageCoordinates(): void
    {
        $crop = Crop::fromString('1000,500,x25p,y10p');

        $this->assertSame(25.0, $crop->x);
        $this->assertTrue($crop->xIsPercent);
        $this->assertTrue($crop->yIsPercent);
    }

    public function testOffsets(): void
    {
        $crop = Crop::fromString('1:1,offset-x90,offset-y50');

        $this->assertSame(90.0, $crop->offsetX);
        $this->assertSame(50.0, $crop->offsetY);
        $this->assertNull($crop->x);
    }

    public function testSafe(): void
    {
        $crop = Crop::fromString('3000,600,x100,y100,safe');

        $this->assertTrue($crop->safe);
        $this->assertSame(100.0, $crop->x);
    }

    #[DataProvider('provideRoundTrips')]
    public function testToStringRoundTrip(string $crop): void
    {
        $this->assertSame($crop, Crop::fromString($crop)->toString());
    }

    public static function provideRoundTrips(): iterable
    {
        yield ['1000,500'];
        yield ['16:9'];
        yield ['1000,500,x400,y50'];
        yield ['1000,500,x25p,y10p'];
        yield ['1:1,offset-x90,offset-y50'];
        yield ['3000,600,x100,y100,safe'];
    }

    public function testSmartIsRejectedExplicitly(): void
    {
        $this->expectException(UnsupportedTransformationException::class);
        $this->expectExceptionMessage('content-aware cropping ("smart")');

        Crop::fromString('16:9,smart');
    }

    #[DataProvider('provideInvalidCrops')]
    public function testInvalidCrop(string $crop, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        Crop::fromString($crop);
    }

    public static function provideInvalidCrops(): iterable
    {
        yield 'empty' => ['', 'cannot be empty'];
        yield 'missing height' => ['1000', 'must provide a height'];
        yield 'unknown modifier' => ['100,100,middle', 'unknown modifier "middle"'];
        yield 'half a position' => ['100,100,x10', 'must provide both coordinates'];
        yield 'non numeric size' => ['abc,100', 'invalid dimension "abc"'];
        yield 'negative size' => ['-10,100', 'invalid dimension "-10"'];
        yield 'zero size' => ['0,100', 'A crop must be positive'];
        yield 'bad ratio' => ['16:9:4', 'invalid ratio'];
        yield 'offset out of range' => ['1:1,offset-x150,offset-y0', 'must be a percentage between 0 and 100'];
    }
}
