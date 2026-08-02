<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Cloudinary;

use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
use Symfony\UX\Image\Provider\ImageProviderInterface;

/**
 * DSN:
 *   cloudinary://<cloud_name>                                    (public, unsigned)
 *   cloudinary://<api_key>:<api_secret>@<cloud_name>?sign_urls=true
 *   cloudinary://<cloud_name>?delivery=fetch&secure=true
 *
 * @author Symfony Community
 */
final class CloudinaryProviderFactory implements ImageProviderFactoryInterface
{
    public function create(Dsn $dsn): ImageProviderInterface
    {
        return new CloudinaryProvider(
            cloudName: $dsn->getHost(),
            apiSecret: $dsn->getPassword(),
            signUrls: $dsn->getBooleanOption('sign_urls'),
            secure: $dsn->getBooleanOption('secure', true),
            deliveryType: (string) $dsn->getOption('delivery', 'upload'),
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return 'cloudinary' === $dsn->getScheme();
    }
}
