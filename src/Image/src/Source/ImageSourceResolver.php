<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Source;

use Symfony\UX\Image\Exception\SourceNotFoundException;
use Symfony\UX\Image\Transformation\Format;

/**
 * Resolves a public path to a file on disk.
 *
 * Everything this class rejects is reachable from a URL, so it is deliberately
 * strict: only regular, readable image files that really live inside the public
 * directory are returned.
 */
final class ImageSourceResolver
{
    /** @var array<string, ImageSource> */
    private array $cache = [];

    private ?string $realPublicDir = null;

    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function resolve(string $path): ImageSource
    {
        return $this->cache[$path] ??= $this->doResolve($path);
    }

    private function doResolve(string $path): ImageSource
    {
        if ('' === trim($path)) {
            throw new SourceNotFoundException($path, 'it is empty');
        }

        if (str_contains($path, "\0")) {
            throw new SourceNotFoundException($path, 'it contains a null byte');
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) || str_starts_with($path, '//')) {
            throw new SourceNotFoundException($path, 'only paths inside the public directory can be transformed');
        }

        // the query string and fragment of an asset URL are not part of the file name
        $relativePath = ltrim(explode('#', explode('?', $path)[0])[0], '/');

        if ('' === $relativePath) {
            throw new SourceNotFoundException($path, 'it is empty');
        }

        $format = Format::tryFromPath($relativePath);

        if (null === $format) {
            throw new SourceNotFoundException($path, \sprintf('"%s" is not a supported image extension', pathinfo($relativePath, \PATHINFO_EXTENSION)));
        }

        $realPublicDir = $this->realPublicDir ??= realpath($this->publicDir) ?: throw new SourceNotFoundException($path, \sprintf('the public directory "%s" does not exist', $this->publicDir));
        $realPath = realpath($realPublicDir.\DIRECTORY_SEPARATOR.$relativePath);

        if (false === $realPath) {
            throw new SourceNotFoundException($path, 'it does not exist');
        }

        // realpath() has resolved "..", symlinks and Windows short names: whatever
        // the request looked like, the file has to sit inside the public directory
        if (!str_starts_with($realPath, $realPublicDir.\DIRECTORY_SEPARATOR)) {
            throw new SourceNotFoundException($path, 'it is outside the public directory');
        }

        if (!is_file($realPath) || !is_readable($realPath)) {
            throw new SourceNotFoundException($path, 'it is not a readable file');
        }

        return new ImageSource(
            path: $realPath,
            relativePath: str_replace('\\', '/', substr($realPath, \strlen($realPublicDir) + 1)),
            format: $format,
            modifiedAt: filemtime($realPath) ?: 0,
        );
    }
}
