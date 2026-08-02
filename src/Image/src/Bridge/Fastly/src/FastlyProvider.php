<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Bridge\Fastly;

use Symfony\UX\Image\GeneratedImage;
use Symfony\UX\Image\ImageSource;
use Symfony\UX\Image\Provider\ImageProviderInterface;
use Symfony\UX\Image\Provider\ProviderCapabilities;
use Symfony\UX\Image\Transformation;

/**
 * Fastly Image Optimizer encodes transformations as QUERY PARAMS on the origin
 * URL:
 *     https://cdn.example.com/images/hero.jpg?width=800&fit=cover&format=webp
 * Optional URL protection is a `sig=` HMAC-SHA256 param over "path?sortedquery".
 *
 * @author Symfony Community
 */
final class FastlyProvider implements ImageProviderInterface
{
    private const FIT = [
        'contain' => 'bounds',
        'cover' => 'cover',
        'fill' => 'cover', // Fastly IO has no distort mode; cover is the closest
    ];

    public function __construct(
        private readonly string $host = 'default', // 'default' => use the resolved asset URL as-is
        #[\SensitiveParameter]
        private readonly ?string $signKey = null,
        private readonly ?string $auto = 'webp',
        private readonly bool $secure = true,
    ) {
    }

    public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
    {
        [$base, $params] = $this->splitUrl($source->url);

        if (null !== $transformation->width) {
            $params['width'] = $transformation->width;
        }
        if (null !== $transformation->height) {
            $params['height'] = $transformation->height;
        }
        $params['fit'] = self::FIT[$transformation->fit->value];
        if (null !== $transformation->format) {
            $params['format'] = $transformation->format;
        } elseif (null !== $this->auto) {
            $params['auto'] = $this->auto;
        }
        if (null !== $transformation->quality) {
            $params['quality'] = $transformation->quality;
        }

        // Raw Fastly IO parameters, e.g. {blur: 10}. Merged before sorting so
        // that they are covered by the signature.
        foreach ($transformation->modifiers as $name => $value) {
            $params[$name] = $value;
        }

        ksort($params);

        if (null !== $this->signKey) {
            $path = parse_url($base, \PHP_URL_PATH) ?: '/';
            $signature = strtr(base64_encode(hash_hmac('sha256', $path.'?'.http_build_query($params), $this->signKey, true)), '+/', '-_');
            $params['sig'] = rtrim($signature, '=');
        }

        $url = $base.'?'.http_build_query($params);

        $width = $transformation->width;
        $height = $transformation->height ?? ($width && $source->dimensions ? $source->dimensions->heightForWidth($width) : null);

        return new GeneratedImage($url, $width, $height, $transformation->format);
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            formats: ['avif', 'webp', 'jpg', 'png'],
            widths: null,
            canResize: true,
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function splitUrl(string $url): array
    {
        if ('default' !== $this->host) {
            $scheme = $this->secure ? 'https' : 'http';
            if (str_starts_with($url, '/')) {
                $url = \sprintf('%s://%s%s', $scheme, $this->host, $url);
            } else {
                $parts = parse_url($url);
                $url = \sprintf('%s://%s%s', $scheme, $this->host, $parts['path'] ?? '/')
                    .(isset($parts['query']) ? '?'.$parts['query'] : '');
            }
        }

        $query = [];
        if (false !== ($position = strpos($url, '?'))) {
            parse_str(substr($url, $position + 1), $query);
            $url = substr($url, 0, $position);
        }

        return [$url, $query];
    }
}
