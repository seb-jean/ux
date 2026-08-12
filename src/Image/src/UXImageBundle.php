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
use Symfony\UX\Image\Transformation\Format;

final class UXImageBundle extends AbstractBundle
{
    protected string $extensionAlias = 'ux_image';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('driver')
                    ->info("The image processing driver.\n\"auto\" picks Imagick when available, GD otherwise.")
                    ->values(['auto', 'gd', 'imagick'])
                    ->defaultValue('auto')
                ->end()
                ->scalarNode('public_dir')
                    ->info('The directory source images are resolved from.')
                    ->defaultValue('%kernel.project_dir%/public')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('cache_dir')
                    ->info('The directory transformed images are stored in.')
                    ->defaultValue('%kernel.cache_dir%/ux_image')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('route_prefix')
                    ->info('The path the image route is mounted on.')
                    ->defaultValue('/_image')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('quality')
                    ->info('The default compression level for lossy formats.')
                    ->min(0)->max(100)
                    ->defaultValue(85)
                ->end()
                ->integerNode('max_width')
                    ->info('The largest width a transformation may request.')
                    ->min(1)
                    ->defaultValue(4000)
                ->end()
                ->integerNode('max_height')
                    ->info('The largest height a transformation may request.')
                    ->min(1)
                    ->defaultValue(4000)
                ->end()
                ->arrayNode('auto')
                    ->info('Formats to negotiate automatically, based on the "Accept" request header.')
                    ->example(['webp'])
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue([])
                    ->enumPrototype()
                        ->values(['avif', 'webp'])
                    ->end()
                ->end()
                ->arrayNode('allowed_formats')
                    ->info('The output formats a transformation may request.')
                    ->beforeNormalization()->castToArray()->end()
                    ->defaultValue(array_map(static fn (Format $format) => $format->value, Format::cases()))
                    ->enumPrototype()
                        ->values(array_map(static fn (Format $format) => $format->value, Format::cases()))
                    ->end()
                ->end()
                ->arrayNode('presets')
                    ->info('Named sets of transformations, usable as {{ ux_image(src, \'preset_name\') }}.')
                    ->example(['thumbnail' => ['width' => 300, 'height' => 300, 'fit' => 'crop', 'format' => 'webp']])
                    ->defaultValue([])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->variablePrototype()
                        ->validate()
                            ->ifTrue(static fn ($v) => !\is_array($v))
                            ->thenInvalid('A preset must be a map of transformations, got %s.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        if (isset($builder->getParameter('kernel.bundles')['TwigComponentBundle'])) {
            $container->import('../config/twig_component.php');
        }

        $builder->getDefinition('.ux_image.source_resolver')
            ->setArgument(0, $config['public_dir'])
        ;

        $builder->getDefinition('.ux_image.cache')
            ->setArgument(0, $config['cache_dir'])
        ;

        $builder->getDefinition('.ux_image.processor_registry')
            ->setArgument(1, $config['driver'])
        ;

        $builder->getDefinition('.ux_image.transformation_factory')
            ->setArgument(0, $config['quality'])
            ->setArgument(1, $config['max_width'])
            ->setArgument(2, $config['max_height'])
            ->setArgument(3, $config['auto'])
            ->setArgument(4, $config['allowed_formats'])
            ->setArgument(5, $config['presets'])
        ;

        $builder->setParameter('ux_image.route_prefix', $config['route_prefix']);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
