<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Integration;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\SourceNotFoundException;
use Symfony\UX\Image\Exception\UnsupportedTransformationException;
use Symfony\UX\Image\Tests\Fixtures\TestKernel;
use Twig\Environment;
use Twig\Error\RuntimeError;

#[RequiresPhpExtension('gd')]
class TwigIntegrationTest extends TestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel();
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        $this->kernel->cleanup();
    }

    private function render(string $template, array $context = []): string
    {
        /** @var Environment $twig */
        $twig = $this->kernel->getContainer()->get('test.twig');

        return $twig->createTemplate($template)->render($context);
    }

    public function testTheFunctionRendersAnImgTag(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", {width: 40}) }}');

        $this->assertStringStartsWith('<img src="/_image/photo.jpg?', $html);
        $this->assertStringEndsWith('>', $html);
        $this->assertStringContainsString('width="40"', $html);
        $this->assertStringContainsString('height="30"', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('decoding="async"', $html);
    }

    public function testTheDimensionsMatchWhatTheControllerWillProduce(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", {width: 40, height: 40, fit: "crop"}) }}');

        $this->assertStringContainsString('width="40"', $html);
        $this->assertStringContainsString('height="40"', $html);
    }

    public function testDprDoesNotChangeTheAdvertisedDimensions(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", {width: 40, dpr: 2}) }}');

        $this->assertStringContainsString('width="40"', $html);
        $this->assertStringContainsString('height="30"', $html);
    }

    public function testWithoutTransformations(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg") }}');

        $this->assertStringContainsString('width="80"', $html);
        $this->assertStringContainsString('height="60"', $html);
    }

    public function testAttributesAreAdded(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", {width: 40}, {alt: "A photo", class: "hero"}) }}');

        $this->assertStringContainsString('alt="A photo"', $html);
        $this->assertStringContainsString('class="hero"', $html);
    }

    public function testAttributesOverrideTheDefaults(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", {width: 40}, {loading: "eager", width: 100}) }}');

        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('width="100"', $html);
        $this->assertStringNotContainsString('width="40"', $html);
    }

    public function testBooleanAttributes(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", null, {inert: true, hidden: false}) }}');

        $this->assertStringContainsString(' inert>', $html);
        $this->assertStringNotContainsString('hidden', $html);
    }

    public function testAttributesAreEscaped(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", null, {alt: alt}) }}', ['alt' => '"><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testAPreset(): void
    {
        $html = $this->render('{{ ux_image("/photo.jpg", "thumbnail") }}');

        $this->assertStringContainsString('width="20"', $html);
        $this->assertStringContainsString('height="20"', $html);
    }

    public function testAnUnknownPreset(): void
    {
        $this->assertRenderingFails('{{ ux_image("/photo.jpg", "nope") }}', InvalidArgumentException::class, 'The image preset "nope" is not configured.');
    }

    public function testTheUrlFunctionReturnsOnlyTheUrl(): void
    {
        $url = $this->render('{{ ux_image_url("/photo.jpg", {width: 40}) }}');

        $this->assertStringStartsWith('/_image/photo.jpg?', $url);
        $this->assertStringNotContainsString('<img', $url);
    }

    public function testTheUrlIsUsableAsIsInAnAttribute(): void
    {
        $html = $this->render('<div style="background-image: url({{ ux_image_url("/photo.jpg", {width: 40}) }})"></div>');

        $this->assertStringContainsString('/_image/photo.jpg?', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testAMissingSourceIsReported(): void
    {
        $this->assertRenderingFails('{{ ux_image("/nope.jpg") }}', SourceNotFoundException::class, 'it does not exist');
    }

    public function testAnUnsupportedTransformationIsReported(): void
    {
        $this->assertRenderingFails('{{ ux_image("/photo.jpg", {sharpen: 5}) }}', UnsupportedTransformationException::class, 'The image transformation "sharpen" cannot be applied');
    }

    public function testTheComponent(): void
    {
        $html = $this->render('<twig:UX:Image src="/photo.jpg" width="40" alt="A photo" class="hero" />');

        $this->assertStringStartsWith('<img src="/_image/photo.jpg?', $html);
        $this->assertStringContainsString('width="40"', $html);
        $this->assertStringContainsString('height="30"', $html);
        $this->assertStringContainsString('alt="A photo"', $html);
        $this->assertStringContainsString('class="hero"', $html);
    }

    public function testTheComponentAcceptsEveryTransformation(): void
    {
        $html = $this->render('<twig:UX:Image src="/photo.jpg" width="40" height="40" fit="crop" format="png" bw="true" />');

        $this->assertStringContainsString('width="40"', $html);
        $this->assertStringContainsString('height="40"', $html);
        $this->assertStringContainsString('format=png', urldecode($html));
        $this->assertStringContainsString('bw=true', urldecode($html));
    }

    public function testTheComponentAcceptsAPreset(): void
    {
        $html = $this->render('<twig:UX:Image src="/photo.jpg" preset="thumbnail" />');

        $this->assertStringContainsString('width="20"', $html);
        $this->assertStringContainsString('height="20"', $html);
    }

    public function testTheComponentRejectsAPresetMixedWithTransformations(): void
    {
        $this->assertRenderingFails('<twig:UX:Image src="/photo.jpg" preset="thumbnail" width="40" />', InvalidArgumentException::class, 'cannot be combined with the "width" transformation');
    }

    /**
     * Twig wraps anything a function throws, the useful exception is the previous one.
     *
     * @param class-string<\Throwable> $expectedClass
     */
    private function assertRenderingFails(string $template, string $expectedClass, string $expectedMessage): void
    {
        try {
            $this->render($template);
        } catch (RuntimeError $e) {
            $this->assertInstanceOf($expectedClass, $e->getPrevious());
            $this->assertStringContainsString($expectedMessage, $e->getPrevious()->getMessage());

            return;
        }

        $this->fail(\sprintf('Rendering "%s" should have thrown a "%s".', $template, $expectedClass));
    }

    public function testTheComponentPassesUnknownAttributesThrough(): void
    {
        $html = $this->render('<twig:UX:Image src="/photo.jpg" data-controller="zoom" />');

        $this->assertStringContainsString('data-controller="zoom"', $html);
    }
}
