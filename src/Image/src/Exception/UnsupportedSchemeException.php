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

/**
 * Thrown when no provider factory supports the given DSN scheme.
 *
 * @author Symfony Community
 */
final class UnsupportedSchemeException extends InvalidArgumentException
{
}
