<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Url\ImageUriSigner;

class ImageUriSignerTest extends TestCase
{
    public function testSignatureIsUrlSafe(): void
    {
        $signer = new ImageUriSigner('a-secret');

        for ($i = 0; $i < 50; ++$i) {
            $hash = $signer->sign('images/photo-'.$i.'.jpg', ['width' => $i]);

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{24}$/', $hash);
            $this->assertSame($hash, rawurlencode($hash));
        }
    }

    public function testSignatureIsStable(): void
    {
        $signer = new ImageUriSigner('a-secret');

        $this->assertSame(
            $signer->sign('images/photo.jpg', ['width' => 400, 'format' => 'webp']),
            $signer->sign('images/photo.jpg', ['width' => 400, 'format' => 'webp']),
        );
    }

    public function testParameterOrderDoesNotMatter(): void
    {
        $signer = new ImageUriSigner('a-secret');

        $this->assertTrue($signer->isValid(
            'images/photo.jpg',
            ['format' => 'webp', 'width' => 400],
            $signer->sign('images/photo.jpg', ['width' => 400, 'format' => 'webp']),
        ));
    }

    public function testAnotherSecretProducesAnotherSignature(): void
    {
        $query = ['width' => 400];

        $this->assertFalse(new ImageUriSigner('other-secret')->isValid(
            'images/photo.jpg',
            $query,
            new ImageUriSigner('a-secret')->sign('images/photo.jpg', $query),
        ));
    }

    public function testAnEmptyHashIsRejected(): void
    {
        $this->assertFalse(new ImageUriSigner('a-secret')->isValid('images/photo.jpg', [], ''));
    }

    public function testATamperedParameterIsRejected(): void
    {
        $signer = new ImageUriSigner('a-secret');
        $hash = $signer->sign('images/photo.jpg', ['width' => 400]);

        $this->assertFalse($signer->isValid('images/photo.jpg', ['width' => 401], $hash));
        $this->assertFalse($signer->isValid('images/photo.jpg', [], $hash));
    }
}
