<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\UX\Image\Bridge as ImageBridge;
use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;

/**
 * @author Symfony Community
 */
final class UXImageBundle extends Bundle
{
    /**
     * @var array<string, array{ provider_factory: class-string<ImageProviderFactoryInterface> }>
     *
     * @internal
     */
    public static array $bridges = [
        'cloudinary' => ['provider_factory' => ImageBridge\Cloudinary\CloudinaryProviderFactory::class],
        'fastly' => ['provider_factory' => ImageBridge\Fastly\FastlyProviderFactory::class],
    ];

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
