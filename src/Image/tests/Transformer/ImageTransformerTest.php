<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformer\ImageTransformer;

class ImageTransformerTest extends TestCase
{
    private ImageTransformer $transformer;
    private string $fixtureJpeg;
    private string $fixturePng;

    protected function setUp(): void
    {
        $this->transformer = new ImageTransformer();
        $this->fixtureJpeg = $this->createFixtureImage('jpeg');
        $this->fixturePng = $this->createFixtureImage('png');
    }

    protected function tearDown(): void
    {
        @unlink($this->fixtureJpeg);
        @unlink($this->fixturePng);
    }

    public function testTransformWithoutOptions(): void
    {
        [$data, $mime] = $this->transformer->transform($this->fixtureJpeg, []);

        $this->assertNotEmpty($data);
        $this->assertSame('image/jpeg', $mime);
    }

    public function testTransformResize(): void
    {
        // Source is 200×100; fill stretches to exact target dimensions
        [$data] = $this->transformer->transform($this->fixtureJpeg, ['width' => 50, 'height' => 50, 'fit' => 'fill']);

        $image = imagecreatefromstring($data);
        $this->assertSame(50, imagesx($image));
        $this->assertSame(50, imagesy($image));
        imagedestroy($image);
    }

    public function testTransformContainPreservesAspectRatio(): void
    {
        // Source is 200x100, target is 100x100, contain → output is 100x50
        [$data] = $this->transformer->transform($this->fixtureJpeg, [
            'width' => 100,
            'height' => 100,
            'fit' => 'contain',
        ]);

        $image = imagecreatefromstring($data);
        $this->assertSame(100, imagesx($image));
        $this->assertSame(50, imagesy($image));
        imagedestroy($image);
    }

    public function testTransformCoverFillsDimensions(): void
    {
        [$data] = $this->transformer->transform($this->fixtureJpeg, [
            'width' => 100,
            'height' => 100,
            'fit' => 'cover',
        ]);

        $image = imagecreatefromstring($data);
        $this->assertSame(100, imagesx($image));
        $this->assertSame(100, imagesy($image));
        imagedestroy($image);
    }

    public function testTransformToWebp(): void
    {
        [$data, $mime] = $this->transformer->transform($this->fixtureJpeg, ['format' => 'webp', 'width' => 100]);

        $this->assertSame('image/webp', $mime);
        // WebP magic bytes: RIFF....WEBP
        $this->assertStringStartsWith('RIFF', $data);
    }

    public function testTransformToPng(): void
    {
        [$data, $mime] = $this->transformer->transform($this->fixtureJpeg, ['format' => 'png']);

        $this->assertSame('image/png', $mime);
        // PNG signature
        $this->assertStringStartsWith("\x89PNG", $data);
    }

    public function testInvalidFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->transformer->transform($this->fixtureJpeg, ['format' => 'gif']);
    }

    public function testInvalidFitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->transformer->transform($this->fixtureJpeg, ['fit' => 'stretch']);
    }

    public function testInvalidQualityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->transformer->transform($this->fixtureJpeg, ['quality' => 0]);
    }

    /** Creates a 200×100 test image and saves it as the given format, returns the path. */
    private function createFixtureImage(string $format): string
    {
        $image = imagecreatetruecolor(200, 100);
        imagefilledrectangle($image, 0, 0, 199, 99, imagecolorallocate($image, 100, 149, 237));

        $path = sys_get_temp_dir().'/ux_image_test_'.uniqid().'.'.$format;
        match ($format) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
        };
        imagedestroy($image);

        return $path;
    }
}
