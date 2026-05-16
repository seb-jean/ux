<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * @author Sébastien Jean <contact@seb-jean.fr>
 */
final class UXImageBundle extends AbstractBundle
{
    protected string $extensionAlias = 'ux_image';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('provider')
                    ->defaultValue('local://default')
                    ->info('The DSN of the image provider to use (e.g. "local://default" or "local://default?endpoint=/_image").')
                ->end()
                ->scalarNode('public_dir')
                    ->defaultNull()
                    ->info('Absolute path to the public directory. Defaults to "%kernel.project_dir%/public".')
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        if (ContainerBuilder::willBeAvailable('symfony/ux-twig-component', TwigComponentBundle::class, ['symfony/ux-image'])) {
            $container->import('../config/twig_component.php');
        }

        $publicDir = $config['public_dir'] ?? '%kernel.project_dir%/public';

        $container->services()
            ->get('ux_image.providers')
            ->arg(0, ['default' => $config['provider']]);

        $container->services()
            ->get('ux_image.controller.transform')
            ->arg(1, $publicDir);
    }

    public function loadRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../config/routes.php');
    }
}
