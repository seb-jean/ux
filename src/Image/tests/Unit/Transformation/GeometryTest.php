<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Tests\Unit\Transformation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformation\Geometry;
use Symfony\UX\Image\Transformation\Size;
use Symfony\UX\Image\Transformation\Transformations;

class GeometryTest extends TestCase
{
    /**
     * @param array<string, mixed> $transformations
     * @param array{int, int}      $expected
     */
    #[DataProvider('provideOutputSizes')]
    public function testOutputSize(array $source, array $transformations, array $expected): void
    {
        $plan = Geometry::plan(new Size(...$source), Transformations::fromArray($transformations));

        $this->assertSame($expected, [$plan->output->width, $plan->output->height]);
    }

    public static function provideOutputSizes(): iterable
    {
        yield 'no transformation keeps the source size' => [[800, 600], [], [800, 600]];

        yield 'width only scales proportionally' => [[800, 600], ['width' => 400], [400, 300]];
        yield 'height only scales proportionally' => [[800, 600], ['height' => 300], [400, 300]];
        yield 'width only upscales' => [[400, 300], ['width' => 800], [800, 600]];

        // "fit" is only meaningful when both dimensions are given, like Fastly
        yield 'bounds is the default and fits inside' => [[800, 600], ['width' => 400, 'height' => 400], [400, 300]];
        yield 'bounds fits inside a tall box' => [[800, 600], ['width' => 400, 'height' => 400, 'fit' => 'bounds'], [400, 300]];
        yield 'cover overflows one dimension' => [[800, 600], ['width' => 400, 'height' => 400, 'fit' => 'cover'], [533, 400]];
        yield 'crop matches the box exactly' => [[800, 600], ['width' => 400, 'height' => 400, 'fit' => 'crop'], [400, 400]];
        yield 'fit is ignored with a single dimension' => [[800, 600], ['width' => 400, 'fit' => 'crop'], [400, 300]];

        yield 'dpr multiplies the output' => [[800, 600], ['width' => 400, 'dpr' => 2], [800, 600]];
        yield 'dpr alone scales the source' => [[800, 600], ['dpr' => 2], [1600, 1200]];
        yield 'dpr applies to a crop fit' => [[800, 600], ['width' => 200, 'height' => 200, 'fit' => 'crop', 'dpr' => 3], [600, 600]];

        yield 'crop ratio on a landscape source' => [[800, 600], ['crop' => '1:1'], [600, 600]];
        yield 'crop ratio on a portrait source' => [[600, 800], ['crop' => '1:1'], [600, 600]];
        yield 'crop ratio wider than the source' => [[800, 600], ['crop' => '16:9'], [800, 450]];
        yield 'crop pixels' => [[800, 600], ['crop' => '300,200'], [300, 200]];
        yield 'crop then resize' => [[800, 600], ['crop' => '1:1', 'width' => 100], [100, 100]];

        yield 'orientation swaps the axes' => [[800, 600], ['orient' => 'r'], [600, 800]];
        yield 'orientation swaps before resizing' => [[800, 600], ['orient' => '6', 'width' => 300], [300, 400]];
        yield 'orientation without a rotation keeps the size' => [[800, 600], ['orient' => 'h'], [800, 600]];
    }

    public function testDisplaySizeIgnoresDpr(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['width' => 400, 'dpr' => 2]));

        $this->assertSame([800, 600], [$plan->output->width, $plan->output->height]);
        $this->assertSame([400, 300], [$plan->displaySize->width, $plan->displaySize->height]);
    }

    public function testCropIsCenteredByDefault(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '400,300']));

        $this->assertNotNull($plan->crop);
        $this->assertSame([200, 150, 400, 300], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testCropAcceptsAbsoluteCoordinates(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '300,200,x100,y50']));

        $this->assertSame([100, 50, 300, 200], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testCropAcceptsPercentageCoordinates(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '300,200,x25p,y50p']));

        $this->assertSame([200, 300, 300, 200], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testCropOffsetsPositionWithinTheLeftoverSpace(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '1:1,offset-x100,offset-y0']));

        // a 600x600 region, pushed fully to the right
        $this->assertSame([200, 0, 600, 600], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testCropCoordinatesAreClampedToTheSource(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '300,200,x700,y500']));

        $this->assertSame([500, 400, 300, 200], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testCropLargerThanTheSourceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The crop 1000x200 does not fit in the 800x600 source image');

        Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '1000,200']));
    }

    public function testSafeShrinksAnOversizedCrop(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '1000,200,safe']));

        $this->assertSame([0, 200, 800, 200], [$plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height]);
    }

    public function testAFullSizeCropIsDroppedFromThePlan(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['crop' => '800,600']));

        $this->assertNull($plan->crop);
        $this->assertTrue($plan->isIdentity());
    }

    public function testIdentityPlan(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['format' => 'webp', 'quality' => 60]));

        $this->assertTrue($plan->isIdentity());
    }

    public function testResizingIsNotAnIdentityPlan(): void
    {
        $plan = Geometry::plan(new Size(800, 600), Transformations::fromArray(['width' => 400]));

        $this->assertFalse($plan->isIdentity());
    }
}
