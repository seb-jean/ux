Symfony UX Image
================

Symfony UX Image provides a ``<twig:ux:img>`` Twig component that renders
optimal, responsive images **without a single line of JavaScript**. It is part
of `the Symfony UX initiative`_.

Writing a correct ``<img>`` tag is surprisingly hard: it needs ``srcset`` and
``sizes`` to avoid shipping desktop images to phones, modern formats such as
AVIF or WebP, explicit ``width``/``height`` to prevent layout shifts, and the
right ``loading``, ``decoding`` and ``fetchpriority`` hints. This component
generates all of it from a minimum of information.

The component never transforms images itself: it builds the markup and delegates
the generation of the variant URLs to a **provider**. A provider requiring no
external service is bundled, and CDN providers ship as separate bridges.

Installation
------------

.. code-block:: terminal

    $ composer require symfony/ux-image

That is all: the bundled provider needs no configuration and no external
service.

Usage
-----

The simplest possible call already produces a well-formed image:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="Product shot" />

.. code-block:: html

    <img src="/images/hero.jpg" alt="Product shot"
         width="1600" height="900" loading="lazy" decoding="async">

The ``src`` is resolved through the Asset component, so it benefits from the
versioning strategy of AssetMapper, Webpack Encore or Symfony Reprise. The
``width`` and ``height`` attributes are read from the image file itself.

Preventing layout shifts
~~~~~~~~~~~~~~~~~~~~~~~~

Whenever the source can be located on the filesystem, its intrinsic dimensions
are read and rendered, which reserves the right space in the layout and avoids
`Cumulative Layout Shift`_. Pass ``width`` and ``height`` explicitly to override
them, which is required for remote sources that cannot be inspected:

.. code-block:: html+twig

    <twig:ux:img src="https://example.com/hero.jpg" alt="…" width="1600" height="900" />

.. caution::

    The attributes alone are not enough: a CSS ``width: auto`` overrides the width
    the browser computed from them, and it can no longer reserve the space. Pair
    them with the usual responsive rule, which keeps the ratio derived from the
    attributes:

    .. code-block:: css

        img, picture { max-width: 100%; height: auto; }

    Setting only ``height: auto`` is what makes the attributes work; ``width: auto``
    silently defeats them.

When the layout reserves the space itself — a CSS grid cell, an
``aspect-ratio`` rule, a container the image simply fills — the inferred
attributes can get in the way. ``:dimensions="false"`` turns the inference off:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" :dimensions="false" />

Only the inference is disabled, not the attributes: a ``width`` or ``height``
written explicitly is still rendered, and no longer drags the other one along
through the aspect ratio. Reach for it deliberately — it gives up the protection
against layout shifts, so the CSS has to provide it instead.

Responsive images
~~~~~~~~~~~~~~~~~

Give the browser several candidates with ``widths``, and describe how much space
the image takes with ``sizes``:

.. code-block:: html+twig

    <twig:ux:img
        src="images/hero.jpg" alt="…"
        :widths="[640, 960, 1280, 1920]"
        sizes="(max-width: 600px) 100vw, 50vw"
    />

.. code-block:: html

    <img src="/images/hero.jpg?width=1920" alt="…"
         srcset="/images/hero.jpg?width=640 640w, /images/hero.jpg?width=960 960w,
                 /images/hero.jpg?width=1280 1280w, /images/hero.jpg?width=1920 1920w"
         sizes="(max-width: 600px) 100vw, 50vw"
         width="1600" height="900" loading="lazy" decoding="async">

.. note::

    The exact URLs depend on the provider in use. A provider unable to resize —
    such as the bundled one — ignores ``widths`` and renders a single ``<img>``.

Candidates wider than the source itself are dropped, and replaced by the source's
own width. A width descriptor must match the natural width of the resource it
points at, and no provider invents detail: asking for 1920px of an 800px image
returns the 800px one, so announcing ``1920w`` would mislead the browser into
picking a variant that carries nothing more. This relies on the dimensions read
server-side, so it applies whenever the source can be inspected.

.. tip::

    ``sizes`` is worth writing whenever the image is not full-width. Without it,
    a browser given width descriptors assumes the image spans the whole viewport
    (``100vw``) and downloads a candidate sized for that — often two steps larger
    than needed.

Named breakpoints
~~~~~~~~~~~~~~~~~

``sizes`` also accepts named breakpoints, which is usually easier to read than
raw media conditions:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" sizes="100vw md:50vw xl:400px" />

.. code-block:: html

    sizes="(min-width: 1280px) 400px, (min-width: 768px) 50vw, 100vw"

Breakpoints are always rendered from the widest to the narrowest, because the
first matching condition wins — the declaration order does not matter.

More importantly, the candidate widths are then **derived from the layout**
rather than taken from a generic list: ``xl:400px`` implies a 400px candidate,
``md:50vw`` implies 384px (half of the 768px breakpoint), and so on. Set
``widths`` explicitly to opt out.

The breakpoint names come from the ``screens`` configuration, which defaults to
the usual Tailwind scale:

.. code-block:: yaml

    ux_image:
        screens: { xs: 320, sm: 640, md: 768, lg: 1024, xl: 1280, xxl: 1536 }

Letting the browser measure
~~~~~~~~~~~~~~~~~~~~~~~~~~~

A ``sizes`` value that does not match the actual CSS layout is the most common
way to lose the benefit of ``srcset``: the browser then picks the wrong variant.
``sizes="auto"`` sidesteps the problem entirely by letting the browser use the
concrete layout size:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" sizes="auto" />

Browsers that do not support the keyword fall back to ``100vw``, which is rarely
what the layout does. Give them a fallback list by putting it after ``auto``:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" sizes="auto 100vw xl:400px" />

.. code-block:: html

    sizes="auto, (min-width: 1280px) 400px, 100vw"

The fallback also drives the candidate widths: a lazily loaded image only gets
its concrete size once the layout is done, so the declared sizes are what any
browser uses to pick a candidate for the initial fetch.

.. note::

    The keyword is only valid on a lazily loaded image, so it cannot be combined
    with ``priority`` — doing so raises an exception rather than emitting invalid
    markup. Support is currently limited to Chrome, Edge and Opera, and is part of
    Interop 2026.

Fixed-size images
~~~~~~~~~~~~~~~~~

Not every image is fluid. An avatar rendered at a known size does not need a
width-based ``srcset`` — it needs the same image at higher pixel densities, for
HiDPI screens. When an image is given an explicit ``width`` and no ``sizes``,
the component switches to density descriptors automatically:

.. code-block:: html+twig

    <twig:ux:img src="user/42.png" alt="…" width="96" height="96" fit="cover" />

.. code-block:: html

    <img src="…?width=96&height=96" srcset="…?width=96&height=96 1x, …?width=192&height=192 2x"
         width="96" height="96" loading="lazy" decoding="async">

No ``sizes`` attribute is rendered, since the image size does not depend on the
viewport. The densities are configurable, and may be fractional:

.. code-block:: yaml

    ux_image:
        defaults:
            densities: [1, 1.5, 2]

.. note::

    Densities only apply to fixed-size images. In the width-based mode, the ``w``
    descriptors combined with ``sizes`` already let the browser account for the
    device pixel ratio, so multiplying the candidates would only add redundant
    URLs.

Modern formats
~~~~~~~~~~~~~~

Listing ``formats`` switches the output to a ``<picture>`` element, letting the
browser pick the first format it supports and falling back to the original one:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" :formats="['avif', 'webp']" />

.. code-block:: html

    <picture>
        <source type="image/avif" srcset="…">
        <source type="image/webp" srcset="…">
        <img src="/images/hero.jpg" alt="…" width="1600" height="900" loading="lazy" decoding="async">
    </picture>

Formats a provider does not support are dropped, so it is safe to ask for AVIF
everywhere.

Art direction
~~~~~~~~~~~~~

Serving the *same* image at several sizes is responsive design; serving a
*different* crop depending on the viewport is art direction — a wide banner on
desktop, a portrait one on mobile. That needs explicit sources, so the component
mirrors the HTML elements:

.. code-block:: html+twig

    <twig:ux:picture>
        <twig:ux:source media="(min-width: 1024px)" src="hero-wide.jpg" :widths="[1024, 1920]" />
        <twig:ux:source media="(min-width: 640px)" src="hero-square.jpg" :widths="[640, 1024]" />
        <twig:ux:img src="hero-portrait.jpg" alt="…" :widths="[320, 640]" />
    </twig:ux:picture>

.. code-block:: html

    <picture>
        <source media="(min-width: 1024px)" type="image/avif" srcset="…">
        <source media="(min-width: 1024px)" type="image/webp" srcset="…">
        <source media="(min-width: 1024px)" srcset="…">
        <source media="(min-width: 640px)" type="image/avif" srcset="…">
        …
        <img src="/hero-portrait.jpg?width=640" alt="…" srcset="…">
    </picture>

Each ``<twig:ux:source>`` renders one ``<source>`` per requested format plus a
last one without a ``type``, as the fallback for that media condition — so
formats and art direction combine instead of competing. Sources are emitted in
declaration order, and the browser picks the first one that matches: declare the
widest condition first.

``<twig:ux:source>`` accepts the same props as ``<twig:ux:img>`` (except ``alt``
and ``priority``), plus ``media``.

Each ``<source>`` also carries its **own** ``width`` and ``height``, read from
that source image. This matters: art direction is precisely the case where the
aspect ratio changes between breakpoints, and browsers that do not find the
dimensions on the selected source fall back to the ones on the ``<img>`` — which
belong to another crop, and the layout shifts. Chrome and Safari honour these
attributes; Firefox still reads the ``<img>``, so the fix helps where it can and
changes nothing elsewhere.

.. note::

    Inside a ``<twig:ux:picture>``, ``<twig:ux:img>`` never wraps itself in a
    nested ``<picture>``: it is the fallback of the one you opened. It does still
    contribute its own format ``<source>`` elements, emitted just before the
    ``<img>`` and after the ``<twig:ux:source>`` you declared, so the fallback
    crop keeps AVIF and WebP too. Pass ``:formats="[]"`` to render a bare ``<img>``
    instead. Outside a ``<twig:ux:picture>``, ``<twig:ux:img>`` builds its own
    ``<picture>`` when ``formats`` are requested.

The LCP image
~~~~~~~~~~~~~

Images are lazy-loaded by default, which is the right call for everything below
the fold — but not for the `Largest Contentful Paint`_ image, which should be
fetched as early as possible. Mark it with ``priority``:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" priority />

This renders ``loading="eager"``, ``fetchpriority="high"`` and
``decoding="sync"`` instead of the defaults. Use it on one image per page.

``priority`` is only a shorthand: ``loading``, ``decoding`` and ``fetchpriority``
are props of their own, and an explicit one always wins. That matters for the LCP
image, where an asynchronous decode keeps the decoding off the rendering path
while the fetch stays eager and high-priority:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" priority decoding="async" />

They are also useful on their own — ``fetchpriority="low"`` deprioritises an
image that is visible but not important, ``loading="eager"`` opts a single image
out of lazy loading without the rest of the ``priority`` treatment. Values are
validated: ``loading`` accepts ``lazy`` and ``eager``, ``decoding`` accepts
``sync``, ``async`` and ``auto``, ``fetchpriority`` accepts ``high``, ``low`` and
``auto``.

.. note::

    Write these as props, not as extra attributes. The component renders the three
    attributes itself, so passing them through would emit each one twice, and a
    duplicate attribute resolves to its first occurrence — the value you wrote
    would be the one dropped.

.. tip::

    ``fetchpriority="high"`` is enough for most LCP images. When the image is
    discovered late (behind a carousel, or set from CSS), preload it as well, with
    an ``imagesrcset``/``imagesizes`` pair mirroring what the component renders:

    .. code-block:: html+twig

        <link rel="preload" as="image" fetchpriority="high"
              imagesrcset="…" imagesizes="…">

    Keep the two in sync by hand: a preload that does not match the ``srcset``
    and ``sizes`` of the ``<img>`` downloads a second, unused variant.

Cropping and quality
~~~~~~~~~~~~~~~~~~~~

``fit`` controls how the image fills the requested box, and ``quality`` the
compression level:

.. code-block:: html+twig

    <twig:ux:img src="user/42/avatar.png" alt="…" width="96" height="96" fit="cover" quality="80" />

============= ===============================================================
``fit``       Behavior
============= ===============================================================
``contain``   Fit inside the box, keep the aspect ratio, no crop *(default)*
``cover``     Fill the box, keep the aspect ratio, crop the overflow
``fill``      Stretch to the box, ignoring the aspect ratio
============= ===============================================================

Extra attributes
~~~~~~~~~~~~~~~~

Any other attribute is forwarded to the ``<img>`` element:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" class="rounded-xl shadow" id="hero" />

When ``formats`` are requested, the component wraps the ``<img>`` in a
``<picture>`` of its own. Prefix an attribute with ``picture:`` to put it there
instead:

.. code-block:: html+twig

    <twig:ux:img src="images/hero.jpg" alt="…" class="rounded-xl" picture:class="block w-full" />

.. code-block:: html

    <picture class="block w-full">
        <source type="image/avif" srcset="…">
        <source type="image/webp" srcset="…">
        <img src="…" alt="…" class="rounded-xl" …>
    </picture>

``<twig:ux:picture>`` needs none of this: its root element already is the
``<picture>``, so its attributes land on it directly.

Presets
~~~~~~~

Repeating the same combination of props across templates is tedious and drifts
over time. Declare it once as a preset:

.. code-block:: yaml

    ux_image:
        presets:
            avatar: { width: 96, height: 96, fit: cover, quality: 80 }
            hero:   { sizes: '100vw md:50vw', priority: true }

.. code-block:: html+twig

    <twig:ux:img src="user/42.png" alt="…" preset="avatar" />

    {# a prop given explicitly always wins over the preset #}
    <twig:ux:img src="user/42.png" alt="…" preset="avatar" width="48" />

Precedence is **explicit prop > preset > global defaults**. An unknown preset
name raises an exception. Symfony developers coming from LiipImagineBundle will
recognise the idea behind its *filter sets*.

Provider-specific options
~~~~~~~~~~~~~~~~~~~~~~~~~

``width``, ``format``, ``quality`` and ``fit`` cover what every provider can do.
For anything specific to one CDN, ``modifiers`` are passed through untouched:

.. code-block:: html+twig

    {# Cloudinary: e_grayscale #}
    <twig:ux:img src="hero.jpg" alt="…" :modifiers="{ e: 'grayscale' }" />

    {# Fastly: ?blur=10 #}
    <twig:ux:img src="hero.jpg" alt="…" :modifiers="{ blur: 10 }" />

Keys are the provider's own parameter names, so its full feature set stays
reachable. They can also be set globally under ``defaults.modifiers`` or inside
a preset.

Props reference
~~~~~~~~~~~~~~~

================= ============================================ ========================================
Prop              Description                                  Default
================= ============================================ ========================================
``src``           Source (asset path, public id or URL)        *(required)*
``alt``           Alternative text (``""`` for decorative)     ``''``
``width``         Intrinsic width, in pixels                   read from the source
``height``        Intrinsic height, in pixels                  read from the source
``dimensions``    Set to ``false`` to stop inferring them      ``true``
``sizes``         The ``sizes`` attribute, breakpoints allowed ``defaults.sizes``
``widths``        Candidate widths for the ``srcset``          derived, else ``defaults.widths``
``densities``     Densities for fixed-size images              ``defaults.densities``
``formats``       Formats rendered as ``<picture>`` sources    ``defaults.formats``
``priority``      LCP image (eager, ``fetchpriority=high``)    ``false``
``loading``       ``lazy`` or ``eager``                        ``priority``, else ``loading``
``decoding``      ``sync``, ``async`` or ``auto``              ``priority``, else ``decoding``
``fetchpriority`` ``high``, ``low`` or ``auto``                ``high`` with ``priority``, else omitted
``fit``           ``contain``, ``cover`` or ``fill``           ``defaults.fit``
``quality``       Compression quality, from 1 to 100           ``defaults.quality``
``modifiers``     Provider-specific options                    ``defaults.modifiers``
``preset``        Name of a configured preset                  *(none)*
``provider``      Name of the provider to use                  the default provider
================= ============================================ ========================================

Configuration
-------------

Every default used by the component can be changed globally:

.. code-block:: yaml

    # config/packages/ux_image.yaml
    ux_image:
        defaults:
            widths:    [320, 640, 768, 1024, 1366, 1920]
            densities: [1, 2]
            formats:   ['avif', 'webp']
            sizes:     ~          # null, so fixed-size images use densities
            quality:   ~          # null lets the provider decide (often "auto")
            fit:       contain
            modifiers: {}
        screens:  { xs: 320, sm: 640, md: 768, lg: 1024, xl: 1280, xxl: 1536 }
        loading:  lazy          # lazy or eager
        decoding: async         # async, sync or auto

.. caution::

    Keep ``defaults.sizes`` null unless every image on the site is fluid. A
    non-null default applies to *all* images, including those given an explicit
    ``width``, which prevents them from using the density-based ``srcset``.

Choosing the default provider
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Without any configuration, the bundled provider is used. To use another one,
declare it under ``providers`` and point ``provider`` at its **name**:

.. code-block:: yaml

    ux_image:
        provider: cloudinary       # the provider used by default
        providers:
            cloudinary: '%env(CLOUDINARY_DSN)%'
            fastly: '%env(FASTLY_DSN)%'

Every declared provider stays reachable per image through the ``provider`` prop,
which is handy when avatars and marketing images live in different places:

.. code-block:: html+twig

    <twig:ux:img src="user/42/avatar.png" alt="…" provider="fastly" />

When a single provider is enough, ``provider`` also accepts a **DSN** directly,
so there is nothing to name:

.. code-block:: yaml

    ux_image:
        provider: '%env(UX_IMAGE_DSN)%'   # e.g. cloudinary://my-cloud

.. note::

    ``provider`` is read as a provider name first, and as a DSN only when no
    provider goes by that name. When ``provider`` is omitted: a provider named
    ``default`` wins, otherwise a single declared provider is used, otherwise the
    bundled provider applies. Declaring several providers without designating one
    raises an exception when the container is compiled.

Providers
---------

A provider turns a source and a requested transformation into a delivery URL.
The bundled ``default://local`` provider requires no external service: it serves
the original file and reads its real dimensions, so ``width`` and ``height`` are
always rendered. Transforming providers ship as bridges:

==================== ========================================================
Provider             Installation and DSN
==================== ========================================================
`Cloudinary`_        **Install**: ``composer require symfony/ux-cloudinary-image`` \
                     **DSN**: ``cloudinary://<cloud_name>``
`Fastly`_            **Install**: ``composer require symfony/ux-fastly-image`` \
                     **DSN**: ``fastly://<host>``
==================== ========================================================

.. note::

    Read the `Symfony UX Image Cloudinary bridge docs`_ and the
    `Symfony UX Image Fastly bridge docs`_ to learn about their DSN options,
    including URL signing.

Capabilities
~~~~~~~~~~~~

Providers differ: some accept arbitrary widths, others only an allow-list; some
convert to AVIF, others do not; the bundled one cannot resize at all. Each
provider therefore declares its capabilities, and the component adapts instead
of failing:

* an unsupported format is dropped from the ``<picture>`` sources, falling back
  to the original format;
* an unsupported width is clamped to the nearest allowed one, or to the largest
  when none is big enough;
* a provider that cannot resize renders a single ``<img>``, without ``srcset``.

Writing a custom provider
~~~~~~~~~~~~~~~~~~~~~~~~~

A provider builds a URL from an ``ImageSource`` and a ``Transformation``, and
declares what it can do. Implementations must be pure: the same input always
produces the same URL::

    // src/Image/AcmeProvider.php
    namespace App\Image;

    use Symfony\UX\Image\GeneratedImage;
    use Symfony\UX\Image\ImageSource;
    use Symfony\UX\Image\Provider\ImageProviderInterface;
    use Symfony\UX\Image\Provider\ProviderCapabilities;
    use Symfony\UX\Image\Transformation;

    final class AcmeProvider implements ImageProviderInterface
    {
        public function __construct(private readonly string $host)
        {
        }

        public function transform(ImageSource $source, Transformation $transformation): GeneratedImage
        {
            $query = array_filter([
                'w' => $transformation->width,
                'fm' => $transformation->format,
                'q' => $transformation->quality,
            ], static fn ($value) => null !== $value);

            $url = \sprintf('https://%s%s?%s', $this->host, $source->url, http_build_query($query));

            return new GeneratedImage($url, $transformation->width, $transformation->height, $transformation->format);
        }

        public function capabilities(): ProviderCapabilities
        {
            // null widths means any width is accepted
            return new ProviderCapabilities(formats: ['avif', 'webp', 'jpg'], widths: null, canResize: true);
        }
    }

A factory then maps a DSN scheme to that provider::

    // src/Image/AcmeProviderFactory.php
    namespace App\Image;

    use Symfony\UX\Image\Dsn;
    use Symfony\UX\Image\Provider\ImageProviderFactoryInterface;
    use Symfony\UX\Image\Provider\ImageProviderInterface;

    final class AcmeProviderFactory implements ImageProviderFactoryInterface
    {
        public function create(Dsn $dsn): ImageProviderInterface
        {
            return new AcmeProvider($dsn->getHost());
        }

        public function supports(Dsn $dsn): bool
        {
            return 'acme' === $dsn->getScheme();
        }
    }

Register the factory with the ``ux_image.provider_factory`` tag:

.. code-block:: yaml

    # config/services.yaml
    services:
        App\Image\AcmeProviderFactory:
            tags: ['ux_image.provider_factory']

The scheme is now usable in any DSN:

.. code-block:: yaml

    ux_image:
        provider: 'acme://cdn.example.com'

.. tip::

    ``Dsn`` exposes ``getUser()``, ``getPassword()``, ``getOption()`` and
    ``getBooleanOption()``, so credentials and options can travel in the DSN
    itself, as in ``acme://key:secret@cdn.example.com?sign=true``.

Backward Compatibility promise
------------------------------

This bundle follows the Symfony Backward Compatibility promise:
https://symfony.com/doc/current/contributing/code/bc.html

.. _`the Symfony UX initiative`: https://ux.symfony.com/
.. _`Cumulative Layout Shift`: https://web.dev/articles/cls
.. _`Largest Contentful Paint`: https://web.dev/articles/lcp
.. _`Cloudinary`: https://cloudinary.com/
.. _`Fastly`: https://developer.fastly.com/reference/io/
.. _`Symfony UX Image Cloudinary bridge docs`: https://github.com/symfony/ux/blob/3.x/src/Image/src/Bridge/Cloudinary/README.md
.. _`Symfony UX Image Fastly bridge docs`: https://github.com/symfony/ux/blob/3.x/src/Image/src/Bridge/Fastly/README.md
