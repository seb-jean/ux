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
 * Thrown when neither the "gd" nor the "imagick" extension is available.
 */
final class ProcessorNotAvailableException extends RuntimeException
{
}
