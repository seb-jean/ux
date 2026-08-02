<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Asset;

use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\UX\Image\Dimensions\DimensionsReaderInterface;
use Symfony\UX\Image\ImageSource;

/**
 * Resolves a "src" through the Asset component (versioned URL) and, when the
 * source is a local asset, locates it on disk to read its real dimensions.
 * Works with AssetMapper, Webpack Encore and Symfony Reprise since all of them
 * feed the Asset component.
 *
 * @author Symfony Community
 */
final class AssetSourceResolver implements SourceResolverInterface
{
    public function __construct(
        private readonly ?Packages $packages,
        private readonly DimensionsReaderInterface $dimensions,
        private readonly ?AssetMapperInterface $assetMapper = null,
        private readonly ?string $publicDir = null,
    ) {
    }

    public function resolve(string $src): ImageSource
    {
        // Absolute URLs and data URIs are used verbatim (remote, no local read).
        if (str_starts_with($src, 'data:') || preg_match('#^(https?:)?//#', $src)) {
            return new ImageSource($src, $src);
        }

        $url = $this->packages?->getUrl($src) ?? '/'.ltrim($src, '/');
        $path = $this->locate($src);
        $dimensions = null !== $path ? $this->dimensions->read($path) : null;

        return new ImageSource($src, $url, $path, $dimensions);
    }

    private function locate(string $src): ?string
    {
        if (null !== $this->assetMapper && null !== ($asset = $this->assetMapper->getAsset($src))) {
            return $asset->sourcePath;
        }

        if (null !== $this->publicDir) {
            $candidate = rtrim($this->publicDir, '/').'/'.ltrim($src, '/');
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
