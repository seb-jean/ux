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

use Symfony\UX\Image\Dsn;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\UnsupportedSchemeException;

/**
 * Lazily builds and caches named providers from their DSNs, using the tagged
 * provider factories. Mirrors the UX Map "Renderers" service.
 *
 * @author Symfony Community
 */
final class Providers
{
    /** @var array<string, ImageProviderInterface> */
    private array $providers = [];

    /**
     * @param array<string, string>                   $dsns      map of provider name => DSN
     * @param iterable<ImageProviderFactoryInterface> $factories
     */
    public function __construct(
        private readonly array $dsns,
        private readonly iterable $factories,
        private readonly string $default,
    ) {
    }

    public function get(?string $name = null): ImageProviderInterface
    {
        $name ??= $this->default;

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        // Resolution happens at runtime, so that "%env(UX_IMAGE_DSN)%" is already
        // resolved: a configured name wins, otherwise the value is read as an
        // inline DSN (the single-provider shorthand).
        if (null === $dsn = $this->dsns[$name] ?? null) {
            if (!str_contains($name, '://')) {
                throw new InvalidArgumentException(\sprintf('Image provider "%s" is not configured. Configured providers: "%s". Set "ux_image.provider" to one of them, or to a provider DSN.', $name, implode('", "', array_keys($this->dsns))));
            }

            $dsn = $name;
        }

        $dsn = new Dsn($dsn);

        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $this->providers[$name] = $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException(\sprintf('No image provider supports the scheme "%s" (provider "%s"). Did you forget to install the matching bridge?', $dsn->getScheme(), $name));
    }
}
