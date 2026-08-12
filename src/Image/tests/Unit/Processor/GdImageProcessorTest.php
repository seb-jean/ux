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
use Symfony\UX\Image\Exception\RuntimeException;
use Symfony\UX\Image\Processor\GdImageProcessor;
use Symfony\UX\Image\Processor\ImageProcessorInterface;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\Geometry;
use Symfony\UX\Image\Transformation\Size;
use Symfony\UX\Image\Transformation\Transformations;

#[RequiresPhpExtension('gd')]
class GdImageProcessorTest extends ImageProcessorTestCase
{
    protected function createProcessor(): ImageProcessorInterface
    {
        return new GdImageProcessor();
    }

    public function testItIsAvailable(): void
    {
        $this->assertTrue($this->createProcessor()->isAvailable());
        $this->assertTrue($this->createProcessor()->supports(Format::Png));
    }

    public function testAFileThatIsNotAnImageIsReported(): void
    {
        $source = $this->resolve('broken.png');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GD cannot read the image "broken.png".');

        $this->createProcessor()->process(
            $source,
            Geometry::plan(new Size(10, 10), Transformations::none()),
            Transformations::none(),
            Format::Png,
        );
    }
}
