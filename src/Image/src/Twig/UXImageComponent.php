<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Twig;

use Symfony\UX\Image\Exception\InvalidArgumentException;
use Symfony\UX\Image\Transformation\Transformations;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Backs <twig:UX:Image src="..." width="800" alt="..." class="hero" />.
 *
 * Transformations and HTML attributes are written side by side, so they are told
 * apart here: anything Fastly names is a transformation, everything else ends up
 * on the <img> tag.
 *
 * @internal
 */
final class UXImageComponent
{
    public string $src;

    /** @var array<string, mixed>|string|null */
    public array|string|null $transformations = null;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        $transformations = [];

        foreach (Transformations::PARAMETERS as $parameter) {
            foreach ([$parameter, str_replace('-', '_', $parameter)] as $key) {
                if (\array_key_exists($key, $data)) {
                    $transformations[$parameter] = $data[$key];
                    unset($data[$key]);
                }
            }
        }

        if (isset($data['preset'])) {
            if ([] !== $transformations) {
                throw new InvalidArgumentException(\sprintf('The "preset" attribute of <twig:UX:Image> cannot be combined with the "%s" transformation.', array_key_first($transformations)));
            }

            $data['transformations'] = $data['preset'];
            unset($data['preset']);
        } elseif ([] !== $transformations) {
            $data['transformations'] = $transformations;
        }

        return $data;
    }
}
