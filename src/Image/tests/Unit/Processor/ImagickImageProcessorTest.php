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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\UX\Image\Processor\ImageProcessorInterface;
use Symfony\UX\Image\Processor\ImagickImageProcessor;
use Symfony\UX\Image\Transformation\Format;

#[RequiresPhpExtension('imagick')]
class ImagickImageProcessorTest extends ImageProcessorTestCase
{
    protected function createProcessor(): ImageProcessorInterface
    {
        return new ImagickImageProcessor();
    }

    public function testItIsAvailable(): void
    {
        $this->assertTrue($this->createProcessor()->isAvailable());
        $this->assertTrue($this->createProcessor()->supports(Format::Png));
    }
}
