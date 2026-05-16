<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Twig;

use Symfony\UX\Image\Image;
use Symfony\UX\Image\Provider\Providers;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Providers $providers,
    ) {
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    public function renderImage(Image $image, array $attributes = []): string
    {
        return $this->providers->renderImage($image, $attributes);
    }
}
