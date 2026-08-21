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

use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Image;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class Providers implements ProviderInterface
{
    /** @param array<string, ProviderInterface> $providers */
    public function __construct(
        private readonly array $providers,
    ) {
    }

    public function renderImage(Image $image, array $attributes = []): string
    {
        return $this->get($image->getProvider())->renderImage($image, $attributes);
    }

    public function get(?string $name): ProviderInterface
    {
        if (null === $name) {
            return $this->getDefault();
        }

        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException(\sprintf('The image provider "%s" is not configured. Available providers are: %s.', $name, implode(', ', array_keys($this->providers))));
        }

        return $this->providers[$name];
    }

    public function getDefault(): ProviderInterface
    {
        if (!isset($this->providers['default'])) {
            throw new InvalidArgumentException('No default image provider is configured.');
        }

        return $this->providers['default'];
    }

    public function __toString(): string
    {
        return implode(', ', array_map(static fn (ProviderInterface $p) => (string) $p, $this->providers));
    }
}
