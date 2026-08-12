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

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\SourceNotFoundException;
use Symfony\UX\Image\ImageTransformer;
use Symfony\UX\Image\Source\ImageSourceResolver;
use Symfony\UX\Image\Transformation\TransformationFactory;
use Symfony\UX\Image\Url\ImageUriSigner;
use Symfony\UX\Image\Url\ImageUrlGenerator;

/**
 * Serves transformed images, generating them on the first request.
 *
 * @internal
 */
final class ImageController
{
    private const MAX_AGE = 31536000;

    public function __construct(
        private readonly ImageSourceResolver $sourceResolver,
        private readonly TransformationFactory $transformationFactory,
        private readonly ImageTransformer $transformer,
        private readonly ImageUriSigner $signer,
    ) {
    }

    public function __invoke(Request $request, string $path): Response
    {
        $query = $request->query->all();
        $hash = $query[ImageUrlGenerator::HASH_PARAMETER] ?? null;
        unset($query[ImageUrlGenerator::HASH_PARAMETER]);

        // the signature is what keeps this route from being an image resizing
        // service for anyone who finds it
        if (!\is_string($hash) || !$this->signer->isValid($path, $query, $hash)) {
            throw new AccessDeniedHttpException(\sprintf('The image URL for "%s" is not signed, or the signature does not match its transformations.', $path));
        }

        try {
            $source = $this->sourceResolver->resolve($path);
        } catch (SourceNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        try {
            // the limits are checked again here: a signature is not a permission
            // to ask for anything the configuration forbids
            $transformations = $this->transformationFactory->fromQuery($query);
            $format = $this->transformer->resolveFormat($source, $transformations, $request->getAcceptableContentTypes());
            $file = $this->transformer->transform($source, $transformations, $format);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $response = new BinaryFileResponse($file, headers: ['Content-Type' => $format->mimeType()]);
        $response->setAutoEtag();
        $response->setAutoLastModified();
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        // the URL carries a token derived from the source file, so a given URL
        // will never have to return different bytes
        $response->headers->addCacheControlDirective('immutable');

        if ([] !== $transformations->auto) {
            $response->setVary('Accept');
        }

        $response->isNotModified($request);

        return $response;
    }
}
