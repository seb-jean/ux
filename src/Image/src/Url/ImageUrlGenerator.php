<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Url;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Builds the signed URL an <img> tag points to.
 */
final class ImageUrlGenerator
{
    public const ROUTE = 'ux_image';
    public const HASH_PARAMETER = '_hash';
    public const VERSION_PARAMETER = 'v';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ImageUriSigner $signer,
    ) {
    }

    public function generate(ImageSource $source, Transformations $transformations, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $query = $transformations->toQuery();

        // the version changes whenever the source file does, which is what makes
        // it safe to serve transformed images as immutable
        $query[self::VERSION_PARAMETER] = self::version($source);
        ksort($query);

        $query[self::HASH_PARAMETER] = $this->signer->sign($source->relativePath, $query);

        return $this->urlGenerator->generate(self::ROUTE, ['path' => $source->relativePath] + $query, $referenceType);
    }

    public static function version(ImageSource $source): string
    {
        return substr(hash('xxh128', $source->relativePath.':'.$source->modifiedAt), 0, 8);
    }
}
