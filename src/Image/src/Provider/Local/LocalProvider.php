<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Provider\Local;

use Symfony\UX\Image\Image;
use Symfony\UX\Image\Provider\ProviderInterface;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

/**
 * Provider for locally-stored images, serving transformations via a Symfony route.
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class LocalProvider implements ProviderInterface
{
    public function __construct(
        private readonly StimulusHelper $stimulus,
        private readonly string $endpoint = '/_image',
    ) {
    }

    public function renderImage(Image $image, array $attributes = []): string
    {
        $imageData = $image->toArray();
        $controllerValues = ['src' => $imageData['src']];

        if (isset($imageData['alt'])) {
            $controllerValues['alt'] = $imageData['alt'];
        }
        if (isset($imageData['width'])) {
            $controllerValues['width'] = $imageData['width'];
        }
        if (isset($imageData['height'])) {
            $controllerValues['height'] = $imageData['height'];
        }
        if (isset($imageData['loading'])) {
            $controllerValues['loading'] = $imageData['loading'];
        }
        if (isset($imageData['transformation'])) {
            $controllerValues['transformation'] = (object) $imageData['transformation'];
        }

        $controllerValues['provider-options'] = (object) ['endpoint' => $this->endpoint];

        $stimulusAttributes = $this->stimulus->createStimulusAttributes();
        $stimulusAttributes->addController('@symfony/ux-image/local', $controllerValues);

        foreach ($attributes as $name => $value) {
            if (true === $value) {
                $stimulusAttributes->addAttribute($name, $name);
            } elseif (false !== $value) {
                $stimulusAttributes->addAttribute($name, $value);
            }
        }

        return \sprintf('<img %s />', $stimulusAttributes);
    }

    public function __toString(): string
    {
        return 'local';
    }
}
