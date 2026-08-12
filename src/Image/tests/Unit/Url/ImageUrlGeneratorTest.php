<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Transformations;
use Symfony\UX\Image\Url\ImageUriSigner;
use Symfony\UX\Image\Url\ImageUrlGenerator;

class ImageUrlGeneratorTest extends TestCase
{
    private function createGenerator(): ImageUrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(ImageUrlGenerator::ROUTE, new Route('/_image/{path}', requirements: ['path' => '.+']));

        return new ImageUrlGenerator(
            new UrlGenerator($routes, new RequestContext()),
            new ImageUriSigner('a-secret'),
        );
    }

    private function createSource(string $relativePath = 'images/photo.jpg', int $modifiedAt = 1750000000): ImageSource
    {
        return new ImageSource('/app/public/'.$relativePath, $relativePath, Format::Jpeg, $modifiedAt);
    }

    public function testGenerate(): void
    {
        $url = $this->createGenerator()->generate($this->createSource(), Transformations::fromArray(['width' => 400, 'format' => 'webp']));

        $this->assertStringStartsWith('/_image/images/photo.jpg?', $url);

        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertSame('400', $query['width']);
        $this->assertSame('webp', $query['format']);
        $this->assertArrayHasKey('v', $query);
        $this->assertArrayHasKey('_hash', $query);
    }

    public function testSlashesInThePathAreKept(): void
    {
        $url = $this->createGenerator()->generate($this->createSource('images/nested/deep.png'), Transformations::none());

        $this->assertStringStartsWith('/_image/images/nested/deep.png?', $url);
    }

    public function testTheSameTransformationsAlwaysProduceTheSameUrl(): void
    {
        $generator = $this->createGenerator();

        $first = $generator->generate($this->createSource(), Transformations::fromArray(['width' => 400, 'fit' => 'cover']));
        $second = $generator->generate($this->createSource(), Transformations::fromArray(['fit' => 'cover', 'width' => 400]));

        $this->assertSame($first, $second);
    }

    public function testTheUrlChangesWhenTheSourceChanges(): void
    {
        $generator = $this->createGenerator();

        $before = $generator->generate($this->createSource(modifiedAt: 1750000000), Transformations::none());
        $after = $generator->generate($this->createSource(modifiedAt: 1760000000), Transformations::none());

        $this->assertNotSame($before, $after);
    }

    public function testTheSignatureCoversThePathAndEveryTransformation(): void
    {
        $signer = new ImageUriSigner('a-secret');
        $url = $this->createGenerator()->generate($this->createSource(), Transformations::fromArray(['width' => 400]));

        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        $hash = $query['_hash'];
        unset($query['_hash']);

        $this->assertTrue($signer->isValid('images/photo.jpg', $query, $hash));
        $this->assertFalse($signer->isValid('images/other.jpg', $query, $hash), 'the path is signed');
        $this->assertFalse($signer->isValid('images/photo.jpg', ['width' => '4000'] + $query, $hash), 'the transformations are signed');
        $this->assertFalse($signer->isValid('images/photo.jpg', $query + ['bw' => 'true'], $hash), 'an added transformation invalidates the signature');
    }
}
