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

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformation\Fit;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\TransformationFactory;

class TransformationFactoryTest extends TestCase
{
    public function testDefaultQualityIsApplied(): void
    {
        $factory = new TransformationFactory(quality: 72);

        $this->assertSame(72, $factory->create(['width' => 100])->quality);
    }

    public function testAnExplicitQualityWins(): void
    {
        $factory = new TransformationFactory(quality: 72);

        $this->assertSame(30, $factory->create(['width' => 100, 'quality' => 30])->quality);
    }

    public function testConfiguredAutoIsApplied(): void
    {
        $factory = new TransformationFactory(auto: ['webp']);

        $this->assertSame([Format::Webp], $factory->create(['width' => 100])->auto);
    }

    public function testConfiguredAutoIsSkippedWhenAFormatIsRequested(): void
    {
        $factory = new TransformationFactory(auto: ['webp']);

        $this->assertSame([], $factory->create(['width' => 100, 'format' => 'png'])->auto);
    }

    public function testPreset(): void
    {
        $factory = new TransformationFactory(presets: [
            'thumbnail' => ['width' => 300, 'height' => 300, 'fit' => 'crop', 'format' => 'webp'],
        ]);

        $transformations = $factory->create('thumbnail');

        $this->assertSame(300, $transformations->width);
        $this->assertSame(Fit::Crop, $transformations->fit);
        $this->assertSame(Format::Webp, $transformations->format);
    }

    public function testUnknownPresetListsTheAvailableOnes(): void
    {
        $factory = new TransformationFactory(presets: ['thumbnail' => [], 'hero' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The image preset "banner" is not configured. Available presets: "thumbnail", "hero".');

        $factory->create('banner');
    }

    public function testNullMeansNoTransformation(): void
    {
        $factory = new TransformationFactory(quality: 85);

        $this->assertNull($factory->create(null)->width);
    }

    public function testWidthIsCapped(): void
    {
        $factory = new TransformationFactory(maxWidth: 1000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "width" transformation cannot exceed 1000, got 1001.');

        $factory->create(['width' => 1001]);
    }

    public function testHeightIsCapped(): void
    {
        $factory = new TransformationFactory(maxHeight: 1000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "height" transformation cannot exceed 1000, got 2000.');

        $factory->create(['height' => 2000]);
    }

    public function testFormatMustBeAllowed(): void
    {
        $factory = new TransformationFactory(allowedFormats: ['webp', 'jpeg']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "png" output format is not allowed, expected one of: "webp", "jpeg".');

        $factory->create(['format' => 'png']);
    }

    public function testAutoMustBeAllowed(): void
    {
        $factory = new TransformationFactory(allowedFormats: ['jpeg', 'webp']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "auto" transformation cannot negotiate "avif"');

        $factory->create(['auto' => 'avif']);
    }

    public function testLimitsAreEnforcedAgainOnTheUrlQuery(): void
    {
        $factory = new TransformationFactory(maxWidth: 1000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 1000');

        $factory->fromQuery(['width' => '9999']);
    }

    public function testTheUrlSignatureAndVersionAreNotTransformations(): void
    {
        $factory = new TransformationFactory();

        $transformations = $factory->fromQuery(['width' => '100', 'v' => 'abc123', '_hash' => 'deadbeef']);

        $this->assertSame(100, $transformations->width);
    }
}
