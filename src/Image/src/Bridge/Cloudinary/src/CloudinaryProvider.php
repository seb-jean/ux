<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Cloudinary;

use Symfony\UX\Image\Exception\UnsupportedTransformationException;
use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Transformation;

/**
 * Cloudinary encodes transformations in the URL PATH:
 *     https://res.cloudinary.com/<cloud>/image/upload/<transf>/<public_id>
 * Signed delivery uses a short signature segment:
 *     /image/upload/s--<sig>--/<transf>/<public_id>
 *
 * @author Symfony Community
 */
final class CloudinaryProvider implements ImageProviderInterface
{
    private const CROP = [
        'contain' => 'c_fit',
        'cover' => 'c_fill,g_auto',
        'fill' => 'c_scale',
    ];

    public function __construct(
        private readonly string $cloudName,
        #[\SensitiveParameter]
        private readonly ?string $apiSecret = null,
        private readonly bool $signUrls = false,
        private readonly bool $secure = true,
        private readonly string $deliveryType = 'upload', // 'upload' | 'fetch'
        private readonly string $host = 'res.cloudinary.com',
    ) {
    }

    public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
    {
        $transf = $this->buildTransformation($transformation);

        $publicId = 'fetch' === $this->deliveryType
            ? $source->url                  // remote fetch: the full origin URL
            : ltrim($source->src, '/');     // upload: the public id / path

        $encodedId = 'fetch' === $this->deliveryType ? rawurlencode($publicId) : $publicId;
        $tail = '' !== $transf ? $transf.'/'.$encodedId : $encodedId;

        if ($this->signUrls) {
            if (null === $this->apiSecret) {
                throw new UnsupportedTransformationException('Signed Cloudinary URLs require an API secret in the DSN (cloudinary://<key>:<secret>@<cloud>).');
            }
            $toSign = ('' !== $transf ? $transf.'/' : '').$publicId;
            $signature = substr(strtr(base64_encode(sha1($toSign.$this->apiSecret, true)), '+/', '-_'), 0, 8);
            $tail = 's--'.$signature.'--/'.$tail;
        }

        $scheme = $this->secure ? 'https' : 'http';
        $url = \sprintf('%s://%s/%s/image/%s/%s', $scheme, $this->host, $this->cloudName, $this->deliveryType, $tail);

        $width = $transformation->width;
        $height = $transformation->height ?? ($width && $source->dimensions ? $source->dimensions->heightForWidth($width) : null);

        return new GeneratedImage($url, $width, $height, $transformation->format);
    }

    public function capabilities(): ProviderCapabilities
    {
        // Arbitrary widths, all modern formats, plus f_auto content negotiation.
        return new ProviderCapabilities(
            formats: ['avif', 'webp', 'jpg', 'png', 'gif', 'auto'],
            widths: null,
            canResize: true,
        );
    }

    private function buildTransformation(Transformation $transformation): string
    {
        $parts = [self::CROP[$transformation->fit->value]];

        if (null !== $transformation->width) {
            $parts[] = 'w_'.$transformation->width;
        }
        if (null !== $transformation->height) {
            $parts[] = 'h_'.$transformation->height;
        }

        $parts[] = 'f_'.($transformation->format ?? 'auto');
        $parts[] = 'q_'.($transformation->quality ?? 'auto');

        // Raw Cloudinary transformation parameters, e.g. {e: 'grayscale'} => "e_grayscale".
        foreach ($transformation->modifiers as $name => $value) {
            $parts[] = $name.'_'.$value;
        }

        return implode(',', $parts);
    }
}
