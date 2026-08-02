<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Image\Responsive;

use Symfony\UX\Image\Exception\InvalidArgumentException;

/**
 * Expands the breakpoint syntax of the "sizes" prop ("100vw md:50vw xl:400px")
 * into a real "sizes" attribute, and derives the candidate widths it implies.
 *
 * @author Symfony Community
 */
final class SizesResolver
{
    /**
     * @param array<string, int> $screens breakpoint name => min viewport width, in pixels
     */
    public function __construct(
        private readonly array $screens = [],
    ) {
    }

    /**
     * The "auto" keyword lets the browser use the concrete layout size. It is only
     * valid on a lazily loaded image, which the caller is responsible for checking.
     */
    public const AUTO = 'auto';

    public function resolve(?string $sizes): ResolvedSizes
    {
        if (null === $sizes || '' === trim($sizes)) {
            return new ResolvedSizes(null, []);
        }

        $tokens = $this->tokenize($sizes);

        // "auto" may stand alone, or prefix a fallback list ("auto 100vw md:50vw")
        // for browsers that don't support the keyword. The fallback is rendered
        // after "auto," per spec, and still feeds the width derivation below: a
        // lazy image's concrete size is only known once the layout is done, so
        // the fallback is what a supporting browser also uses to pick a candidate
        // for the initial, pre-layout fetch.
        $usesAuto = false;
        if ($tokens && self::AUTO === $tokens[0]) {
            $usesAuto = true;
            array_shift($tokens);
        }

        if (!$tokens) {
            return new ResolvedSizes($usesAuto ? self::AUTO : null, []);
        }

        $conditional = [];
        $fallback = null;
        $widths = [];

        foreach ($tokens as $entry) {
            [$screen, $size] = $this->split($entry);

            if (null === $screen) {
                // A bare size applies unconditionally; the last one wins.
                $fallback = $size;
                continue;
            }

            if (!isset($this->screens[$screen])) {
                throw new InvalidArgumentException(\sprintf('Unknown screen "%s" in the "sizes" prop. Configured screens: "%s".', $screen, implode('", "', array_keys($this->screens))));
            }

            $conditional[$screen] = $size;
        }

        // The first matching media condition wins, so the widest breakpoints
        // must be rendered first.
        uksort($conditional, fn (string $a, string $b): int => $this->screens[$b] <=> $this->screens[$a]);

        $rendered = [];
        foreach ($conditional as $screen => $size) {
            $rendered[] = \sprintf('(min-width: %dpx) %s', $this->screens[$screen], $size);
            if (null !== $width = $this->widthFor($size, $this->screens[$screen])) {
                $widths[] = $width;
            }
        }

        if (null !== $fallback) {
            $rendered[] = $fallback;
            // A bare size carries no layout information on its own, so widths are
            // only derived when at least one breakpoint was given. Below the
            // smallest breakpoint the fallback applies: size it against the
            // narrowest configured screen.
            $smallest = $this->screens ? min($this->screens) : null;
            if ($conditional && null !== $smallest && null !== $width = $this->widthFor($fallback, $smallest)) {
                $widths[] = $width;
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        $sizes = implode(', ', $rendered);

        return new ResolvedSizes($usesAuto ? self::AUTO.', '.$sizes : $sizes, $widths);
    }

    /**
     * Splits on whitespace, but only outside parentheses so that a size such as
     * "calc(50vw - 2rem)" stays a single entry.
     *
     * @return list<string>
     */
    private function tokenize(string $sizes): array
    {
        $entries = [];
        $current = '';
        $depth = 0;

        foreach (str_split(trim($sizes)) as $char) {
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                $depth = max(0, $depth - 1);
            }

            if (0 === $depth && preg_match('/\s/', $char)) {
                if ('' !== $current) {
                    $entries[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ('' !== $current) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * @return array{0: string|null, 1: string} the screen name (or null) and the size
     */
    private function split(string $entry): array
    {
        if (!str_contains($entry, ':')) {
            return [null, $entry];
        }

        [$screen, $size] = explode(':', $entry, 2);

        return [$screen, $size];
    }

    /**
     * Converts a CSS size into the pixel width it represents at a given viewport.
     */
    private function widthFor(string $size, int $viewport): ?int
    {
        if (preg_match('/^(\d+(?:\.\d+)?)vw$/', $size, $matches)) {
            return (int) round($viewport * (float) $matches[1] / 100);
        }

        if (preg_match('/^(\d+)px$/', $size, $matches)) {
            return (int) $matches[1];
        }

        // Anything else (calc(), rem, %...) cannot be resolved to a width here.
        return null;
    }
}
