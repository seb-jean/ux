# Symfony UX Image: Cloudinary

[Cloudinary](https://cloudinary.com/) integration for Symfony UX Image.

## Installation

Install the bridge using Composer and Symfony Flex:

```shell
composer require symfony/ux-cloudinary-image
```

## DSN example

```dotenv
# public delivery
UX_IMAGE_DSN=cloudinary://my-cloud

# signed delivery
UX_IMAGE_DSN=cloudinary://my-key:my-secret@my-cloud?sign_urls=true

# remote fetch delivery
UX_IMAGE_DSN=cloudinary://my-cloud?delivery=fetch
```

| Option      | Description                                              | Default    |
|-------------|----------------------------------------------------------|------------|
| `sign_urls` | Prepend a signature segment (`s--xxxxxxxx--`) to the URL | `false`    |
| `secure`    | Use `https`                                              | `true`     |
| `delivery`  | `upload` (public id) or `fetch` (remote origin URL)      | `upload`   |

## How it works

Cloudinary encodes transformations in the URL **path**:

```
https://res.cloudinary.com/my-cloud/image/upload/c_fill,g_auto,w_800,f_auto,q_auto/hero.jpg
```

When no format or quality is requested, the bridge falls back to Cloudinary's
`f_auto` / `q_auto` content negotiation. Signed delivery requires an API secret
in the DSN, otherwise an `UnsupportedTransformationException` is thrown.

## Modifiers

Any Cloudinary transformation parameter can be passed through `modifiers`, using
its own short name:

```twig
<twig:ux:img src="hero.jpg" alt="…" :modifiers="{ e: 'grayscale', bo: '5px_solid_black' }" />
{# .../c_fit,w_800,f_auto,q_auto,e_grayscale,bo_5px_solid_black/hero.jpg #}
```

## Resources

- [Documentation](https://symfony.com/bundles/ux-image/current/index.html)
- [Report issues](https://github.com/symfony/ux/issues) and
  [send Pull Requests](https://github.com/symfony/ux/pulls)
  in the [main Symfony UX repository](https://github.com/symfony/ux)
