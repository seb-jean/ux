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
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

class LocalProviderTest extends TestCase
{
    private StimulusHelper $stimulus;

    protected function setUp(): void
    {
        $this->stimulus = new StimulusHelper(null);
    }

    public function testRenderImageWithoutTransformation(): void
    {
        $provider = new LocalProvider($this->stimulus);
        $image = new Image('/images/photo.jpg', 'A photo');

        $html = $provider->renderImage($image);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('data-controller="symfony--ux-image--local"', $html);
        $this->assertStringContainsString('data-symfony--ux-image--local-src-value="/images/photo.jpg"', $html);
        $this->assertStringContainsString('data-symfony--ux-image--local-alt-value="A photo"', $html);
    }

    public function testRenderImageWithTransformation(): void
    {
        $provider = new LocalProvider($this->stimulus);
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

        $this->assertStringContainsString('data-symfony--ux-image--local-transformation-value=', $html);
        $decoded = html_entity_decode($html);
        $this->assertStringContainsString('"width":800', $decoded);
        $this->assertStringContainsString('"height":600', $decoded);
        $this->assertStringContainsString('"format":"webp"', $decoded);
        $this->assertStringContainsString('"quality":85', $decoded);
        $this->assertStringContainsString('"fit":"cover"', $decoded);
    }

    public function testRenderImageWithCustomEndpoint(): void
    {
        $provider = new LocalProvider($this->stimulus, '/resize');
        $image = new Image('/images/photo.jpg');

        $html = $provider->renderImage($image);

        $decoded = html_entity_decode($html);
        $this->assertMatchesRegularExpression('/"endpoint":"\\\\?\/resize"/', $decoded);
    }

    public function testRenderImageWithExtraAttributes(): void
    {
        $provider = new LocalProvider($this->stimulus);
        $image = new Image('/images/photo.jpg');

        $html = $provider->renderImage($image, ['class' => 'hero-image', 'data-id' => '42']);

        $this->assertStringContainsString('class="hero-image"', $html);
        $this->assertStringContainsString('data-id="42"', $html);
    }

    public function testToString(): void
    {
        $provider = new LocalProvider($this->stimulus);
        $this->assertSame('local', (string) $provider);
    }
}
