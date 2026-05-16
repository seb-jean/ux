<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Provider\Local\LocalProvider;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Twig\ImageRuntime;

class ImageRuntimeTest extends TestCase
{
    private ImageRuntime $runtime;

    protected function setUp(): void
    {
        $providers = new Providers(['default' => new LocalProvider()]);
        $this->runtime = new ImageRuntime($providers);
    }

    // --- ux_image() Twig function ---

    public function testRenderWithSrcOnly(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg');

        $this->assertStringContainsString('src="/images/photo.jpg"', $html);
    }

    public function testRenderWithOptions(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', [
            'alt' => 'A photo',
            'width' => 800,
            'height' => 600,
            'loading' => 'eager',
        ]);

        $this->assertStringContainsString('alt="A photo"', $html);
        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="600"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function testRenderWithTransform(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', [
            'transform' => ['width' => 800, 'format' => 'webp', 'quality' => 85, 'fit' => 'cover'],
        ]);

        $this->assertStringContainsString('src="/_image?', $html);
        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('format=webp', $html);
        $this->assertStringContainsString('q=85', $html);
        $this->assertStringContainsString('fit=cover', $html);
    }

    public function testRenderWithExtraAttributes(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', [], ['class' => 'hero', 'id' => 'main-img']);

        $this->assertStringContainsString('class="hero"', $html);
        $this->assertStringContainsString('id="main-img"', $html);
    }

    // --- {% component %} and <twig:UX:Image> via render() ---

    public function testComponentRenderWithSrcOnly(): void
    {
        $html = $this->runtime->render(['src' => '/images/photo.jpg']);

        $this->assertStringContainsString('src="/images/photo.jpg"', $html);
    }

    public function testComponentRenderWithOptions(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'alt' => 'A photo',
            'width' => 800,
            'height' => 600,
            'loading' => 'eager',
        ]);

        $this->assertStringContainsString('src="/images/photo.jpg"', $html);
        $this->assertStringContainsString('alt="A photo"', $html);
        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="600"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function testComponentRenderWithTransform(): void
    {
        // {% component %} and <twig:UX:Image :transform="{ width: 800, format: 'webp' }" />
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'transform' => ['width' => 800, 'format' => 'webp', 'quality' => 85],
        ]);

        $this->assertStringContainsString('src="/_image?', $html);
        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('format=webp', $html);
        $this->assertStringContainsString('q=85', $html);
    }

    public function testComponentRenderExtraArgsBecomHtmlAttributes(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'class' => 'hero',
            'data-id' => '42',
        ]);

        $this->assertStringContainsString('class="hero"', $html);
        $this->assertStringContainsString('data-id="42"', $html);
    }

    public function testComponentRenderRequiresSrc(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runtime->render(['alt' => 'Photo']);
    }

    // --- sources / <picture> support ---

    public function testRenderImageWithSourcesReturnsPictureTag(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', [
            'sources' => [
                ['srcset' => '/images/photo.avif', 'type' => 'image/avif'],
                ['srcset' => '/images/photo.webp', 'type' => 'image/webp'],
            ],
        ]);

        $this->assertStringStartsWith('<picture>', $html);
        $this->assertStringEndsWith('</picture>', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.avif" type="image/avif">', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.webp" type="image/webp">', $html);
        $this->assertStringContainsString('<img src="/images/photo.jpg"', $html);
    }

    public function testRenderImageWithSourceIncludesMediaAndSizes(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', [
            'sources' => [
                [
                    'srcset' => '/images/photo.webp',
                    'type' => 'image/webp',
                    'media' => '(min-width: 800px)',
                    'sizes' => '80vw',
                ],
            ],
        ]);

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('media="(min-width: 800px)"', $html);
        $this->assertStringContainsString('sizes="80vw"', $html);
    }

    public function testComponentRenderWithSources(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'sources' => [
                ['srcset' => '/images/photo.avif', 'type' => 'image/avif'],
                ['srcset' => '/images/photo.webp', 'type' => 'image/webp'],
            ],
        ]);

        $this->assertStringStartsWith('<picture>', $html);
        $this->assertStringEndsWith('</picture>', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.avif" type="image/avif">', $html);
        $this->assertStringContainsString('<source srcset="/images/photo.webp" type="image/webp">', $html);
    }

    public function testRenderImageWithDecodingOption(): void
    {
        $html = $this->runtime->renderImage('/images/photo.jpg', ['decoding' => 'sync']);

        $this->assertStringContainsString('decoding="sync"', $html);
    }

    public function testComponentRenderWithDecodingOption(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'decoding' => 'auto',
        ]);

        $this->assertStringContainsString('decoding="auto"', $html);
    }
}
