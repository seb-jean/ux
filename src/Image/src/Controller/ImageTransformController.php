<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformer\ImageTransformer;

/**
 * Handles on-the-fly image transformation requests from the local provider.
 *
 * Route: GET /_image?src=…&w=…&h=…&format=…&q=…&fit=…
 *
 * @author Sébastien Jean <contact@seb-jean.fr>
 *
 * @internal
 */
final class ImageTransformController
{
    public function __construct(
        private readonly ImageTransformer $transformer,
        private readonly string $publicDir,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $src = $request->query->getString('src');
        if ('' === $src) {
            return new Response('Missing "src" parameter.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $srcPath = $this->resolvePath($src);
        } catch (InvalidArgumentException $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if (!is_file($srcPath)) {
            return new Response('Image not found.', Response::HTTP_NOT_FOUND);
        }

        $options = [
            'width' => $request->query->getInt('w') ?: null,
            'height' => $request->query->getInt('h') ?: null,
            'format' => $request->query->getString('format') ?: null,
            'quality' => $request->query->getInt('q') ?: null,
            'fit' => $request->query->getString('fit') ?: null,
        ];

        try {
            [$data, $mimeType] = $this->transformer->transform($srcPath, $options);
        } catch (InvalidArgumentException $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response($data, Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function resolvePath(string $src): string
    {
        $publicDir = realpath($this->publicDir);
        $candidate = $publicDir.'/'.ltrim($src, '/');

        // Prevent path traversal before the file exists check
        $normalized = implode('/', array_filter(explode('/', str_replace('\\', '/', $candidate)), static fn ($s) => '' !== $s && '.' !== $s));
        if (!str_starts_with($normalized, ltrim(str_replace('\\', '/', $publicDir), '/'))) {
            throw new InvalidArgumentException('Invalid image path.');
        }

        $extension = strtolower(pathinfo($candidate, \PATHINFO_EXTENSION));
        if (!\in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported image extension "%s".', $extension));
        }

        return $candidate;
    }
}
