<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider\Local;

use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
use Symfony\UX\Image\Provider\ImageProviderInterface;

/**
 * Handles the "default://" scheme, shipped with ux-image.
 *
 * @author Symfony Community
 */
final class LocalImageProviderFactory implements ImageProviderFactoryInterface
{
    public function create(Dsn $dsn): ImageProviderInterface
    {
        return new LocalImageProvider();
    }

    public function supports(Dsn $dsn): bool
    {
        return 'default' === $dsn->getScheme();
    }
}
