<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\Image\Cache\ImageCache;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Transformations;

class ImageCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/ux-image-cache/'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    private function createSource(string $relativePath = 'images/photo.jpg', int $modifiedAt = 1750000000): ImageSource
    {
        return new ImageSource('/app/public/'.$relativePath, $relativePath, Format::Jpeg, $modifiedAt);
    }

    public function testKeyDependsOnTheSourceTheTransformationsAndTheFormat(): void
    {
        $cache = new ImageCache($this->directory);
        $transformations = Transformations::fromArray(['width' => 400]);
        $key = $cache->key($this->createSource(), $transformations, Format::Webp);

        $this->assertSame($key, $cache->key($this->createSource(), $transformations, Format::Webp));
        $this->assertNotSame($key, $cache->key($this->createSource('images/other.jpg'), $transformations, Format::Webp));
        $this->assertNotSame($key, $cache->key($this->createSource(modifiedAt: 1760000000), $transformations, Format::Webp));
        $this->assertNotSame($key, $cache->key($this->createSource(), Transformations::fromArray(['width' => 401]), Format::Webp));
        $this->assertNotSame($key, $cache->key($this->createSource(), $transformations, Format::Avif));
    }

    public function testEquivalentTransformationsShareACacheEntry(): void
    {
        $cache = new ImageCache($this->directory);

        $this->assertSame(
            $cache->key($this->createSource(), Transformations::fromArray(['width' => 400, 'bw' => true]), Format::Webp),
            $cache->key($this->createSource(), Transformations::fromArray(['bw' => 'true', 'width' => '400']), Format::Webp),
        );
    }

    public function testPathIsShardedAndUsesTheFormatExtension(): void
    {
        $cache = new ImageCache($this->directory);
        $key = $cache->key($this->createSource(), Transformations::none(), Format::Jpeg);

        $this->assertSame(
            \sprintf('%s/%s/%s/%s.jpg', $this->directory, substr($key, 0, 2), substr($key, 2, 2), $key),
            $cache->path($key, Format::Jpeg),
        );
    }

    public function testLookupReturnsNullUntilTheImageIsWritten(): void
    {
        $cache = new ImageCache($this->directory);
        $key = $cache->key($this->createSource(), Transformations::none(), Format::Webp);

        $this->assertNull($cache->lookup($key, Format::Webp));

        $path = $cache->write($key, Format::Webp, 'binary');

        $this->assertSame($path, $cache->lookup($key, Format::Webp));
        $this->assertSame('binary', file_get_contents($path));
    }

    public function testWriteCreatesMissingDirectories(): void
    {
        $cache = new ImageCache($this->directory.'/deeply/nested');

        $path = $cache->write('0123456789abcdef', Format::Png, 'binary');

        $this->assertFileExists($path);
    }
}
