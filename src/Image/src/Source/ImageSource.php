<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Source;

use Symfony\UX\Image\Exception\SourceNotFoundException;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Orientation;
use Symfony\UX\Image\Transformation\Size;

/**
 * A source image, resolved inside the public directory.
 */
final class ImageSource
{
    private Size|false|null $size = null;
    private Orientation|false|null $orientation = null;

    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
        public readonly Format $format,
        public readonly int $modifiedAt,
    ) {
    }

    /**
     * The pixel dimensions, or null when the file cannot be read as an image.
     */
    public function size(): ?Size
    {
        if (null === $this->size) {
            $dimensions = @getimagesize($this->path);

            $this->size = false !== $dimensions && $dimensions[0] > 0 && $dimensions[1] > 0
                ? new Size($dimensions[0], $dimensions[1])
                : false;
        }

        return $this->size ?: null;
    }

    public function sizeOrFail(): Size
    {
        return $this->size() ?? throw new SourceNotFoundException($this->relativePath, 'its dimensions cannot be read');
    }

    /**
     * The orientation recorded in the EXIF metadata, if any.
     *
     * It is the implicit value of the "orient" transformation, so that a photo
     * shot sideways is rendered upright without asking for it.
     */
    public function exifOrientation(): ?Orientation
    {
        if (null === $this->orientation) {
            $this->orientation = false;

            if (Format::Jpeg === $this->format && \function_exists('exif_read_data')) {
                $exif = @exif_read_data($this->path, 'IFD0');

                if (isset($exif['Orientation']) && is_numeric($exif['Orientation'])) {
                    $this->orientation = Orientation::tryFromExif((int) $exif['Orientation']) ?? false;
                }
            }
        }

        return $this->orientation ?: null;
    }
}
