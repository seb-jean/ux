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

    // --- Twig Component render() ---

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
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'transform' => ['width' => 800, 'format' => 'webp'],
        ]);

        $this->assertStringContainsString('src="/_image?', $html);
        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('format=webp', $html);
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

    // --- <twig:UX:Image> flat transform-* attributes ---

    public function testComponentRenderWithFlatTransformAttributes(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'transformWidth' => 800,
            'transformHeight' => 600,
            'transformFormat' => 'webp',
            'transformQuality' => 85,
            'transformFit' => 'cover',
        ]);

        $this->assertStringContainsString('src="/_image?', $html);
        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('h=600', $html);
        $this->assertStringContainsString('format=webp', $html);
        $this->assertStringContainsString('q=85', $html);
        $this->assertStringContainsString('fit=cover', $html);
    }

    public function testFlatTransformAttributesMergeWithTransformArray(): void
    {
        // transform array sets width, flat attribute overrides with height
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'transform' => ['width' => 800, 'format' => 'jpeg'],
            'transformFormat' => 'webp', // overrides the array value
        ]);

        $this->assertStringContainsString('w=800', $html);
        $this->assertStringContainsString('format=webp', $html);
        $this->assertStringNotContainsString('format=jpeg', $html);
    }

    public function testFlatTransformAttributesDoNotLeakAsHtmlAttributes(): void
    {
        $html = $this->runtime->render([
            'src' => '/images/photo.jpg',
            'transformWidth' => 800,
            'class' => 'hero',
        ]);

        $this->assertStringNotContainsString('transformWidth', $html);
        $this->assertStringNotContainsString('transform-width', $html);
        $this->assertStringContainsString('class="hero"', $html);
    }
}
