<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider;

use Symfony\UX\Image\Dsn;

/**
 * Builds an {@see ImageProviderInterface} from a DSN. Bridges provide one factory
 * each, tagged "ux_image.provider_factory".
 *
 * @author Symfony Community
 */
interface ImageProviderFactoryInterface
{
    public function create(Dsn $dsn): ImageProviderInterface;

    public function supports(Dsn $dsn): bool;
}
