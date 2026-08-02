<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\UXImageBundle;

/**
 * @author Symfony Community
 *
 * @internal
 */
final class UXImageExtension extends Extension
{
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $dsns = $config['providers'];
        // Resolve first: it may register the built-in provider into $dsns.
        $default = $this->resolveDefaultProvider($config['provider'], $dsns);

        $container->getDefinition('ux_image.providers')
            ->setArgument(0, $dsns)
            ->setArgument(2, $default);

        $container->getDefinition('ux_image.source_resolver')
            ->setArgument(3, $container->getParameter('kernel.project_dir').'/public');

        $container->getDefinition('ux_image.sizes_resolver')
            ->setArgument(0, $config['screens']);

        $container->getDefinition('ux_image.renderer')
            ->setArgument(3, $config['defaults'])
            ->setArgument(4, [
                'loading' => $config['loading'],
                'decoding' => $config['decoding'],
            ]);

        $presets = $this->normalizePresets($config['presets']);
        $container->getDefinition('ux_image.twig_component.img')->setArgument(1, $presets);
        $container->getDefinition('ux_image.twig_component.source')->setArgument(1, $presets);

        // Register the provider factory of every installed bridge.
        foreach (UXImageBundle::$bridges as $name => $bridge) {
            if (ContainerBuilder::willBeAvailable('symfony/ux-'.$name.'-image', $bridge['provider_factory'], ['symfony/ux-image'])) {
                $container->register('ux_image.provider_factory.'.$name, $bridge['provider_factory'])
                    ->addTag('ux_image.provider_factory');
            }
        }
    }

    /**
     * Drops the keys a preset did not define, so that merging a preset into the
     * component props never overrides a prop with an empty value.
     *
     * @param array<string, array<string, mixed>> $presets
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizePresets(array $presets): array
    {
        foreach ($presets as $name => $preset) {
            $presets[$name] = array_filter($preset, static fn (mixed $value): bool => null !== $value && [] !== $value);
        }

        return $presets;
    }

    /**
     * Resolves which provider is used when a component does not name one.
     *
     * The returned value is either a provider name or a DSN: it is only
     * interpreted at runtime, so that an unresolved "%env()%" placeholder is
     * never mistaken for a provider name.
     *
     * @param array<string, string> $dsns
     */
    private function resolveDefaultProvider(?string $provider, array &$dsns): string
    {
        if (null !== $provider) {
            return $provider;
        }

        if (isset($dsns['default'])) {
            return 'default';
        }

        // A single configured provider is unambiguously the default one.
        if (1 === \count($dsns)) {
            return array_key_first($dsns);
        }

        if ($dsns) {
            throw new InvalidArgumentException(\sprintf('Several image providers are configured ("%s") but none of them is the default one. Set "ux_image.provider" to the name of the provider to use by default.', implode('", "', array_keys($dsns))));
        }

        // No provider configured at all: fall back to the built-in one.
        $dsns['default'] = 'default://local';

        return 'default';
    }
}
