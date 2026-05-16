<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Source;

class SourceTest extends TestCase
{
    public function testCreateWithSrcsetOnly(): void
    {
        $source = Source::create('/images/photo.webp');

        $this->assertSame(['srcset' => '/images/photo.webp'], $source->toArray());
    }

    public function testWithType(): void
    {
        $source = Source::create('/images/photo.avif')->type('image/avif');

        $this->assertSame([
            'srcset' => '/images/photo.avif',
            'type' => 'image/avif',
        ], $source->toArray());
    }

    public function testWithMedia(): void
    {
        $source = Source::create('/images/photo.webp')->media('(min-width: 800px)');

        $this->assertSame([
            'srcset' => '/images/photo.webp',
            'media' => '(min-width: 800px)',
        ], $source->toArray());
    }

    public function testWithSizes(): void
    {
        $source = Source::create('/images/photo.webp')->sizes('(max-width: 600px) 100vw, 50vw');

        $this->assertSame([
            'srcset' => '/images/photo.webp',
            'sizes' => '(max-width: 600px) 100vw, 50vw',
        ], $source->toArray());
    }

    public function testWithAllAttributes(): void
    {
        $source = Source::create('/images/photo.webp')
            ->type('image/webp')
            ->media('(min-width: 800px)')
            ->sizes('80vw');

        $this->assertSame([
            'srcset' => '/images/photo.webp',
            'type' => 'image/webp',
            'media' => '(min-width: 800px)',
            'sizes' => '80vw',
        ], $source->toArray());
    }

    public function testIsImmutable(): void
    {
        $original = Source::create('/images/photo.webp');
        $withType = $original->type('image/webp');
        $withMedia = $original->media('(min-width: 800px)');
        $withSizes = $original->sizes('80vw');

        // Original is unchanged
        $this->assertSame(['srcset' => '/images/photo.webp'], $original->toArray());

        // Each derived instance has only the added attribute
        $this->assertArrayHasKey('type', $withType->toArray());
        $this->assertArrayNotHasKey('media', $withType->toArray());

        $this->assertArrayHasKey('media', $withMedia->toArray());
        $this->assertArrayNotHasKey('type', $withMedia->toArray());

        $this->assertArrayHasKey('sizes', $withSizes->toArray());
        $this->assertArrayNotHasKey('type', $withSizes->toArray());
    }

    public function testChainingIsImmutable(): void
    {
        $base = Source::create('/images/photo.avif');
        $typed = $base->type('image/avif');
        $full = $typed->media('(min-width: 800px)')->sizes('80vw');

        $this->assertNotSame($base, $typed);
        $this->assertNotSame($typed, $full);

        // base has only srcset
        $this->assertSame(['srcset' => '/images/photo.avif'], $base->toArray());

        // typed has srcset + type
        $this->assertSame([
            'srcset' => '/images/photo.avif',
            'type' => 'image/avif',
        ], $typed->toArray());

        // full has all four
        $this->assertSame([
            'srcset' => '/images/photo.avif',
            'type' => 'image/avif',
            'media' => '(min-width: 800px)',
            'sizes' => '80vw',
        ], $full->toArray());
    }

    public function testToArrayOmitsNullValues(): void
    {
        $source = Source::create('/images/photo.webp');
        $arr = $source->toArray();

        $this->assertArrayNotHasKey('type', $arr);
        $this->assertArrayNotHasKey('media', $arr);
        $this->assertArrayNotHasKey('sizes', $arr);
    }

    public function testConstructorDirectly(): void
    {
        $source = new Source('/images/photo.webp', 'image/webp', '(min-width: 600px)', '50vw');

        $this->assertSame([
            'srcset' => '/images/photo.webp',
            'type' => 'image/webp',
            'media' => '(min-width: 600px)',
            'sizes' => '50vw',
        ], $source->toArray());
    }
}
