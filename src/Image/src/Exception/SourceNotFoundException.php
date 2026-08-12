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
 * Thrown when a source image cannot be resolved inside the public directory.
 */
final class SourceNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $path,
        string $reason = 'it does not exist',
    ) {
        parent::__construct(\sprintf('The source image "%s" cannot be used because %s.', $path, $reason));
    }
}
