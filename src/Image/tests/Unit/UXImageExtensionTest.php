<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\UX\Image\DependencyInjection\UXImageExtension;
use Symfony\UX\Image\Exception\InvalidArgumentException;

final class UXImageExtensionTest extends TestCase
{
    public function testWithoutAnyConfigurationTheBuiltInProviderIsUsed(): void
    {
        [$dsns, $default] = $this->load([]);

        self::assertSame(['default' => 'default://local'], $dsns);
        self::assertSame('default', $default);
    }

    public function testTheDefaultProviderCanBeNamed(): void
    {
        [$dsns, $default] = $this->load([[
            'provider' => 'cloudinary',
            'providers' => [
                'cloudinary' => 'cloudinary://my-cloud',
                'fastly' => 'fastly://cdn.example.com',
            ],
        ]]);

        self::assertSame('cloudinary', $default);
        self::assertArrayHasKey('fastly', $dsns);
    }

    public function testTheDefaultProviderCanBeADsn(): void
    {
        [$dsns, $default] = $this->load([[
            'provider' => 'cloudinary://my-cloud',
        ]]);

        self::assertSame('cloudinary://my-cloud', $default);
        self::assertSame([], $dsns);
    }

    public function testASingleConfiguredProviderIsTheDefaultOne(): void
    {
        [, $default] = $this->load([[
            'providers' => ['fastly' => 'fastly://cdn.example.com'],
        ]]);

        self::assertSame('fastly', $default);
    }

    public function testAProviderNamedDefaultWins(): void
    {
        [, $default] = $this->load([[
            'providers' => [
                'default' => 'fastly://cdn.example.com',
                'cloudinary' => 'cloudinary://my-cloud',
            ],
        ]]);

        self::assertSame('default', $default);
    }

    public function testSeveralProvidersWithoutADefaultThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Set "ux_image.provider" to the name of the provider to use by default.');

        $this->load([[
            'providers' => [
                'cloudinary' => 'cloudinary://my-cloud',
                'fastly' => 'fastly://cdn.example.com',
            ],
        ]]);
    }

    /**
     * Keys a preset does not define must not reach the component, otherwise an
     * empty value would override the prop it is supposed to leave alone.
     */
    public function testPresetsOnlyCarryTheKeysTheyDefine(): void
    {
        $container = $this->build([[
            'presets' => [
                'avatar' => ['width' => 96, 'height' => 96, 'fit' => 'cover'],
            ],
        ]]);

        $presets = $container->getDefinition('ux_image.twig_component.img')->getArgument(1);

        self::assertSame(['width' => 96, 'height' => 96, 'fit' => 'cover'], $presets['avatar']);
    }

    public function testScreensReachTheSizesResolver(): void
    {
        $container = $this->build([['screens' => ['small' => 480, 'big' => 1400]]]);

        self::assertSame(
            ['small' => 480, 'big' => 1400],
            $container->getDefinition('ux_image.sizes_resolver')->getArgument(0),
        );
    }

    public function testSizesDefaultsToNullSoFixedSizeImagesUseDensities(): void
    {
        $container = $this->build([]);

        $defaults = $container->getDefinition('ux_image.renderer')->getArgument(3);

        self::assertNull($defaults['sizes']);
        self::assertSame([1, 2], $defaults['densities']);
    }

    /**
     * @return array{0: array<string, string>, 1: string} the provider DSNs and the default provider
     */
    private function load(array $configs): array
    {
        $definition = $this->build($configs)->getDefinition('ux_image.providers');

        return [$definition->getArgument(0), $definition->getArgument(2)];
    }

    private function build(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');

        new UXImageExtension()->load($configs, $container);

        return $container;
    }
}
