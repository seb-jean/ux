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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\UX\Image\Tests\Fixtures\TestKernel;
use Symfony\UX\Image\Transformation\Transformations;
use Symfony\UX\Image\Url\ImageUrlGenerator;

#[RequiresPhpExtension('gd')]
class ImageControllerTest extends TestCase
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

    /**
     * @param array<string, mixed> $transformations
     */
    private function url(string $path, array $transformations = []): string
    {
        $container = $this->kernel->getContainer();

        return $container->get('test.ux_image.url_generator')->generate(
            $container->get('test.ux_image.source_resolver')->resolve($path),
            Transformations::fromArray($transformations),
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function request(string $uri, array $server = []): Response
    {
        return $this->kernel->handle(Request::create($uri, server: $server), HttpKernelInterface::MAIN_REQUEST, false);
    }

    public function testItServesATransformedImage(): void
    {
        $response = $this->request($this->url('/photo.jpg', ['width' => 40]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        $contents = $this->contentsOf($response);
        $size = getimagesizefromstring($contents);

        $this->assertSame([40, 30], [$size[0], $size[1]]);
    }

    public function testItConvertsTheFormat(): void
    {
        $response = $this->request($this->url('/photo.jpg', ['width' => 40, 'format' => 'png']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('image/png', getimagesizefromstring($this->contentsOf($response))['mime']);
    }

    public function testTheResponseIsCacheableForever(): void
    {
        $response = $this->request($this->url('/photo.jpg', ['width' => 40]));

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertNotNull($response->headers->get('Last-Modified'));
    }

    public function testANotModifiedResponseIsReturnedForAKnownETag(): void
    {
        $url = $this->url('/photo.jpg', ['width' => 40]);
        $etag = $this->request($url)->headers->get('ETag');

        $response = $this->request($url, ['HTTP_IF_NONE_MATCH' => $etag]);

        $this->assertSame(304, $response->getStatusCode());
    }

    public function testTheImageIsGeneratedOnceAndThenServedFromDisk(): void
    {
        $url = $this->url('/photo.jpg', ['width' => 37]);

        $this->assertCount(0, $this->cachedFiles(), 'nothing is generated before the first request');

        $first = $this->contentsOf($this->request($url));

        $this->assertCount(1, $this->cachedFiles(), 'the first request generates the image');

        $second = $this->contentsOf($this->request($url));

        $this->assertCount(1, $this->cachedFiles(), 'the second request reuses it');
        $this->assertSame($first, $second);
    }

    /**
     * The whole point of computing the geometry twice: what Twig writes in the
     * tag has to be what the browser eventually downloads.
     */
    public function testTheRenderedTagAdvertisesTheSizeTheControllerProduces(): void
    {
        $twig = $this->kernel->getContainer()->get('test.twig');

        foreach ([
            '{width: 40}',
            '{width: 40, height: 40, fit: "crop"}',
            '{width: 40, height: 40, fit: "cover"}',
            '{width: 100, height: 30}',
            '{crop: "1:1", width: 25}',
            '{crop: "30,20,x5,y5"}',
            '{orient: "r", width: 30}',
            '{width: 40, dpr: 2}',
            '{}',
        ] as $transformations) {
            $html = $twig->createTemplate(\sprintf('{{ ux_image("/photo.jpg", %s) }}', $transformations))->render();

            preg_match('/src="([^"]+)"/', $html, $src);
            preg_match('/width="(\d+)"/', $html, $width);
            preg_match('/height="(\d+)"/', $html, $height);

            $contents = $this->contentsOf($this->request(html_entity_decode($src[1])));
            $size = getimagesizefromstring($contents);

            // "dpr" is the one case where the file is deliberately denser than the tag
            $dpr = str_contains($transformations, 'dpr: 2') ? 2 : 1;

            $this->assertSame(
                [(int) $width[1] * $dpr, (int) $height[1] * $dpr],
                [$size[0], $size[1]],
                \sprintf('the tag and the file agree for %s', $transformations),
            );
        }
    }

    public function testAnUnsignedUrlIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->request('/_image/photo.jpg?width=40');
    }

    public function testATamperedTransformationIsRejected(): void
    {
        $url = str_replace('width=40', 'width=4000', $this->url('/photo.jpg', ['width' => 40]));

        $this->expectException(AccessDeniedHttpException::class);

        $this->request($url);
    }

    public function testATamperedPathIsRejected(): void
    {
        $url = str_replace('/photo.jpg', '/portrait.png', $this->url('/photo.jpg', ['width' => 40]));

        $this->expectException(AccessDeniedHttpException::class);

        $this->request($url);
    }

    public function testAnAddedTransformationIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->request($this->url('/photo.jpg', ['width' => 40]).'&bw=true');
    }

    public function testAMissingSourceIsNotFound(): void
    {
        $url = str_replace('/photo.jpg', '/nope.jpg', $this->url('/photo.jpg'));
        $url = $this->resign($url, 'nope.jpg');

        $this->expectException(NotFoundHttpException::class);

        $this->request($url);
    }

    public function testATransformationBeyondTheLimitsIsRejectedEvenWhenSigned(): void
    {
        $kernel = new TestKernel(imageConfig: ['max_width' => 100]);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $url = $container->get('test.ux_image.url_generator')->generate(
                $container->get('test.ux_image.source_resolver')->resolve('/photo.jpg'),
                Transformations::fromArray(['width' => 500]),
            );

            $this->expectException(BadRequestHttpException::class);

            $kernel->handle(Request::create($url), HttpKernelInterface::MAIN_REQUEST, false);
        } finally {
            $kernel->shutdown();
            $kernel->cleanup();
        }
    }

    public function testAutoNegotiatesWebpWhenTheClientAcceptsIt(): void
    {
        if (!(gd_info()['WebP Support'] ?? false)) {
            $this->markTestSkipped('GD was built without WebP support.');
        }

        $url = $this->url('/photo.jpg', ['width' => 40, 'auto' => 'webp']);

        $accepting = $this->request($url, ['HTTP_ACCEPT' => 'image/webp,image/*,*/*']);
        $notAccepting = $this->request($url, ['HTTP_ACCEPT' => 'image/*,*/*']);

        $this->assertSame('image/webp', $accepting->headers->get('Content-Type'));
        $this->assertSame('image/jpeg', $notAccepting->headers->get('Content-Type'));
        $this->assertSame('Accept', $accepting->headers->get('Vary'));
    }

    public function testWithoutAutoTheResponseDoesNotVary(): void
    {
        $response = $this->request($this->url('/photo.jpg', ['width' => 40]), ['HTTP_ACCEPT' => 'image/webp,*/*']);

        $this->assertNull($response->headers->get('Vary'));
    }

    private function resign(string $url, string $path): string
    {
        parse_str(parse_url($url, \PHP_URL_QUERY), $query);
        unset($query[ImageUrlGenerator::HASH_PARAMETER]);

        $query[ImageUrlGenerator::HASH_PARAMETER] = $this->kernel->getContainer()->get('test.ux_image.uri_signer')->sign($path, $query);

        return parse_url($url, \PHP_URL_PATH).'?'.http_build_query($query);
    }

    /**
     * @return list<string>
     */
    private function cachedFiles(): array
    {
        $directory = $this->kernel->getCacheDir().'/ux_image';

        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function contentsOf(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }
}
