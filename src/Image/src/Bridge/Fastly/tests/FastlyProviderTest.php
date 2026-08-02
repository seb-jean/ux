<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Fastly\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Bridge\Fastly\FastlyProviderFactory;
use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Fit;
use Symfony\UX\Image\ImageDimensions;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Transformation;

final class FastlyProviderTest extends TestCase
{
    public function testItEncodesTransformationsAsQueryParams(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://default'));

        $generated = $provider->transform(
            new ImageSource('images/hero.jpg', 'https://cdn.test/images/hero.jpg', null, new ImageDimensions(1600, 900)),
            new Transformation(width: 800, format: 'webp', quality: 75, fit: Fit::Cover),
        );

        self::assertSame('https://cdn.test/images/hero.jpg?fit=cover&format=webp&quality=75&width=800', $generated->url);
        self::assertSame(800, $generated->width);
        self::assertSame(450, $generated->height);
    }

    public function testAutoNegotiationWhenNoFormatRequested(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://default'));

        $generated = $provider->transform(
            new ImageSource('images/hero.jpg', 'https://cdn.test/images/hero.jpg'),
            new Transformation(width: 640),
        );

        self::assertStringContainsString('auto=webp', $generated->url);
        self::assertStringContainsString('width=640', $generated->url);
    }

    public function testHostRewriteForRelativeUrls(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://cdn.example.com'));

        $generated = $provider->transform(
            new ImageSource('images/hero.jpg', '/images/hero.jpg'),
            new Transformation(width: 320),
        );

        self::assertStringStartsWith('https://cdn.example.com/images/hero.jpg?', $generated->url);
    }

    public function testSignedUrlsAppendASigParam(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://secretkey@cdn.example.com'));

        $generated = $provider->transform(
            new ImageSource('images/hero.jpg', '/images/hero.jpg'),
            new Transformation(width: 320),
        );

        self::assertMatchesRegularExpression('#[?&]sig=[A-Za-z0-9_-]+#', $generated->url);
    }

    public function testModifiersAreMergedIntoTheQueryAndSigned(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://default'));

        $generated = $provider->transform(
            new ImageSource('images/hero.jpg', 'https://cdn.test/images/hero.jpg'),
            new Transformation(width: 800, modifiers: ['blur' => 10]),
        );

        self::assertStringContainsString('blur=10', $generated->url);
        // params stay sorted, so the signature is reproducible
        self::assertStringContainsString('auto=webp&blur=10&fit=bounds&width=800', $generated->url);
    }

    public function testCapabilities(): void
    {
        $provider = new FastlyProviderFactory()->create(new Dsn('fastly://default'));

        self::assertTrue($provider->capabilities()->canResize);
        self::assertTrue($provider->capabilities()->supportsFormat('avif'));
    }
}
