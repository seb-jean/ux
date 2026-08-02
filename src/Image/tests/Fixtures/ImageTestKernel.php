<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\Image\UXImageBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * @internal
 */
final class ImageTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new TwigComponentBundle(),
            new UXImageBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => '$ecret',
                'test' => true,
                'http_method_override' => false,
            ]);
            $container->loadFromExtension('twig', [
                'default_path' => __DIR__.'/templates',
                'strict_variables' => true,
            ]);
            $container->loadFromExtension('twig_component', [
                'defaults' => [],
                'anonymous_template_directory' => 'components',
            ]);
            $container->loadFromExtension('ux_image', [
                // the default provider is designated by name, among several
                'provider' => 'local',
                'providers' => [
                    'local' => 'default://local',
                    'cdn' => 'fake://x',
                ],
                'presets' => [
                    'avatar' => ['width' => 96, 'height' => 96, 'fit' => 'cover', 'provider' => 'cdn'],
                ],
            ]);

            // Replace the filesystem-based resolver with a deterministic one.
            $container->setDefinition('ux_image.source_resolver', new Definition(StaticSourceResolver::class));

            // Register a fake resizing provider factory so "cdn" resolves.
            $container->register('ux_image.provider_factory.fake', FakeProviderFactory::class)
                ->addTag('ux_image.provider_factory');
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_image/cache/'.spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_image/log';
    }
}
