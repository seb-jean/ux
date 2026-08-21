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

use Symfony\UX\Image\Exception\UnsupportedSchemeException;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class Provider
{
    public function __construct(
        /** @param iterable<ProviderFactoryInterface> $factories */
        private readonly iterable $factories,
    ) {
    }

    public function fromStrings(#[\SensitiveParameter] array $dsns): Providers
    {
        $providers = [];
        foreach ($dsns as $name => $dsn) {
            $providers[$name] = $this->fromString($dsn);
        }

        return new Providers($providers);
    }

    public function fromString(#[\SensitiveParameter] string $dsn): ProviderInterface
    {
        return $this->fromDsnObject(new Dsn($dsn));
    }

    public function fromDsnObject(Dsn $dsn): ProviderInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }
}
