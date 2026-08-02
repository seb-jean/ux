# Symfony UX Image: Fastly

[Fastly Image Optimizer](https://developer.fastly.com/reference/io/) integration
for Symfony UX Image.

## Installation

Install the bridge using Composer and Symfony Flex:

```shell
composer require symfony/ux-fastly-image
```

## DSN example

```dotenv
# your app is already fronted by Fastly Image Optimizer
UX_IMAGE_DSN=fastly://default

# rewrite asset URLs to a dedicated host
UX_IMAGE_DSN=fastly://cdn.example.com?auto=webp

# protected URLs
UX_IMAGE_DSN=fastly://my-sign-key@cdn.example.com
```

| Option     | Description                                             | Default |
|------------|---------------------------------------------------------|---------|
| `auto`     | Value of the `auto` param when no format is requested   | `webp`  |
| `secure`   | Use `https` when rewriting the host                     | `true`  |
| `sign_key` | HMAC key for URL protection (or pass it as the DSN user) | `null`  |

## How it works

Fastly Image Optimizer passes transformations as **query params** on the origin
URL:

```
https://cdn.example.com/images/hero.jpg?fit=cover&format=webp&quality=75&width=800
```

Params are sorted before signing. When a sign key is configured, a `sig=`
HMAC-SHA256 parameter computed over `path?sortedquery` is appended.

## Modifiers

Any Fastly IO parameter can be passed through `modifiers`:

```twig
<twig:ux:img src="hero.jpg" alt="…" :modifiers="{ blur: 10, brightness: 20 }" />
{# ...?auto=webp&blur=10&brightness=20&fit=bounds&width=800 #}
```

Modifiers are merged before the params are sorted, so they are covered by the
signature when URL protection is enabled.

## Resources

- [Documentation](https://symfony.com/bundles/ux-image/current/index.html)
- [Report issues](https://github.com/symfony/ux/issues) and
  [send Pull Requests](https://github.com/symfony/ux/pulls)
  in the [main Symfony UX repository](https://github.com/symfony/ux)
