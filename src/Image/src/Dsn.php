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

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * Parses an image provider DSN, e.g. "cloudinary://key:secret@cloud?sign_urls=true".
 *
 * Modeled on the UX Map renderer DSN, with password support for provider secrets.
 *
 * @author Symfony Community
 */
final class Dsn
{
    private readonly string $scheme;
    private readonly string $host;
    private readonly ?string $user;
    private readonly ?string $password;
    private readonly array $options;

    public function __construct(#[\SensitiveParameter] private readonly string $dsn)
    {
        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException('The image provider DSN is invalid.');
        }

        if (!isset($params['scheme'])) {
            throw new InvalidArgumentException('The image provider DSN must contain a scheme.');
        }

        if (!isset($params['host'])) {
            throw new InvalidArgumentException('The image provider DSN must contain a host (use "default" for the built-in provider).');
        }

        $this->scheme = $params['scheme'];
        $this->host = $params['host'];
        $this->user = '' !== ($params['user'] ?? '') ? rawurldecode($params['user']) : null;
        $this->password = '' !== ($params['pass'] ?? '') ? rawurldecode($params['pass']) : null;

        $options = [];
        parse_str($params['query'] ?? '', $options);
        $this->options = $options;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRequiredUser(): string
    {
        return $this->user ?? throw new InvalidArgumentException('The image provider DSN is missing a user (API key).');
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function getBooleanOption(string $key, bool $default = false): bool
    {
        return filter_var($this->getOption($key, $default), \FILTER_VALIDATE_BOOL);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOriginalDsn(): string
    {
        return $this->dsn;
    }
}
