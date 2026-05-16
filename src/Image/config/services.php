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

use Symfony\UX\Image\Provider\Local\LocalProviderFactory;
use Symfony\UX\Image\Provider\Provider;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Twig\ImageExtension;
use Symfony\UX\Image\Twig\ImageRuntime;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('ux_image.providers', Providers::class)
            ->factory([service('ux_image.provider'), 'fromStrings'])
            ->args([
                abstract_arg('providers configuration'),
            ])

        ->set('ux_image.provider_factory.local', LocalProviderFactory::class)
            ->tag('ux_image.provider_factory')

        ->set('ux_image.provider', Provider::class)
            ->args([
                tagged_iterator('ux_image.provider_factory'),
            ])

        ->set('ux_image.twig_extension', ImageExtension::class)
            ->tag('twig.extension')

        ->set('ux_image.twig_runtime', ImageRuntime::class)
            ->args([
                service('ux_image.providers'),
            ])
            ->tag('twig.runtime')
            ->tag('ux.twig_component.twig_renderer', ['key' => 'ux:image'])
    ;
};
