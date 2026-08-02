<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Dimensions;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\UX\Image\ImageDimensions;

/**
 * Caches dimension reads, which would otherwise hit the filesystem once per
 * image and per request.
 *
 * The modification time is part of the cache key, so editing an image yields a
 * new key: no invalidation is needed, and development stays correct.
 *
 * @author Symfony Community
 */
final class CachedDimensionsReader implements DimensionsReaderInterface
{
    public function __construct(
        private readonly DimensionsReaderInterface $inner,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function read(string $path): ?ImageDimensions
    {
        if (false === $mtime = @filemtime($path)) {
            return null;
        }

        $item = $this->cache->getItem('ux_image.'.hash('xxh128', $path).'.'.$mtime);

        if ($item->isHit()) {
            /** @var array{0: int, 1: int}|null $cached */
            $cached = $item->get();

            return null === $cached ? null : new ImageDimensions($cached[0], $cached[1]);
        }

        $dimensions = $this->inner->read($path);

        // Misses are cached too: an unreadable file must not be probed again on
        // every render.
        $item->set(null === $dimensions ? null : [$dimensions->width, $dimensions->height]);
        $this->cache->save($item);

        return $dimensions;
    }
}
