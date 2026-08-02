<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\UX\Image\Dimensions\CachedDimensionsReader;
use Symfony\UX\Image\Dimensions\DimensionsReaderInterface;
use Symfony\UX\Image\ImageDimensions;

final class CachedDimensionsReaderTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'ux_image_');
        file_put_contents($this->file, 'not really an image');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testTheInnerReaderIsCalledOnlyOnce(): void
    {
        $inner = $this->countingReader(new ImageDimensions(1600, 900));
        $reader = new CachedDimensionsReader($inner, new ArrayAdapter());

        $first = $reader->read($this->file);
        $second = $reader->read($this->file);

        self::assertSame(1600, $first?->width);
        self::assertSame(1600, $second?->width);
        self::assertSame(900, $second?->height);
        self::assertSame(1, $inner->calls);
    }

    /**
     * Misses must be cached too, otherwise an unreadable file is probed on every
     * single render.
     */
    public function testMissesAreCached(): void
    {
        $inner = $this->countingReader(null);
        $reader = new CachedDimensionsReader($inner, new ArrayAdapter());

        self::assertNull($reader->read($this->file));
        self::assertNull($reader->read($this->file));
        self::assertSame(1, $inner->calls);
    }

    /**
     * The modification time is part of the cache key, so editing a file yields a
     * fresh read without any explicit invalidation.
     */
    public function testTouchingTheFileBypassesTheCachedEntry(): void
    {
        $inner = $this->countingReader(new ImageDimensions(1600, 900));
        $reader = new CachedDimensionsReader($inner, new ArrayAdapter());

        $reader->read($this->file);
        touch($this->file, time() + 10);
        clearstatcache(true, $this->file);
        $reader->read($this->file);

        self::assertSame(2, $inner->calls);
    }

    public function testAMissingFileIsNotCached(): void
    {
        $inner = $this->countingReader(new ImageDimensions(1, 1));
        $reader = new CachedDimensionsReader($inner, new ArrayAdapter());

        self::assertNull($reader->read('/does/not/exist.png'));
        self::assertSame(0, $inner->calls);
    }

    private function countingReader(?ImageDimensions $dimensions): DimensionsReaderInterface
    {
        return new class($dimensions) implements DimensionsReaderInterface {
            public int $calls = 0;

            public function __construct(private readonly ?ImageDimensions $dimensions)
            {
            }

            public function read(string $path): ?ImageDimensions
            {
                ++$this->calls;

                return $this->dimensions;
            }
        };
    }
}
