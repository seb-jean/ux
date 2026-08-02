<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Fastly;

use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
use Symfony\UX\Image\Provider\ImageProviderInterface;

/**
 * DSN:
 *   fastly://default                        (asset() is already fronted by Fastly IO)
 *   fastly://cdn.example.com?auto=webp
 *   fastly://<sign_key>@cdn.example.com     (protected URLs)
 *
 * @author Symfony Community
 */
final class FastlyProviderFactory implements ImageProviderFactoryInterface
{
    public function create(Dsn $dsn): ImageProviderInterface
    {
        return new FastlyProvider(
            host: $dsn->getHost(),
            signKey: $dsn->getUser() ?? $dsn->getOption('sign_key'),
            auto: $dsn->getOption('auto', 'webp'),
            secure: $dsn->getBooleanOption('secure', true),
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return 'fastly' === $dsn->getScheme();
    }
}
