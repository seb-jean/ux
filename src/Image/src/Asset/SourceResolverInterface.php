<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Asset;

use Symfony\UX\Image\ImageSource;

/**
 * Turns the "src" written in a template into a resolved {@see ImageSource}
 * (public URL + local path + intrinsic dimensions when available).
 *
 * @author Symfony Community
 */
interface SourceResolverInterface
{
    public function resolve(string $src): ImageSource;
}
