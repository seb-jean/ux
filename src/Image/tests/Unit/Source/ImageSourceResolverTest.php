<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\Image\Exception\SourceNotFoundException;
use Symfony\UX\Image\Source\ImageSourceResolver;
use Symfony\UX\Image\Transformation\Format;

class ImageSourceResolverTest extends TestCase
{
    private string $root;
    private string $publicDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ux-image-source/'.bin2hex(random_bytes(6));
        $this->publicDir = $this->root.'/public';

        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->publicDir.'/images/nested', $this->root.'/private']);
        $filesystem->copy(__DIR__.'/../../Fixtures/images/photo.jpg', $this->publicDir.'/images/photo.jpg');
        $filesystem->copy(__DIR__.'/../../Fixtures/images/logo.png', $this->publicDir.'/images/nested/deep.png');
        $filesystem->copy(__DIR__.'/../../Fixtures/images/photo.jpg', $this->root.'/private/secret.jpg');
        $filesystem->dumpFile($this->publicDir.'/images/notes.txt', 'not an image');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    private function createResolver(): ImageSourceResolver
    {
        return new ImageSourceResolver($this->publicDir);
    }

    public function testResolve(): void
    {
        $source = $this->createResolver()->resolve('/images/photo.jpg');

        $this->assertSame(realpath($this->publicDir.'/images/photo.jpg'), $source->path);
        $this->assertSame('images/photo.jpg', $source->relativePath);
        $this->assertSame(Format::Jpeg, $source->format);
        $this->assertSame(filemtime($this->publicDir.'/images/photo.jpg'), $source->modifiedAt);
        $this->assertSame([80, 60], [$source->sizeOrFail()->width, $source->sizeOrFail()->height]);
    }

    public function testResolveWithoutALeadingSlash(): void
    {
        $this->assertSame('images/photo.jpg', $this->createResolver()->resolve('images/photo.jpg')->relativePath);
    }

    public function testResolveANestedPath(): void
    {
        $this->assertSame('images/nested/deep.png', $this->createResolver()->resolve('/images/nested/deep.png')->relativePath);
    }

    public function testTheQueryStringAndFragmentAreIgnored(): void
    {
        $resolver = $this->createResolver();

        $this->assertSame('images/photo.jpg', $resolver->resolve('/images/photo.jpg?v=123')->relativePath);
        $this->assertSame('images/photo.jpg', $resolver->resolve('/images/photo.jpg#anchor')->relativePath);
    }

    public function testResolvedSourcesAreMemoized(): void
    {
        $resolver = $this->createResolver();

        $this->assertSame($resolver->resolve('/images/photo.jpg'), $resolver->resolve('/images/photo.jpg'));
    }

    #[DataProvider('provideRejectedPaths')]
    public function testRejectedPath(string $path, string $expectedReason): void
    {
        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage($expectedReason);

        $this->createResolver()->resolve($path);
    }

    public static function provideRejectedPaths(): iterable
    {
        yield 'empty' => ['', 'it is empty'];
        yield 'only a slash' => ['/', 'it is empty'];
        yield 'blank' => ['   ', 'it is empty'];
        yield 'missing file' => ['/images/nope.jpg', 'it does not exist'];
        yield 'not an image extension' => ['/images/notes.txt', '"txt" is not a supported image extension'];
        yield 'no extension' => ['/images/photo', '"" is not a supported image extension'];
        yield 'a directory' => ['/images', '"" is not a supported image extension'];
        yield 'traversal' => ['/../private/secret.jpg', 'it is outside the public directory'];
        yield 'nested traversal' => ['/images/../../private/secret.jpg', 'it is outside the public directory'];
        yield 'traversal with a leading dot' => ['../private/secret.jpg', 'it is outside the public directory'];
        yield 'traversal to a missing file' => ['/../private/nope.jpg', 'it does not exist'];
        yield 'absolute host path' => ['//evil.com/images/photo.jpg', 'only paths inside the public directory'];
        yield 'http url' => ['http://evil.com/photo.jpg', 'only paths inside the public directory'];
        yield 'https url' => ['https://evil.com/photo.jpg', 'only paths inside the public directory'];
        yield 'data url' => ['data://text/plain;base64,SSBsb3ZlIFBIUAo=', 'only paths inside the public directory'];
        yield 'php wrapper' => ['php://filter/read=convert.base64-encode/resource=photo.jpg', 'only paths inside the public directory'];
        yield 'null byte' => ["/images/photo.jpg\0.txt", 'it contains a null byte'];
    }

    public function testASymlinkOutsideThePublicDirectoryIsRejected(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Symlinks require elevated privileges on Windows.');
        }

        symlink($this->root.'/private/secret.jpg', $this->publicDir.'/images/leak.jpg');

        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('it is outside the public directory');

        $this->createResolver()->resolve('/images/leak.jpg');
    }

    public function testASymlinkInsideThePublicDirectoryIsAllowed(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Symlinks require elevated privileges on Windows.');
        }

        symlink($this->publicDir.'/images/photo.jpg', $this->publicDir.'/images/alias.jpg');

        $this->assertSame('images/photo.jpg', $this->createResolver()->resolve('/images/alias.jpg')->relativePath);
    }

    public function testAMissingPublicDirectoryIsReported(): void
    {
        $resolver = new ImageSourceResolver($this->root.'/does-not-exist');

        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('the public directory');

        $resolver->resolve('/images/photo.jpg');
    }

    public function testSizeIsNullForAFileThatIsNotAnImage(): void
    {
        new Filesystem()->dumpFile($this->publicDir.'/images/broken.png', 'this is not a PNG');

        $source = $this->createResolver()->resolve('/images/broken.png');

        $this->assertNull($source->size());

        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('its dimensions cannot be read');

        $source->sizeOrFail();
    }
}
