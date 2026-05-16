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

use Symfony\UX\Image\Image;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
interface ProviderInterface extends \Stringable
{
    /**
     * @param array<string, string|bool> $attributes HTML attributes to add to the rendered element
     */
    public function renderImage(Image $image, array $attributes = []): string;
}
