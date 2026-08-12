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

use Symfony\UX\Image\Source\ImageSource;
use Symfony\UX\Image\Transformation\Format;
use Symfony\UX\Image\Transformation\TransformationPlan;
use Symfony\UX\Image\Transformation\Transformations;

/**
 * Executes a transformation plan with a given imaging library.
 *
 * Implementations never interpret transformations: the geometry is already
 * resolved in the plan, so every driver produces the same image.
 */
interface ImageProcessorInterface
{
    /**
     * Whether the underlying extension is installed.
     */
    public function isAvailable(): bool;

    /**
     * Whether this driver can both read and write the given format.
     */
    public function supports(Format $format): bool;

    /**
     * @return string the encoded image
     */
    public function process(ImageSource $source, TransformationPlan $plan, Transformations $transformations, Format $outputFormat): string;
}
