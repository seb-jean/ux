<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Exception;

use Symfony\UX\Image\Provider\Dsn;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
class UnsupportedSchemeException extends InvalidArgumentException
{
    public function __construct(Dsn $dsn)
    {
        parent::__construct(\sprintf('The image provider DSN scheme "%s" is not supported.', $dsn->getScheme()));
    }
}
