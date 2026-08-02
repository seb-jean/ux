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

use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * The <twig:ux:source> component, to be used inside a <twig:ux:picture>.
 *
 * It renders one <source> element per requested format, plus a last one without
 * a "type" as the fallback for that media condition.
 *
 * @author Symfony Community
 */
final class SourceComponent
{
    use ImageProps;

    /** The media condition this source applies to, e.g. "(min-width: 1024px)". */
    public ?string $media = null;

    /** @var list<RenderedSource>|null */
    private ?array $rendered = null;

    /**
     * @param array<string, array<string, mixed>> $presets
     */
    public function __construct(
        private readonly ImageRenderer $renderer,
        private readonly array $presets = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        return $this->mountImageProps($data, $this->presets);
    }

    /**
     * @return list<RenderedSource>
     */
    public function getRendered(): array
    {
        return $this->rendered ??= $this->renderer->renderSource($this);
    }
}
