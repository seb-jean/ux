<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\ProcessorNotAvailableException;
use Symfony\UX\Image\Exception\UnsupportedFormatException;
use Symfony\UX\Image\Processor\ImageProcessorInterface;
use Symfony\UX\Image\Processor\ProcessorRegistry;
use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\TransformationPlan;
use Symfony\UX\Image\Transformation\Transformations;

class ProcessorRegistryTest extends TestCase
{
    /**
     * @param list<Format> $formats
     */
    private function createProcessor(bool $available, array $formats): ImageProcessorInterface
    {
        return new class($available, $formats) implements ImageProcessorInterface {
            public function __construct(private bool $available, private array $formats)
            {
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function supports(Format $format): bool
            {
                return \in_array($format, $this->formats, true);
            }

            public function process(ImageSource $source, TransformationPlan $plan, Transformations $transformations, Format $outputFormat): string
            {
                return '';
            }
        };
    }

    public function testAutoPrefersTheFirstAvailableDriver(): void
    {
        $imagick = $this->createProcessor(true, Format::cases());
        $gd = $this->createProcessor(true, Format::cases());

        $registry = new ProcessorRegistry(['imagick' => $imagick, 'gd' => $gd]);

        $this->assertSame($imagick, $registry->get(Format::Jpeg, Format::Webp));
        $this->assertSame(['imagick', 'gd'], $registry->availableDrivers());
    }

    public function testAutoSkipsAMissingDriver(): void
    {
        $imagick = $this->createProcessor(false, Format::cases());
        $gd = $this->createProcessor(true, Format::cases());

        $registry = new ProcessorRegistry(['imagick' => $imagick, 'gd' => $gd]);

        $this->assertSame($gd, $registry->get(Format::Jpeg, Format::Webp));
        $this->assertSame(['gd'], $registry->availableDrivers());
    }

    public function testAutoSkipsADriverThatCannotHandleTheFormat(): void
    {
        $imagick = $this->createProcessor(true, [Format::Jpeg, Format::Png]);
        $gd = $this->createProcessor(true, Format::cases());

        $registry = new ProcessorRegistry(['imagick' => $imagick, 'gd' => $gd]);

        $this->assertSame($imagick, $registry->get(Format::Jpeg, Format::Png));
        $this->assertSame($gd, $registry->get(Format::Jpeg, Format::Avif), 'AVIF is only supported by the second driver');
    }

    public function testAnExplicitDriverIsNotSubstituted(): void
    {
        $imagick = $this->createProcessor(true, [Format::Jpeg]);
        $gd = $this->createProcessor(true, Format::cases());

        $registry = new ProcessorRegistry(['imagick' => $imagick, 'gd' => $gd], 'imagick');

        $this->assertSame($imagick, $registry->get(Format::Jpeg, Format::Jpeg));

        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('No installed image driver can convert "jpeg" to "avif".');

        $registry->get(Format::Jpeg, Format::Avif);
    }

    public function testNoDriverInstalled(): void
    {
        $registry = new ProcessorRegistry(['gd' => $this->createProcessor(false, Format::cases())]);

        $this->expectException(ProcessorNotAvailableException::class);
        $this->expectExceptionMessage('No image driver is installed: install the "gd" or the "imagick" PHP extension.');

        $registry->get(Format::Jpeg, Format::Jpeg);
    }

    public function testTheConfiguredDriverMustExist(): void
    {
        $this->expectException(ProcessorNotAvailableException::class);
        $this->expectExceptionMessage('The image driver "magick" is unknown, expected one of: "imagick", "gd".');

        new ProcessorRegistry([
            'imagick' => $this->createProcessor(true, Format::cases()),
            'gd' => $this->createProcessor(true, Format::cases()),
        ], 'magick');
    }

    public function testSupports(): void
    {
        $registry = new ProcessorRegistry(['gd' => $this->createProcessor(true, [Format::Jpeg, Format::Png])]);

        $this->assertTrue($registry->supports(Format::Jpeg, Format::Png));
        $this->assertFalse($registry->supports(Format::Jpeg, Format::Avif));
    }
}
