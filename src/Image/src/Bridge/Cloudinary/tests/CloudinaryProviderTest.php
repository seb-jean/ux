<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Cloudinary\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Bridge\Cloudinary\CloudinaryProviderFactory;
use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Exception\UnsupportedTransformationException;
use Symfony\UX\Image\Fit;
use Symfony\UX\Image\ImageDimensions;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Transformation;

final class CloudinaryProviderTest extends TestCase
{
    public function testItEncodesTransformationsInThePath(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo'));

        $generated = $provider->transform(
            new ImageSource('sample.jpg', 'https://origin.test/sample.jpg', null, new ImageDimensions(1600, 900)),
            new Transformation(width: 800, format: 'webp', quality: 80, fit: Fit::Cover),
        );

        self::assertSame('https://res.cloudinary.com/demo/image/upload/c_fill,g_auto,w_800,f_webp,q_80/sample.jpg', $generated->url);
        self::assertSame(800, $generated->width);
        self::assertSame(450, $generated->height); // derived from the source aspect ratio
        self::assertSame('webp', $generated->format);
    }

    public function testAutoFormatAndQualityByDefault(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo'));

        $generated = $provider->transform(
            new ImageSource('sample.jpg', 'https://origin.test/sample.jpg'),
            new Transformation(width: 640),
        );

        self::assertStringContainsString('/c_fit,w_640,f_auto,q_auto/sample.jpg', $generated->url);
    }

    public function testSignedUrlsAddAShortSignatureSegment(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://key:secret@demo?sign_urls=true'));

        $generated = $provider->transform(
            new ImageSource('sample.jpg', 'https://origin.test/sample.jpg'),
            new Transformation(width: 800),
        );

        self::assertMatchesRegularExpression('#/image/upload/s--[A-Za-z0-9_-]{8}--/c_fit,w_800,f_auto,q_auto/sample\.jpg$#', $generated->url);
    }

    public function testSignedUrlsRequireASecret(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo?sign_urls=true'));

        $this->expectException(UnsupportedTransformationException::class);

        $provider->transform(new ImageSource('sample.jpg', 'https://origin.test/sample.jpg'), new Transformation(width: 800));
    }

    public function testFetchDeliveryEncodesTheRemoteUrl(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo?delivery=fetch'));

        $generated = $provider->transform(
            new ImageSource('https://example.com/a.jpg', 'https://example.com/a.jpg'),
            new Transformation(width: 400),
        );

        self::assertStringContainsString('/image/fetch/c_fit,w_400,f_auto,q_auto/'.rawurlencode('https://example.com/a.jpg'), $generated->url);
    }

    public function testModifiersAreAppendedToThePathSegment(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo'));

        $generated = $provider->transform(
            new ImageSource('sample.jpg', 'https://origin.test/sample.jpg'),
            new Transformation(width: 800, modifiers: ['e' => 'grayscale', 'bo' => '5px_solid_black']),
        );

        self::assertStringContainsString('/c_fit,w_800,f_auto,q_auto,e_grayscale,bo_5px_solid_black/sample.jpg', $generated->url);
    }

    public function testCapabilities(): void
    {
        $provider = new CloudinaryProviderFactory()->create(new Dsn('cloudinary://demo'));

        self::assertTrue($provider->capabilities()->canResize);
        self::assertTrue($provider->capabilities()->supportsFormat('avif'));
        self::assertNull($provider->capabilities()->widths);
    }
}
