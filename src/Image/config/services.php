<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\UX\Image\Asset\AssetSourceResolver;
use Symfony\UX\Image\Asset\SourceResolverInterface;
use Symfony\UX\Image\Dimensions\CachedDimensionsReader;
use Symfony\UX\Image\Dimensions\DimensionsReaderInterface;
use Symfony\UX\Image\Dimensions\NativeDimensionsReader;
use Symfony\UX\Image\Provider\Local\LocalImageProviderFactory;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Responsive\SizesResolver;
use Symfony\UX\Image\Twig\ImageComponent;
use Symfony\UX\Image\Twig\ImageRenderer;
use Symfony\UX\Image\Twig\PictureComponent;
use Symfony\UX\Image\Twig\SourceComponent;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('ux_image.provider_factory.local', LocalImageProviderFactory::class)
            ->tag('ux_image.provider_factory')

        ->set('ux_image.providers', Providers::class)
            ->args([
                abstract_arg('provider DSNs'),
                tagged_iterator('ux_image.provider_factory'),
                abstract_arg('default provider name'),
            ])

        ->set('ux_image.cache')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('ux_image.dimensions_reader', NativeDimensionsReader::class)
        ->alias(DimensionsReaderInterface::class, 'ux_image.dimensions_reader')

        ->set('ux_image.dimensions_reader.cached', CachedDimensionsReader::class)
            ->decorate('ux_image.dimensions_reader')
            ->args([
                service('.inner'),
                service('ux_image.cache'),
            ])

        ->set('ux_image.sizes_resolver', SizesResolver::class)
            ->args([
                abstract_arg('screens'),
            ])

        ->set('ux_image.source_resolver', AssetSourceResolver::class)
            ->args([
                service('assets.packages')->nullOnInvalid(),
                service('ux_image.dimensions_reader'),
                service('asset_mapper')->nullOnInvalid(),
                abstract_arg('public directory'),
            ])
        ->alias(SourceResolverInterface::class, 'ux_image.source_resolver')

        ->set('ux_image.renderer', ImageRenderer::class)
            ->args([
                service('ux_image.source_resolver'),
                service('ux_image.providers'),
                service('ux_image.sizes_resolver'),
                abstract_arg('defaults'),
                abstract_arg('config'),
            ])

        ->set('ux_image.twig_component.img', ImageComponent::class)
            ->args([
                service('ux_image.renderer'),
                abstract_arg('presets'),
            ])
            ->tag('twig.component', [
                'key' => 'ux:img',
                'template' => '@UXImage/components/ux_img.html.twig',
                'expose_public_props' => true,
            ])

        ->set('ux_image.twig_component.source', SourceComponent::class)
            ->args([
                service('ux_image.renderer'),
                abstract_arg('presets'),
            ])
            ->tag('twig.component', [
                'key' => 'ux:source',
                'template' => '@UXImage/components/ux_source.html.twig',
                'expose_public_props' => true,
            ])

        ->set('ux_image.twig_component.picture', PictureComponent::class)
            ->tag('twig.component', [
                'key' => 'ux:picture',
                'template' => '@UXImage/components/ux_picture.html.twig',
                'expose_public_props' => true,
            ])
    ;
};
