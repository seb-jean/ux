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
use Symfony\UX\Image\Transformation\Color;

class ColorTest extends TestCase
{
    /**
     * @param array{int, int, int, int} $expected
     */
    #[DataProvider('provideColors')]
    public function testFromString(string $color, array $expected): void
    {
        $color = Color::fromString($color);

        $this->assertSame($expected, [$color->red, $color->green, $color->blue, $color->alpha]);
    }

    public static function provideColors(): iterable
    {
        yield 'three digits' => ['fff', [255, 255, 255, 255]];
        yield 'three digits with a hash' => ['#f00', [255, 0, 0, 255]];
        yield 'four digits' => ['0f08', [0, 255, 0, 136]];
        yield 'six digits' => ['1a2b3c', [26, 43, 60, 255]];
        yield 'eight digits' => ['1a2b3c80', [26, 43, 60, 128]];
        yield 'uppercase' => ['#AABBCC', [170, 187, 204, 255]];
        yield 'rgb()' => ['rgb(12, 34, 56)', [12, 34, 56, 255]];
        yield 'rgba()' => ['rgba(12,34,56,0.5)', [12, 34, 56, 128]];
    }

    #[DataProvider('provideInvalidColors')]
    public function testInvalidColor(string $color): void
    {
        $this->expectException(InvalidArgumentException::class);

        Color::fromString($color);
    }

    public static function provideInvalidColors(): iterable
    {
        yield 'not hexadecimal' => ['zzzzzz'];
        yield 'wrong length' => ['12345'];
        yield 'out of range channel' => ['rgb(300, 0, 0)'];
        yield 'too few channels' => ['rgb(1, 2)'];
        yield 'non numeric channel' => ['rgb(a, b, c)'];
    }

    public function testToStringOmitsAnOpaqueAlpha(): void
    {
        $this->assertSame('1a2b3c', Color::fromString('#1a2b3c')->toString());
        $this->assertSame('1a2b3c80', Color::fromString('#1a2b3c80')->toString());
    }

    public function testIsOpaque(): void
    {
        $this->assertTrue(Color::fromString('fff')->isOpaque());
        $this->assertFalse(Color::fromString('ffff0000')->isOpaque());
    }
}
