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
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Image\UXImageBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $id;

    /**
     * @param array<string, mixed> $imageConfig
     */
    public function __construct(string $environment = 'test', bool $debug = true, private readonly array $imageConfig = [])
    {
        $this->id = bin2hex(random_bytes(6));

        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new UXImageBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'a-test-secret',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'property_access' => ['enabled' => true],
        ]);

        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components',
        ]);

        $container->extension('ux_image', $this->imageConfig + [
            'public_dir' => __DIR__.'/images',
            'presets' => [
                'thumbnail' => ['width' => 20, 'height' => 20, 'fit' => 'crop'],
            ],
        ]);

        // the bundle keeps its services private, tests need a handle on them
        $container->services()
            ->alias('test.ux_image.source_resolver', '.ux_image.source_resolver')->public()
            ->alias('test.ux_image.transformation_factory', '.ux_image.transformation_factory')->public()
            ->alias('test.ux_image.uri_signer', '.ux_image.uri_signer')->public()
            ->alias('test.ux_image.url_generator', '.ux_image.url_generator')->public()
            ->alias('test.twig', 'twig')->public()
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../config/routes.php');
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux-image/'.$this->id.'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux-image/'.$this->id.'/log';
    }

    public function cleanup(): void
    {
        new Filesystem()->remove(sys_get_temp_dir().'/ux-image/'.$this->id);
    }
}
