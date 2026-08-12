Symfony UX Image
================

**EXPERIMENTAL** This component is currently experimental and is likely
to change, or even change drastically.

Symfony UX Image transforms images on the server — resizing, cropping, converting
formats — and renders them in Twig templates.

Transformations are named after the `Fastly Image Optimizer`_ query parameters, so
``width``, ``fit``, ``crop`` or ``format`` mean here exactly what they mean there.
A transformed image is generated the first time it is requested, stored on disk and
served with immutable cache headers, behind a signed URL.

Installation
------------

Install the bundle using Composer and Symfony Flex:

.. code-block:: terminal

    $ composer require symfony/ux-image

The transformations are performed by the `GD`_ or the `Imagick`_ PHP extension.
At least one of them must be installed; both are supported and produce the same
images.

Then import the route the transformed images are served from:

.. code-block:: yaml

    # config/routes.yaml
    ux_image:
        resource: '@UXImageBundle/config/routes.php'

Usage
-----

The ``ux_image()`` function renders an ``<img>`` tag for an image stored in the
public directory:

.. code-block:: html+twig

    {{ ux_image('/images/photo.jpg', { width: 800 }) }}

It renders the tag below, where the ``src`` points to the transformed image, and
the dimensions describe what the browser is about to download:

.. code-block:: html

    <img src="/_image/images/photo.jpg?quality=85&amp;width=800&amp;v=6f1a2c94&amp;_hash=..."
         alt="" width="800" height="600" loading="lazy" decoding="async">

The third argument adds attributes to the tag, and overrides the defaults:

.. code-block:: html+twig

    {{ ux_image('/images/photo.jpg', { width: 800 }, { alt: 'A photo', class: 'hero', loading: 'eager' }) }}

The same image can be rendered with the ``UX:Image`` Twig component. Transformations
are written as attributes, and anything that is not a transformation ends up on the
``<img>`` tag:

.. code-block:: html+twig

    <twig:UX:Image src="/images/photo.jpg" width="800" fit="cover" alt="A photo" class="hero" />

When only the URL is needed, use ``ux_image_url()``:

.. code-block:: html+twig

    <div style="background-image: url({{ ux_image_url('/images/photo.jpg', { width: 1200 }) }})"></div>

Source images are resolved inside the public directory, and nothing else can be
transformed: a path pointing outside of it returns a 404 response.

Transformations
---------------

============== ==================================================== ==========================================
Transformation Values                                               Description
============== ==================================================== ==========================================
``width``      a number of pixels                                   Resizes the width
``height``     a number of pixels                                   Resizes the height
``dpr``        ``1`` to ``5``                                       Multiplies the size of the generated file
``fit``        ``bounds``, ``cover``, ``crop``                      Fits the image within ``width``/``height``
``crop``       see below                                            Removes pixels from the image
``format``     ``avif``, ``gif``, ``jpeg``, ``png``, ``webp``       Converts the output format
``quality``    ``0`` to ``100``, defaults to ``85``                 Compression level of lossy formats
``auto``       ``avif``, ``webp``                                   Negotiates the format with the browser
``bg-color``   ``fff``, ``ffffff``, ``ffffff80``, ``rgb(0, 0, 0)``  Background color behind transparency
``orient``     ``1`` to ``8``, ``r``, ``l``, ``h``, ``v``           Rotates and mirrors the image
``bw``         ``true`` or ``false``                                Converts the image to black and white
``blur``       ``0`` to ``1000``                                    Blurs the image
``brightness`` ``-100`` to ``100``                                  Changes the brightness
``contrast``   ``-100`` to ``100``                                  Changes the contrast
============== ==================================================== ==========================================

``fit`` only has an effect when both ``width`` and ``height`` are given, and
defaults to ``bounds``:

.. code-block:: html+twig

    {# 800x600 stays inside the box: 400x300 #}
    {{ ux_image('/images/photo.jpg', { width: 400, height: 400 }) }}

    {# covers the box, one side overflows: 533x400 #}
    {{ ux_image('/images/photo.jpg', { width: 400, height: 400, fit: 'cover' }) }}

    {# covers the box, then the excess is cut off: 400x400 #}
    {{ ux_image('/images/photo.jpg', { width: 400, height: 400, fit: 'crop' }) }}

``crop`` takes a size in pixels or an aspect ratio, an optional position, and the
``safe`` modifier that shrinks a region that would not fit instead of failing:

.. code-block:: html+twig

    {{ ux_image('/images/photo.jpg', { crop: '1000,500' }) }}
    {{ ux_image('/images/photo.jpg', { crop: '16:9' }) }}
    {{ ux_image('/images/photo.jpg', { crop: '1000,500,x400,y50' }) }}
    {{ ux_image('/images/photo.jpg', { crop: '1000,500,x25p,y10p' }) }}
    {{ ux_image('/images/photo.jpg', { crop: '1:1,offset-x90,offset-y50' }) }}
    {{ ux_image('/images/photo.jpg', { crop: '3000,600,safe' }) }}

A region is centered when no position is given. ``x``/``y`` place its top left
corner, in pixels or in percent when suffixed with ``p``, while ``offset-x`` and
``offset-y`` distribute the leftover space, ``0`` being flush left and ``100``
flush right.

``dpr`` renders a denser file without changing the size the tag advertises, which
is what a high density screen needs:

.. code-block:: html+twig

    {# the file is 800x600, the tag says 400x300 #}
    {{ ux_image('/images/photo.jpg', { width: 400, dpr: 2 }) }}

``auto`` serves a modern format to the browsers that accept it, and the original
format to the others. The response then varies on the ``Accept`` header:

.. code-block:: html+twig

    {{ ux_image('/images/photo.jpg', { width: 800, auto: 'webp' }) }}

Images are straightened using their EXIF orientation, unless ``orient`` says
otherwise.

Presets
-------

Transformations used across templates can be named once in the configuration:

.. code-block:: yaml

    # config/packages/ux_image.yaml
    ux_image:
        presets:
            thumbnail: { width: 300, height: 300, fit: crop, format: webp }

A preset is then used instead of a map of transformations:

.. code-block:: html+twig

    {{ ux_image('/images/photo.jpg', 'thumbnail') }}

    <twig:UX:Image src="/images/photo.jpg" preset="thumbnail" />

Configuration
-------------

.. code-block:: yaml

    # config/packages/ux_image.yaml
    ux_image:
        # "auto" picks Imagick when available, GD otherwise
        driver: auto

        # where source images are read from
        public_dir: '%kernel.project_dir%/public'

        # where transformed images are stored
        cache_dir: '%kernel.cache_dir%/ux_image'

        # the path images are served from
        route_prefix: '/_image'

        # default compression level of lossy formats
        quality: 85

        # the largest image a transformation may ask for
        max_width: 4000
        max_height: 4000

        # formats negotiated for every image, without asking for them
        auto: ['webp']

        # the output formats a transformation may ask for
        allowed_formats: ['avif', 'gif', 'jpeg', 'png', 'webp']

        presets:
            thumbnail: { width: 300, height: 300, fit: crop }

How images are served
---------------------

``ux_image()`` does not transform anything: it resolves the source file, computes
the dimensions of the result and returns a signed URL. The image itself is
generated by the controller behind that URL, the first time a browser requests it,
and written to ``cache_dir``. Later requests are served from disk.

The signature covers the path and every transformation, so the route cannot be
turned into an image resizing service for anyone who finds it. The limits are
enforced again when a request comes in, and a URL that has been tampered with
returns a 403 response.

The URL also carries a token derived from the modification time of the source
file. Replacing ``photo.jpg`` changes the URL of every image derived from it,
which is what makes it safe to serve them as ``immutable``.

Deleting ``cache_dir`` is always safe: the images are regenerated on demand.

Not supported yet
-----------------

The following Fastly parameters are recognized and rejected with an explicit
error, rather than silently ignored: ``pad``, ``canvas``, ``trim``, ``precrop``,
``sharpen``, ``saturation``, ``metadata``, ``frame``, ``level``, ``profile``,
``resize-filter``, ``optimize``, ``enable``, ``disable``, ``viewbox``, and the
``smart`` modifier of ``crop``.

Animated images are reduced to their first frame, and SVG files are not
transformed.

The ``blur`` transformation is an approximation with the GD driver, which only
exposes a fixed blur kernel; Imagick honors the requested radius.

Backward Compatibility promise
------------------------------

This bundle aims at following the same Backward Compatibility promise as the
Symfony framework:
https://symfony.com/doc/current/contributing/code/bc.html

.. _`Fastly Image Optimizer`: https://www.fastly.com/documentation/reference/io/
.. _`GD`: https://www.php.net/manual/en/book.image.php
.. _`Imagick`: https://www.php.net/manual/en/book.imagick.php
