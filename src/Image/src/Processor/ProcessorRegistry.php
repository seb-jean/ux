<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Processor;

use Symfony\UX\Image\Exception\ProcessorNotAvailableException;
use Symfony\UX\Image\Exception\UnsupportedFormatException;
use Symfony\UX\Image\Transformation\Format;

/**
 * Picks the driver that transforms a given image.
 */
final class ProcessorRegistry
{
    public const DRIVER_AUTO = 'auto';

    /**
     * @param array<string, ImageProcessorInterface> $processors keyed by driver name, in order of preference
     */
    public function __construct(
        private readonly array $processors,
        private readonly string $driver = self::DRIVER_AUTO,
    ) {
        if (self::DRIVER_AUTO !== $this->driver && !isset($this->processors[$this->driver])) {
            throw new ProcessorNotAvailableException(\sprintf('The image driver "%s" is unknown, expected one of: "%s".', $this->driver, implode('", "', array_keys($this->processors))));
        }
    }

    /**
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        $available = [];

        foreach ($this->candidates() as $name => $processor) {
            if ($processor->isAvailable()) {
                $available[] = $name;
            }
        }

        return $available;
    }

    public function supports(Format $sourceFormat, Format $outputFormat): bool
    {
        foreach ($this->candidates() as $processor) {
            if ($processor->isAvailable() && $processor->supports($sourceFormat) && $processor->supports($outputFormat)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ProcessorNotAvailableException when no driver is installed at all
     * @throws UnsupportedFormatException     when no installed driver handles both formats
     */
    public function get(Format $sourceFormat, Format $outputFormat): ImageProcessorInterface
    {
        $installed = false;

        foreach ($this->candidates() as $processor) {
            if (!$processor->isAvailable()) {
                continue;
            }

            $installed = true;

            if ($processor->supports($sourceFormat) && $processor->supports($outputFormat)) {
                return $processor;
            }
        }

        if (!$installed) {
            throw new ProcessorNotAvailableException(\sprintf('No image driver is installed: install the "gd" or the "imagick" PHP extension%s.', self::DRIVER_AUTO === $this->driver ? '' : \sprintf(' (the "%s" driver is configured)', $this->driver)));
        }

        throw new UnsupportedFormatException(\sprintf('No installed image driver can convert "%s" to "%s".', $sourceFormat->value, $outputFormat->value));
    }

    /**
     * @return array<string, ImageProcessorInterface>
     */
    private function candidates(): array
    {
        return self::DRIVER_AUTO === $this->driver ? $this->processors : [$this->driver => $this->processors[$this->driver]];
    }
}
