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
 * Thrown when a transformation is known but not implemented yet.
 */
final class UnsupportedTransformationException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $parameter,
        string $reason = 'it is not supported yet',
    ) {
        parent::__construct(\sprintf('The image transformation "%s" cannot be applied because %s.', $parameter, $reason));
    }
}
