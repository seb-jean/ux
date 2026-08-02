# CHANGELOG

## 3.4.0

- Introduce the `symfony/ux-image` package with a `<twig:ux:img>` component
  and a pluggable image-provider abstraction.
- Add `presets`, named sets of props applied through the `preset` prop.
- Add `screens` and the breakpoint syntax of the `sizes` prop
  (`sizes="100vw md:50vw"`), from which candidate widths are derived.
- Add `densities`: an image given an explicit width and no `sizes` now renders a
  `1x, 2x` srcset instead of inheriting the generic widths.
- Add `modifiers`, passing provider-specific options through to the provider.
- Add `<twig:ux:picture>` and `<twig:ux:source>` for art direction, rendering a
  different crop per media condition alongside the format sources. Each `<source>`
  carries its own `width`/`height`, so changing the aspect ratio between
  breakpoints no longer causes a layout shift.
- Support `sizes="auto"`, letting the browser use the concrete layout size,
  optionally followed by a fallback list for browsers without the keyword, and
  reject it on non-lazy images where it is invalid.
- Allow fractional `densities` such as `1.5`, as the pixel density descriptor is
  a floating-point number.
- Cap srcset candidates at the source's natural width, since a width descriptor
  must match the natural width of the resource it points at.
