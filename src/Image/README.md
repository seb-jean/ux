# Symfony UX Image

Symfony UX Image is a Symfony bundle providing a `<twig:ux:img>` Twig component
that generates optimal, responsive `<img>` / `<picture>` markup — `srcset`/`sizes`,
modern formats, explicit `width`/`height` against CLS, `fetchpriority`, `decoding` —
**without a single line of JavaScript**. It is part of
[the Symfony UX initiative](https://ux.symfony.com/).

`ux-image` never transforms images itself: it builds the markup and delegates
variant-URL generation to a **provider** selected by DSN. A zero-config `default`
provider is bundled (passthrough + real dimension reading); CDN providers are
shipped as separate bridges (`symfony/ux-cloudinary-image`, `symfony/ux-fastly-image`).

**This repository is a READ-ONLY sub-tree split**. See
https://github.com/symfony/ux to create issues or submit pull requests.

## Resources

- [Documentation](https://symfony.com/bundles/ux-image/current/index.html)
- [Report issues](https://github.com/symfony/ux/issues) and
  [send Pull Requests](https://github.com/symfony/ux/pulls)
  in the [main Symfony UX repository](https://github.com/symfony/ux)
