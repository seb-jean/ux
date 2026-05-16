<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\Image\Controller\ImageTransformController;
use Symfony\UX\Image\Transformer\ImageTransformer;

class ImageTransformControllerTest extends TestCase
{
    private string $publicDir;
    private string $fixtureImage;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/ux_image_public_'.uniqid();
        mkdir($this->publicDir.'/images', 0755, true);

        $image = imagecreatetruecolor(200, 100);
        imagefilledrectangle($image, 0, 0, 199, 99, imagecolorallocate($image, 100, 149, 237));
        $this->fixtureImage = $this->publicDir.'/images/photo.jpg';
        imagejpeg($image, $this->fixtureImage, 90);
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixtureImage);
        @rmdir($this->publicDir.'/images');
        @rmdir($this->publicDir);
    }

    private function controller(): ImageTransformController
    {
        return new ImageTransformController(new ImageTransformer(), $this->publicDir);
    }

    public function testMissingSrcReturns400(): void
    {
        $response = ($this->controller())(Request::create('/_image'));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUnknownImageReturns404(): void
    {
        $response = ($this->controller())(Request::create('/_image?src=/images/missing.jpg'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPathTraversalReturns400(): void
    {
        $response = ($this->controller())(Request::create('/_image?src=/../etc/passwd'));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testValidImageReturns200(): void
    {
        $response = ($this->controller())(Request::create('/_image?src=/images/photo.jpg'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function testTransformationIsApplied(): void
    {
        $response = ($this->controller())(Request::create('/_image?src=/images/photo.jpg&w=50&h=50&format=webp'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));

        $image = imagecreatefromstring($response->getContent());
        $this->assertSame(50, imagesx($image));
        imagedestroy($image);
    }

    public function testCacheControlHeaderIsSet(): void
    {
        $response = ($this->controller())(Request::create('/_image?src=/images/photo.jpg'));

        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }
}
