<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Image;
use Symfony\UX\Image\Provider\Local\LocalProvider;
use Symfony\UX\Image\Source;

class LocalProviderTest extends TestCase
{
    public function testRenderImageWithoutTransformation(): void
    {
        $provider = new LocalProvider();
        $image = new Image('/images/photo.jpg', 'A photo');

        $html = $provider->renderImage($image);

        $this->assertSame('<img src="/images/photo.jpg" alt="A photo" loading="lazy" decoding="async" />', $html);
    }

    public function testRenderImageWithWidthAndHeight(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->alt('Photo')
            ->width(800)
            ->height(600);

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="600"', $html);
        $this->assertStringContainsString('alt="Photo"', $html);
    }

    public function testRenderImageWithExtraAttributes(): void
    {
        $provider = new LocalProvider();
        $image = new Image('/images/photo.jpg');

        $html = $provider->renderImage($image, ['class' => 'hero-image', 'data-id' => '42']);

        $this->assertStringContainsString('class="hero-image"', $html);
        $this->assertStringContainsString('data-id="42"', $html);
    }

    public function testExtraAttributesCanOverrideDefaults(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))->loading('lazy');

        $html = $provider->renderImage($image, ['loading' => 'eager']);

        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function testAttributesAreHtmlEscaped(): void
    {
        $provider = new LocalProvider();
        $image = new Image('/images/photo.jpg', '<script>alert(1)</script>');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('alt="&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testToString(): void
    {
        $provider = new LocalProvider();
        $this->assertSame('local', (string) $provider);
    }

    public function testRenderImageWithSourcesReturnsPictureTag(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg', 'A photo'))
            ->addSource(Source::create('/images/photo.avif')->type('image/avif'))
            ->addSource(Source::create('/images/photo.webp')->type('image/webp'));

        $html = $provider->renderImage($image);

        $this->assertStringStartsWith('<picture>', $html);
        $this->assertStringEndsWith('</picture>', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.avif" type="image/avif">', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.webp" type="image/webp">', $html);
        $this->assertStringContainsString('<img src="/images/photo.jpg"', $html);
        $this->assertStringContainsString('alt="A photo"', $html);
    }

    public function testRenderImageWithSourceIncludesMediaAndSizes(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->addSource(
                Source::create('/images/photo-large.webp')
                    ->type('image/webp')
                    ->media('(min-width: 800px)')
                    ->sizes('80vw')
            );

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('media="(min-width: 800px)"', $html);
        $this->assertStringContainsString('sizes="80vw"', $html);
    }

    public function testRenderImageSourcesOrderIsPreserved(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->addSource(Source::create('/images/photo.avif')->type('image/avif'))
            ->addSource(Source::create('/images/photo.webp')->type('image/webp'));

        $html = $provider->renderImage($image);

        $avifPos = strpos($html, 'image/avif');
        $webpPos = strpos($html, 'image/webp');

        $this->assertNotFalse($avifPos);
        $this->assertNotFalse($webpPos);
        $this->assertLessThan($webpPos, $avifPos, 'avif source should appear before webp source');
    }

    public function testRenderImageWithDecodingAttribute(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))->decoding('sync');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('decoding="sync"', $html);
    }

    public function testRenderImageDefaultDecodingIsAsync(): void
    {
        $provider = new LocalProvider();
        $image = new Image('/images/photo.jpg');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('decoding="async"', $html);
    }

    public function testRenderImageWithSrcset(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->srcset('/images/photo-300.jpg 300w, /images/photo-600.jpg 600w, /images/photo-1200.jpg 1200w');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('srcset="/images/photo-300.jpg 300w, /images/photo-600.jpg 600w, /images/photo-1200.jpg 1200w"', $html);
    }

    public function testRenderImageWithSrcsetAndSizes(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->srcset('/images/photo-300.jpg 300w, /images/photo-900.jpg 900w')
            ->sizes('(max-width: 600px) 100vw, 50vw');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('sizes="(max-width: 600px) 100vw, 50vw"', $html);
    }

    public function testRenderImageWithFetchpriority(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/hero.jpg'))->fetchpriority('high');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function testRenderImageWithSourceWidthAndHeight(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->addSource(
                Source::create('/images/photo.avif')
                    ->type('image/avif')
                    ->width(1200)
                    ->height(600)
            );

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="600"', $html);
    }
}
