<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Cache;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Stores transformed images on disk.
 *
 * The key covers the source file, its modification time, the transformations and
 * the output format, so a cache entry can never be served for anything else, and
 * touching the source file invalidates every derivative of it.
 */
final class ImageCache
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $directory,
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function key(ImageSource $source, Transformations $transformations, Format $format): string
    {
        return hash('xxh128', implode("\n", [
            $source->relativePath,
            $source->modifiedAt,
            http_build_query($transformations->toQuery()),
            $format->value,
        ]));
    }

    public function path(string $key, Format $format): string
    {
        return \sprintf('%s/%s/%s/%s.%s', rtrim($this->directory, '/\\'), substr($key, 0, 2), substr($key, 2, 2), $key, $format->extension());
    }

    /**
     * @return string|null the path of the cached image, or null when it has to be generated
     */
    public function lookup(string $key, Format $format): ?string
    {
        $path = $this->path($key, $format);

        return is_file($path) ? $path : null;
    }

    /**
     * @return string the path of the cached image
     */
    public function write(string $key, Format $format, string $contents): string
    {
        $path = $this->path($key, $format);

        // dumpFile() writes to a temporary file and renames it, so a concurrent
        // request either sees no file at all or a complete one
        $this->filesystem->dumpFile($path, $contents);

        return $path;
    }
}
