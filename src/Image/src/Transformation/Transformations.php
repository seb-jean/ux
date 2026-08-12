<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Transformation;

use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Exception\UnsupportedTransformationException;

/**
 * An immutable set of transformations, named after Fastly's query parameters.
 *
 * The same object is built from a Twig call, from a preset and from the query
 * string of an image URL, so a transformation always means the same thing.
 *
 * @see https://www.fastly.com/documentation/reference/io/#query-parameters
 */
final class Transformations
{
    /**
     * The transformations UX Image implements, in Fastly's vocabulary.
     */
    public const PARAMETERS = ['width', 'height', 'dpr', 'fit', 'crop', 'format', 'quality', 'auto', 'bg-color', 'orient', 'bw', 'blur', 'brightness', 'contrast'];

    /**
     * Fastly parameters UX Image knows about but does not implement yet.
     *
     * They are rejected explicitly, so that a template asking for one gets an
     * error instead of an image that silently ignored it.
     */
    private const NOT_IMPLEMENTED = [
        'canvas' => 'padding the canvas is not implemented yet',
        'disable' => 'toggling optimizations is not implemented yet',
        'enable' => 'toggling optimizations is not implemented yet',
        'frame' => 'animated images are not supported yet',
        'level' => 'video output is not supported',
        'metadata' => 'metadata control is not implemented yet',
        'optimize' => 'automatic quality is not implemented yet',
        'pad' => 'padding is not implemented yet',
        'precrop' => 'pre-cropping is not implemented yet',
        'profile' => 'video output is not supported',
        'resize-filter' => 'resize filters are not configurable yet',
        'saturation' => 'saturation is not implemented yet',
        'sharpen' => 'sharpening is not implemented yet',
        'trim' => 'trimming is not implemented yet',
        'viewbox' => 'SVG output is not supported',
    ];

    /**
     * @param list<Format> $auto
     */
    private function __construct(
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?float $dpr = null,
        public readonly ?Fit $fit = null,
        public readonly ?Crop $crop = null,
        public readonly ?Format $format = null,
        public readonly ?int $quality = null,
        public readonly array $auto = [],
        public readonly ?Color $backgroundColor = null,
        public readonly ?Orientation $orientation = null,
        public readonly bool $blackAndWhite = false,
        public readonly ?int $blur = null,
        public readonly ?int $brightness = null,
        public readonly ?int $contrast = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $values = [];

        foreach ($parameters as $key => $value) {
            $name = strtolower(str_replace('_', '-', (string) $key));

            if (isset(self::NOT_IMPLEMENTED[$name])) {
                throw new UnsupportedTransformationException($name, self::NOT_IMPLEMENTED[$name]);
            }

            if (null === $value) {
                continue;
            }

            $values[$name] = $value;
        }

        $unknown = array_diff(array_keys($values), self::PARAMETERS);

        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown image transformation%s "%s".', 1 === \count($unknown) ? '' : 's', implode('", "', $unknown)));
        }

        return new self(
            width: isset($values['width']) ? self::toInt($values['width'], 'width', 1) : null,
            height: isset($values['height']) ? self::toInt($values['height'], 'height', 1) : null,
            dpr: isset($values['dpr']) ? self::toFloat($values['dpr'], 'dpr', 1, 5) : null,
            fit: isset($values['fit']) ? self::toEnum(Fit::class, $values['fit'], 'fit') : null,
            crop: isset($values['crop']) ? self::toCrop($values['crop']) : null,
            format: isset($values['format']) ? self::toEnum(Format::class, $values['format'], 'format') : null,
            quality: isset($values['quality']) ? self::toInt($values['quality'], 'quality', 0, 100) : null,
            auto: isset($values['auto']) ? self::toAuto($values['auto']) : [],
            backgroundColor: isset($values['bg-color']) ? self::toColor($values['bg-color']) : null,
            orientation: isset($values['orient']) ? self::toEnum(Orientation::class, $values['orient'], 'orient') : null,
            blackAndWhite: isset($values['bw']) && self::toBool($values['bw'], 'bw'),
            blur: isset($values['blur']) ? self::toInt($values['blur'], 'blur', 0, 1000) : null,
            brightness: isset($values['brightness']) ? self::toInt($values['brightness'], 'brightness', -100, 100) : null,
            contrast: isset($values['contrast']) ? self::toInt($values['contrast'], 'contrast', -100, 100) : null,
        );
    }

    /**
     * The canonical query parameters, sorted so that equivalent transformations
     * always produce the same URL and the same cache key.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        if (null !== $this->width) {
            $query['width'] = (string) $this->width;
        }
        if (null !== $this->height) {
            $query['height'] = (string) $this->height;
        }
        if (null !== $this->dpr) {
            $query['dpr'] = rtrim(rtrim(number_format($this->dpr, 2, '.', ''), '0'), '.');
        }
        if (null !== $this->fit) {
            $query['fit'] = $this->fit->value;
        }
        if (null !== $this->crop) {
            $query['crop'] = $this->crop->toString();
        }
        if (null !== $this->format) {
            $query['format'] = $this->format->value;
        }
        if (null !== $this->quality) {
            $query['quality'] = (string) $this->quality;
        }
        if ([] !== $this->auto) {
            $query['auto'] = implode(',', array_map(static fn (Format $format) => $format->value, $this->auto));
        }
        if (null !== $this->backgroundColor) {
            $query['bg-color'] = $this->backgroundColor->toString();
        }
        if (null !== $this->orientation) {
            $query['orient'] = $this->orientation->value;
        }
        if ($this->blackAndWhite) {
            $query['bw'] = 'true';
        }
        if (null !== $this->blur) {
            $query['blur'] = (string) $this->blur;
        }
        if (null !== $this->brightness) {
            $query['brightness'] = (string) $this->brightness;
        }
        if (null !== $this->contrast) {
            $query['contrast'] = (string) $this->contrast;
        }

        ksort($query);

        return $query;
    }

    public function isEmpty(): bool
    {
        return [] === $this->toQuery();
    }

    /**
     * Whether any transformation changes the pixels, ignoring the output format.
     */
    public function hasGeometry(): bool
    {
        return null !== $this->width || null !== $this->height || null !== $this->crop || null !== $this->dpr;
    }

    public function withFormat(?Format $format): self
    {
        return $this->with(['format' => $format, 'auto' => []]);
    }

    public function withQuality(?int $quality): self
    {
        return $this->with(['quality' => $quality]);
    }

    public function withAuto(array $auto): self
    {
        return $this->with(['auto' => $auto]);
    }

    public function withOrientation(?Orientation $orientation): self
    {
        return $this->with(['orientation' => $orientation]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function with(array $overrides): self
    {
        return new self(
            width: $overrides['width'] ?? $this->width,
            height: $overrides['height'] ?? $this->height,
            dpr: $overrides['dpr'] ?? $this->dpr,
            fit: $overrides['fit'] ?? $this->fit,
            crop: $overrides['crop'] ?? $this->crop,
            format: \array_key_exists('format', $overrides) ? $overrides['format'] : $this->format,
            quality: \array_key_exists('quality', $overrides) ? $overrides['quality'] : $this->quality,
            auto: $overrides['auto'] ?? $this->auto,
            backgroundColor: $overrides['backgroundColor'] ?? $this->backgroundColor,
            orientation: \array_key_exists('orientation', $overrides) ? $overrides['orientation'] : $this->orientation,
            blackAndWhite: $overrides['blackAndWhite'] ?? $this->blackAndWhite,
            blur: $overrides['blur'] ?? $this->blur,
            brightness: $overrides['brightness'] ?? $this->brightness,
            contrast: $overrides['contrast'] ?? $this->contrast,
        );
    }

    private static function toInt(mixed $value, string $name, ?int $min = null, ?int $max = null): int
    {
        if (\is_bool($value) || (!\is_int($value) && !(\is_string($value) && preg_match('/^-?\d+$/', $value)))) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation must be an integer, got "%s".', $name, get_debug_type($value)));
        }

        $value = (int) $value;

        if ((null !== $min && $value < $min) || (null !== $max && $value > $max)) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation must be between %s and %s, got %d.', $name, $min ?? '-∞', $max ?? '∞', $value));
        }

        return $value;
    }

    private static function toFloat(mixed $value, string $name, float $min, float $max): float
    {
        if (\is_bool($value) || !is_numeric($value)) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation must be a number, got "%s".', $name, get_debug_type($value)));
        }

        $value = (float) $value;

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation must be between %s and %s, got %s.', $name, $min, $max, $value));
        }

        return $value;
    }

    private static function toBool(mixed $value, string $name): bool
    {
        return match (true) {
            \is_bool($value) => $value,
            \in_array($value, ['true', '1', 1, 'yes', ''], true) => true,
            \in_array($value, ['false', '0', 0, 'no'], true) => false,
            default => throw new InvalidArgumentException(\sprintf('The "%s" transformation must be a boolean, got "%s".', $name, get_debug_type($value))),
        };
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private static function toEnum(string $enum, mixed $value, string $name): \BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation must be a string, got "%s".', $name, get_debug_type($value)));
        }

        $case = $enum::tryFrom(\is_string($value) ? strtolower($value) : (string) $value);

        if (null === $case) {
            throw new InvalidArgumentException(\sprintf('The "%s" transformation does not accept "%s", expected one of: "%s".', $name, $value, implode('", "', array_column($enum::cases(), 'value'))));
        }

        return $case;
    }

    private static function toCrop(mixed $value): Crop
    {
        return match (true) {
            $value instanceof Crop => $value,
            \is_string($value) => Crop::fromString($value),
            default => throw new InvalidArgumentException(\sprintf('The "crop" transformation must be a string, got "%s".', get_debug_type($value))),
        };
    }

    private static function toColor(mixed $value): Color
    {
        return match (true) {
            $value instanceof Color => $value,
            \is_string($value) => Color::fromString($value),
            default => throw new InvalidArgumentException(\sprintf('The "bg-color" transformation must be a string, got "%s".', get_debug_type($value))),
        };
    }

    /**
     * @return list<Format>
     */
    private static function toAuto(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('The "auto" transformation must be a list of formats, got "%s".', get_debug_type($value)));
        }

        $formats = [];
        foreach ($value as $format) {
            if ('' === $format) {
                continue;
            }

            $format = self::toEnum(Format::class, $format, 'auto');

            if (!\in_array($format, [Format::Avif, Format::Webp], true)) {
                throw new InvalidArgumentException(\sprintf('The "auto" transformation only negotiates "avif" and "webp", got "%s".', $format->value));
            }

            $formats[$format->value] = $format;
        }

        // AVIF first: it compresses better, and the browser only gets it when it says so
        $preference = [Format::Avif->value => 0, Format::Webp->value => 1];
        uksort($formats, static fn (string $a, string $b) => $preference[$a] <=> $preference[$b]);

        return array_values($formats);
    }
}
