<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Processor;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Processor\ImageProcessorInterface;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Source\ImageSourceResolver;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Geometry;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * The contract every driver has to honor.
 *
 * Both drivers run the very same assertions: a transformation must not depend on
 * whether GD or Imagick happens to be installed.
 */
abstract class ImageProcessorTestCase extends TestCase
{
    abstract protected function createProcessor(): ImageProcessorInterface;

    protected function setUp(): void
    {
        if (!$this->createProcessor()->isAvailable()) {
            $this->markTestSkipped(\sprintf('The "%s" driver is not available.', static::class));
        }
    }

    public function testResizeToAWidthKeepsTheAspectRatio(): void
    {
        [$width, $height, $mimeType] = $this->identify($this->process('photo.jpg', ['width' => 40]));

        $this->assertSame([40, 30], [$width, $height]);
        $this->assertSame('image/jpeg', $mimeType);
    }

    public function testResizeToAHeightKeepsTheAspectRatio(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['height' => 30]));

        $this->assertSame([40, 30], [$width, $height]);
    }

    public function testUpscaling(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['width' => 160]));

        $this->assertSame([160, 120], [$width, $height]);
    }

    public function testFitBoundsStaysInsideTheBox(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['width' => 40, 'height' => 40, 'fit' => 'bounds']));

        $this->assertSame([40, 30], [$width, $height]);
    }

    public function testFitCoverOverflowsTheBox(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['width' => 40, 'height' => 40, 'fit' => 'cover']));

        $this->assertSame([53, 40], [$width, $height]);
    }

    public function testFitCropMatchesTheBoxExactly(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['width' => 40, 'height' => 40, 'fit' => 'crop']));

        $this->assertSame([40, 40], [$width, $height]);
    }

    public function testCropInPixels(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['crop' => '40,30']));

        $this->assertSame([40, 30], [$width, $height]);
    }

    public function testCropToARatio(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['crop' => '1:1']));

        $this->assertSame([60, 60], [$width, $height]);
    }

    public function testDprMultipliesTheOutput(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['width' => 40, 'dpr' => 2]));

        $this->assertSame([80, 60], [$width, $height]);
    }

    public function testRotationSwapsTheAxes(): void
    {
        [$width, $height] = $this->identify($this->process('photo.jpg', ['orient' => 'r']));

        $this->assertSame([60, 80], [$width, $height]);
    }

    public function testConversionToPng(): void
    {
        [$width, $height, $mimeType] = $this->identify($this->process('photo.jpg', ['width' => 20], Format::Png));

        $this->assertSame([20, 15], [$width, $height]);
        $this->assertSame('image/png', $mimeType);
    }

    public function testConversionToWebp(): void
    {
        $this->requireFormat(Format::Webp);

        [$width, $height, $mimeType] = $this->identify($this->process('photo.jpg', ['width' => 20], Format::Webp));

        $this->assertSame([20, 15], [$width, $height]);
        $this->assertSame('image/webp', $mimeType);
    }

    public function testConversionToGif(): void
    {
        $this->requireFormat(Format::Gif);

        [,, $mimeType] = $this->identify($this->process('photo.jpg', ['width' => 20], Format::Gif));

        $this->assertSame('image/gif', $mimeType);
    }

    public function testAPngSourceIsRead(): void
    {
        [$width, $height, $mimeType] = $this->identify($this->process('logo.png', ['width' => 20]));

        $this->assertSame([20, 20], [$width, $height]);
        $this->assertSame('image/png', $mimeType);
    }

    public function testQualityChangesTheFileSize(): void
    {
        $low = $this->process('photo.jpg', ['width' => 80, 'quality' => 10]);
        $high = $this->process('photo.jpg', ['width' => 80, 'quality' => 95]);

        $this->assertLessThan(\strlen($high), \strlen($low));
    }

    #[RequiresPhpExtension('gd')]
    public function testTransparencyIsKeptWhenTheFormatSupportsIt(): void
    {
        $contents = $this->process('logo.png', ['width' => 40]);

        $this->assertSame(127, $this->readPixel($contents, 2, 2)['alpha'], 'the corner is fully transparent');
    }

    #[RequiresPhpExtension('gd')]
    public function testTransparencyIsFlattenedOntoTheBackgroundColorForJpeg(): void
    {
        $contents = $this->process('logo.png', ['width' => 40, 'bg-color' => '#00ff00'], Format::Jpeg);

        $pixel = $this->readPixel($contents, 2, 2);

        $this->assertLessThan(30, $pixel['red']);
        $this->assertGreaterThan(220, $pixel['green']);
        $this->assertLessThan(30, $pixel['blue']);
    }

    #[RequiresPhpExtension('gd')]
    public function testTransparencyIsFlattenedOntoWhiteByDefaultForJpeg(): void
    {
        $contents = $this->process('logo.png', ['width' => 40], Format::Jpeg);

        $pixel = $this->readPixel($contents, 2, 2);

        $this->assertGreaterThan(240, min($pixel['red'], $pixel['green'], $pixel['blue']));
    }

    #[RequiresPhpExtension('gd')]
    public function testBlackAndWhite(): void
    {
        $contents = $this->process('portrait.png', ['bw' => true], Format::Png);

        $pixel = $this->readPixel($contents, 30, 40);

        $this->assertSame($pixel['red'], $pixel['green']);
        $this->assertSame($pixel['green'], $pixel['blue']);
    }

    #[RequiresPhpExtension('gd')]
    public function testTheCroppedRegionIsThePositionedOne(): void
    {
        // the red square of logo.png covers 10,10 to 29,29
        $contents = $this->process('logo.png', ['crop' => '10,10,x10,y10'], Format::Png);

        [$width, $height] = $this->identify($contents);
        $pixel = $this->readPixel($contents, 5, 5);

        $this->assertSame([10, 10], [$width, $height]);
        $this->assertGreaterThan(220, $pixel['red']);
        $this->assertLessThan(30, $pixel['green']);
    }

    /**
     * @param array<string, mixed> $transformations
     */
    protected function process(string $fixture, array $transformations, ?Format $outputFormat = null): string
    {
        $source = $this->resolve($fixture);
        $transformations = Transformations::fromArray($transformations);

        return $this->createProcessor()->process(
            $source,
            Geometry::plan($source->sizeOrFail(), $transformations),
            $transformations,
            $outputFormat ?? $source->format,
        );
    }

    protected function resolve(string $fixture): ImageSource
    {
        return new ImageSourceResolver(__DIR__.'/../../Fixtures/images')->resolve($fixture);
    }

    protected function requireFormat(Format $format): void
    {
        if (!$this->createProcessor()->supports($format)) {
            $this->markTestSkipped(\sprintf('This driver was built without "%s" support.', $format->value));
        }
    }

    /**
     * @return array{int, int, string}
     */
    protected function identify(string $contents): array
    {
        $info = getimagesizefromstring($contents);

        $this->assertNotFalse($info, 'the driver produced a readable image');

        return [$info[0], $info[1], $info['mime']];
    }

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    protected function readPixel(string $contents, int $x, int $y): array
    {
        $image = imagecreatefromstring($contents);

        $this->assertNotFalse($image, 'the driver produced a readable image');

        try {
            return imagecolorsforindex($image, imagecolorat($image, $x, $y));
        } finally {
            imagedestroy($image);
        }
    }
}
