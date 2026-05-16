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
use Symfony\UX\Image\Transformation;

class LocalProviderTest extends TestCase
{
    public function testRenderImageWithoutTransformation(): void
    {
        $provider = new LocalProvider();
        $image = new Image('/images/photo.jpg', 'A photo');

        $html = $provider->renderImage($image);

        $this->assertSame('<img src="/images/photo.jpg" alt="A photo" loading="lazy" />', $html);
    }

    public function testRenderImageWithTransformation(): void
    {
        $provider = new LocalProvider();
        $image = (new Image('/images/photo.jpg'))
            ->transform(
                Transformation::create()
                    ->width(800)
                    ->height(600)
                    ->format('webp')
                    ->quality(85)
                    ->fit('cover')
            );

        $html = $provider->renderImage($image);

        $this->assertStringStartsWith('<img src="/_image?', $html);
        $this->assertStringContainsString('src=%2Fimages%2Fphoto.jpg', $html);
        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('h=600', $html);
        $this->assertStringContainsString('format=webp', $html);
        $this->assertStringContainsString('q=85', $html);
        $this->assertStringContainsString('fit=cover', $html);
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

    public function testRenderImageWithCustomEndpoint(): void
    {
        $provider = new LocalProvider('/resize');
        $image = (new Image('/images/photo.jpg'))
            ->transform(Transformation::create()->width(400));

        $html = $provider->renderImage($image);

        $this->assertStringStartsWith('<img src="/resize?', $html);
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
}
