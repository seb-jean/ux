<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Url;

/**
 * Signs image URLs, so that only transformations a template asked for are ever
 * computed.
 *
 * This does not reuse {@see \Symfony\Component\HttpFoundation\UriSigner}: image
 * URLs must never expire nor change between two renders of the same page, while
 * that signer is built around an expiration timestamp folded into the URL.
 */
final class ImageUriSigner
{
    private const HASH_LENGTH = 24;

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    /**
     * @param array<string, mixed> $query the query parameters, without the hash
     */
    public function sign(string $path, array $query): string
    {
        ksort($query);

        $signature = hash_hmac('sha256', $path."\n".http_build_query($query), $this->secret, true);

        return substr(rtrim(strtr(base64_encode($signature), '+/', '-_'), '='), 0, self::HASH_LENGTH);
    }

    /**
     * @param array<string, mixed> $query the query parameters, without the hash
     */
    public function isValid(string $path, array $query, string $hash): bool
    {
        return hash_equals($this->sign($path, $query), $hash);
    }
}
