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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Symfony Community
 *
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ux_image');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('provider')
                    ->info("The provider to use by default: either the name of one of the\n\"providers\" below, or a provider DSN when a single one is needed.")
                    ->example('cloudinary')
                    ->defaultNull()
                ->end()
                ->arrayNode('providers')
                    ->info('The available providers, as name => DSN.')
                    ->example(['cloudinary' => 'cloudinary://my-cloud', 'fastly' => 'fastly://cdn.example.com'])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('widths')
                            ->info('Candidate widths used to build the srcset.')
                            ->integerPrototype()->end()
                            ->defaultValue([320, 640, 768, 1024, 1366, 1920])
                        ->end()
                        ->arrayNode('formats')
                            ->info('Modern formats emitted as <picture> sources.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['avif', 'webp'])
                        ->end()
                        ->arrayNode('densities')
                            ->info("Pixel densities used for fixed-size images (width given, no sizes).\nRenders a \"1x, 2x\" srcset instead of a width-based one.\nFractional values such as 1.5 are allowed by the spec.")
                            ->floatPrototype()->end()
                            ->defaultValue([1, 2])
                        ->end()
                        ->scalarNode('sizes')
                            ->info("The default \"sizes\" attribute. Keep it null so that images given an\nexplicit width fall back to the density-based srcset.")
                            ->example('100vw md:50vw')
                            ->defaultNull()
                        ->end()
                        ->integerNode('quality')->defaultNull()->end()
                        ->enumNode('fit')->values(['contain', 'cover', 'fill'])->defaultValue('contain')->end()
                        ->arrayNode('modifiers')
                            ->info('Provider-specific options passed through to every image.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('screens')
                    ->info('Named breakpoints usable in the "sizes" prop, e.g. "md:50vw".')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->integerPrototype()->end()
                    ->defaultValue(['xs' => 320, 'sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, 'xxl' => 1536])
                ->end()
                ->arrayNode('presets')
                    ->info('Named sets of props, applied through the "preset" prop.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('width')->end()
                            ->integerNode('height')->end()
                            ->booleanNode('dimensions')->end()
                            ->scalarNode('sizes')->end()
                            ->arrayNode('widths')->integerPrototype()->end()->end()
                            ->arrayNode('formats')->scalarPrototype()->end()->end()
                            ->arrayNode('densities')->floatPrototype()->end()->end()
                            ->booleanNode('priority')->end()
                            ->enumNode('loading')->values(['lazy', 'eager'])->end()
                            ->enumNode('decoding')->values(['sync', 'async', 'auto'])->end()
                            ->enumNode('fetchpriority')->values(['high', 'low', 'auto'])->end()
                            ->enumNode('fit')->values(['contain', 'cover', 'fill'])->end()
                            ->integerNode('quality')->end()
                            ->scalarNode('provider')->end()
                            ->arrayNode('modifiers')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->enumNode('loading')->values(['lazy', 'eager'])->defaultValue('lazy')->end()
                ->enumNode('decoding')->values(['async', 'sync', 'auto'])->defaultValue('async')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
