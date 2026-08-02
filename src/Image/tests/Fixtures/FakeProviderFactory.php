<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Fixtures;

use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
use Symfony\UX\Image\Provider\ImageProviderInterface;

final class FakeProviderFactory implements ImageProviderFactoryInterface
{
    public function create(Dsn $dsn): ImageProviderInterface
    {
        return new FakeProvider();
    }

    public function supports(Dsn $dsn): bool
    {
        return 'fake' === $dsn->getScheme();
    }
}
