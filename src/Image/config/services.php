<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\UX\Image\Cache\ImageCache;
use Symfony\UX\Image\Controller\ImageController;
use Symfony\UX\Image\ImageTransformer;
use Symfony\UX\Image\Processor\GdImageProcessor;
use Symfony\UX\Image\Processor\ImagickImageProcessor;
use Symfony\UX\Image\Processor\ProcessorRegistry;
use Symfony\UX\Image\Source\ImageSourceResolver;
use Symfony\UX\Image\Transformation\TransformationFactory;
use Symfony\UX\Image\Twig\ImageExtension;
use Symfony\UX\Image\Twig\ImageRuntime;
use Symfony\UX\Image\Url\ImageUriSigner;
use Symfony\UX\Image\Url\ImageUrlGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('.ux_image.source_resolver', ImageSourceResolver::class)
            ->args([
                abstract_arg('public directory'),
            ])

        ->set('.ux_image.transformation_factory', TransformationFactory::class)
            ->args([
                abstract_arg('default quality'),
                abstract_arg('max width'),
                abstract_arg('max height'),
                abstract_arg('auto formats'),
                abstract_arg('allowed formats'),
                abstract_arg('presets'),
            ])

        ->set('.ux_image.cache', ImageCache::class)
            ->args([
                abstract_arg('cache directory'),
            ])

        ->set('.ux_image.processor.imagick', ImagickImageProcessor::class)

        ->set('.ux_image.processor.gd', GdImageProcessor::class)

        ->set('.ux_image.processor_registry', ProcessorRegistry::class)
            ->args([
                [
                    'imagick' => service('.ux_image.processor.imagick'),
                    'gd' => service('.ux_image.processor.gd'),
                ],
                abstract_arg('driver'),
            ])

        ->set('.ux_image.transformer', ImageTransformer::class)
            ->args([
                service('.ux_image.processor_registry'),
                service('.ux_image.cache'),
            ])
        ->alias(ImageTransformer::class, '.ux_image.transformer')

        ->set('.ux_image.uri_signer', ImageUriSigner::class)
            ->args([
                '%kernel.secret%',
            ])

        ->set('.ux_image.url_generator', ImageUrlGenerator::class)
            ->args([
                service('router'),
                service('.ux_image.uri_signer'),
            ])
        ->alias(ImageUrlGenerator::class, '.ux_image.url_generator')

        ->set('ux_image.controller', ImageController::class)
            ->public()
            ->args([
                service('.ux_image.source_resolver'),
                service('.ux_image.transformation_factory'),
                service('.ux_image.transformer'),
                service('.ux_image.uri_signer'),
            ])
            ->tag('controller.service_arguments')

        ->set('.ux_image.twig_runtime', ImageRuntime::class)
            ->args([
                service('.ux_image.source_resolver'),
                service('.ux_image.transformation_factory'),
                service('.ux_image.transformer'),
                service('.ux_image.url_generator'),
            ])
            ->tag('twig.runtime')

        ->set('.ux_image.twig_extension', ImageExtension::class)
            ->tag('twig.extension')
    ;
};
