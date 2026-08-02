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
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\UnsupportedSchemeException;
use Symfony\UX\Image\Provider\Local\LocalImageProvider;
use Symfony\UX\Image\Provider\Local\LocalImageProviderFactory;
use Symfony\UX\Image\Provider\Providers;
use Symfony\UX\Image\Tests\Fixtures\FakeProvider;
use Symfony\UX\Image\Tests\Fixtures\FakeProviderFactory;

final class ProvidersTest extends TestCase
{
    public function testItReturnsTheDefaultProviderWhenNoneIsRequested(): void
    {
        $providers = new Providers(
            ['local' => 'default://local', 'cdn' => 'fake://x'],
            [new LocalImageProviderFactory(), new FakeProviderFactory()],
            'cdn',
        );

        self::assertInstanceOf(FakeProvider::class, $providers->get());
    }

    public function testItReturnsAProviderByName(): void
    {
        $providers = new Providers(
            ['local' => 'default://local', 'cdn' => 'fake://x'],
            [new LocalImageProviderFactory(), new FakeProviderFactory()],
            'cdn',
        );

        self::assertInstanceOf(LocalImageProvider::class, $providers->get('local'));
    }

    public function testProvidersAreInstantiatedOnce(): void
    {
        $providers = new Providers(['cdn' => 'fake://x'], [new FakeProviderFactory()], 'cdn');

        self::assertSame($providers->get('cdn'), $providers->get('cdn'));
    }

    /**
     * The single-provider shorthand: "ux_image.provider" holds a DSN rather than
     * the name of a configured provider.
     */
    public function testTheDefaultCanBeAnInlineDsn(): void
    {
        $providers = new Providers([], [new FakeProviderFactory()], 'fake://x');

        self::assertInstanceOf(FakeProvider::class, $providers->get());
    }

    public function testAConfiguredNameWinsOverAnInlineDsn(): void
    {
        $providers = new Providers(
            ['default' => 'default://local'],
            [new LocalImageProviderFactory(), new FakeProviderFactory()],
            'default',
        );

        self::assertInstanceOf(LocalImageProvider::class, $providers->get());
    }

    public function testUnknownProviderNameThrows(): void
    {
        $providers = new Providers(['cdn' => 'fake://x'], [new FakeProviderFactory()], 'cdn');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image provider "nope" is not configured. Configured providers: "cdn".');

        $providers->get('nope');
    }

    public function testUnsupportedSchemeThrows(): void
    {
        $providers = new Providers(['cdn' => 'unknown://x'], [new FakeProviderFactory()], 'cdn');

        $this->expectException(UnsupportedSchemeException::class);

        $providers->get();
    }
}
