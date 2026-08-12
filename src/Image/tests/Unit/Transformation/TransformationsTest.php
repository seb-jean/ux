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
use Symfony\UX\Image\Transformation\Fit;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Orientation;
use Symfony\UX\Image\Transformation\Transformations;

class TransformationsTest extends TestCase
{
    public function testEmpty(): void
    {
        $transformations = Transformations::fromArray([]);

        $this->assertTrue($transformations->isEmpty());
        $this->assertSame([], $transformations->toQuery());
        $this->assertFalse($transformations->hasGeometry());
    }

    public function testNullValuesAreIgnored(): void
    {
        $this->assertTrue(Transformations::fromArray(['width' => null, 'format' => null])->isEmpty());
    }

    public function testEveryTransformation(): void
    {
        $transformations = Transformations::fromArray([
            'width' => 800,
            'height' => 600,
            'dpr' => 2,
            'fit' => 'cover',
            'crop' => '16:9',
            'format' => 'webp',
            'quality' => 70,
            'bg-color' => '#fff',
            'orient' => 'r',
            'bw' => true,
            'blur' => 5,
            'brightness' => -10,
            'contrast' => 20,
        ]);

        $this->assertSame(800, $transformations->width);
        $this->assertSame(600, $transformations->height);
        $this->assertSame(2.0, $transformations->dpr);
        $this->assertSame(Fit::Cover, $transformations->fit);
        $this->assertSame('16:9', $transformations->crop->toString());
        $this->assertSame(Format::Webp, $transformations->format);
        $this->assertSame(70, $transformations->quality);
        $this->assertSame('ffffff', $transformations->backgroundColor->toString());
        $this->assertSame(Orientation::Right, $transformations->orientation);
        $this->assertTrue($transformations->blackAndWhite);
        $this->assertSame(5, $transformations->blur);
        $this->assertSame(-10, $transformations->brightness);
        $this->assertSame(20, $transformations->contrast);
    }

    public function testUnderscoresAreAcceptedForDashedNames(): void
    {
        $this->assertSame('ffffff', Transformations::fromArray(['bg_color' => '#fff'])->backgroundColor->toString());
    }

    public function testQueryIsSortedAndCanonical(): void
    {
        $query = Transformations::fromArray(['width' => 800, 'format' => 'webp', 'bw' => true, 'dpr' => 2.0])->toQuery();

        $this->assertSame(['bw' => 'true', 'dpr' => '2', 'format' => 'webp', 'width' => '800'], $query);
    }

    public function testEquivalentTransformationsShareTheSameQuery(): void
    {
        $fromTwig = Transformations::fromArray(['width' => 800, 'fit' => 'cover', 'bw' => true]);
        $fromUrl = Transformations::fromArray(['fit' => 'Cover', 'bw' => 'true', 'width' => '800']);

        $this->assertSame($fromTwig->toQuery(), $fromUrl->toQuery());
    }

    public function testAutoAcceptsAListOrAString(): void
    {
        $this->assertSame([Format::Avif, Format::Webp], Transformations::fromArray(['auto' => 'webp,avif'])->auto);
        $this->assertSame([Format::Webp], Transformations::fromArray(['auto' => ['webp']])->auto);
    }

    public function testAutoIsDeduplicatedAndOrdered(): void
    {
        $this->assertSame('avif,webp', Transformations::fromArray(['auto' => ['webp', 'avif', 'webp']])->toQuery()['auto']);
    }

    public function testAutoOnlyNegotiatesModernFormats(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only negotiates "avif" and "webp"');

        Transformations::fromArray(['auto' => 'png']);
    }

    public function testHasGeometry(): void
    {
        $this->assertTrue(Transformations::fromArray(['width' => 10])->hasGeometry());
        $this->assertTrue(Transformations::fromArray(['crop' => '1:1'])->hasGeometry());
        $this->assertFalse(Transformations::fromArray(['format' => 'webp', 'bw' => true])->hasGeometry());
    }

    public function testWithFormatDropsNegotiation(): void
    {
        $transformations = Transformations::fromArray(['auto' => 'webp'])->withFormat(Format::Avif);

        $this->assertSame(Format::Avif, $transformations->format);
        $this->assertSame([], $transformations->auto);
    }

    public function testWithQuality(): void
    {
        $this->assertSame(42, Transformations::fromArray([])->withQuality(42)->quality);
    }

    #[DataProvider('provideNotImplemented')]
    public function testKnownButUnimplementedParametersAreRejected(string $parameter): void
    {
        $this->expectException(UnsupportedTransformationException::class);
        $this->expectExceptionMessage(\sprintf('The image transformation "%s" cannot be applied', $parameter));

        Transformations::fromArray([$parameter => 'whatever']);
    }

    public static function provideNotImplemented(): iterable
    {
        yield ['pad'];
        yield ['canvas'];
        yield ['trim'];
        yield ['precrop'];
        yield ['sharpen'];
        yield ['saturation'];
        yield ['metadata'];
        yield ['frame'];
        yield ['optimize'];
        yield ['resize-filter'];
    }

    public function testUnimplementedParametersAreRejectedEvenWhenNull(): void
    {
        $this->expectException(UnsupportedTransformationException::class);

        Transformations::fromArray(['pad' => null]);
    }

    public function testUnknownParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown image transformation "wobble".');

        Transformations::fromArray(['wobble' => 1]);
    }

    #[DataProvider('provideOutOfRange')]
    public function testOutOfRangeValues(array $transformations, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        Transformations::fromArray($transformations);
    }

    public static function provideOutOfRange(): iterable
    {
        yield 'zero width' => [['width' => 0], 'The "width" transformation must be between 1 and ∞'];
        yield 'negative height' => [['height' => -1], 'The "height" transformation must be between 1 and ∞'];
        yield 'quality above 100' => [['quality' => 101], 'The "quality" transformation must be between 0 and 100'];
        yield 'dpr above 5' => [['dpr' => 6], 'The "dpr" transformation must be between 1 and 5'];
        yield 'blur above 1000' => [['blur' => 1001], 'The "blur" transformation must be between 0 and 1000'];
        yield 'brightness out of range' => [['brightness' => 200], 'The "brightness" transformation must be between -100 and 100'];
        yield 'non numeric width' => [['width' => 'wide'], 'The "width" transformation must be an integer'];
        yield 'unknown fit' => [['fit' => 'squeeze'], 'The "fit" transformation does not accept "squeeze"'];
        yield 'unknown format' => [['format' => 'bmp'], 'The "format" transformation does not accept "bmp"'];
        yield 'unknown orientation' => [['orient' => 'z'], 'The "orient" transformation does not accept "z"'];
    }
}
