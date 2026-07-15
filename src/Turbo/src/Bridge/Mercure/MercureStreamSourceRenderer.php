<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Turbo\Bridge\Mercure;

use Symfony\Component\Mercure\Twig\MercureExtension;
use Symfony\UX\Turbo\Broadcaster\IdAccessor;
use Symfony\UX\Turbo\StreamSourceRendererInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

/**
 * Renders a Mercure stream source element, delegating authorization to the Mercure Bundle.
 *
 * For private topics, the browser EventSource authenticates with `withCredentials: true`
 * using the Mercure authorization cookie. The cookie is not issued here: private topics are
 * collected by {@see MercureAuthorizationSubscriber}, which sets a single cookie per hub once
 * the response is ready, so that several private stream sources can coexist on the same page.
 *
 * @author Sébastien Jean <sebastien.jean76@gmail.com>
 */
final class MercureStreamSourceRenderer implements StreamSourceRendererInterface
{
    public function __construct(
        private readonly IdAccessor $idAccessor,
        private readonly Environment $twig,
        private readonly string $hubName,
        private readonly MercureAuthorizationSubscriber $authorizationSubscriber,
    ) {
    }

    public function render(string|object|array $topics, array $options = []): string
    {
        $private = $options['private'] ?? false;

        $topicStrings = array_map(
            $this->resolveTopic(...),
            \is_array($topics) ? $topics : [$topics],
        );

        // Mercure >= 0.7: https://github.com/symfony/mercure/pull/123
        /* @phpstan-ignore-next-line function.alreadyNarrowedType */
        $mercure = is_subclass_of(MercureExtension::class, AbstractExtension::class)
            ? $this->twig->getExtension(MercureExtension::class) /* @phpstan-ignore argument.templateType */
            : $this->twig->getRuntime(MercureExtension::class);

        $url = $mercure->mercure($topicStrings, ['hub' => $this->hubName]);

        if ($private) {
            // Defer issuing the authorization cookie so multiple private sources rendered
            // during the same request share a single cookie authorizing all their topics.
            $this->authorizationSubscriber->subscribe($this->hubName, $topicStrings);
        }

        return \sprintf(
            '<turbo-mercure-stream-source src="%s"%s></turbo-mercure-stream-source>',
            htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            $private ? ' private' : '',
        );
    }

    private function resolveTopic(object|string $topic): string
    {
        if (\is_object($topic)) {
            $class = $topic::class;

            if (!$id = $this->idAccessor->getEntityId($topic)) {
                throw new \LogicException(\sprintf('Cannot listen to entity of class "%s" as the PropertyAccess component is not installed. Try running "composer require symfony/property-access".', $class));
            }

            return \sprintf(Broadcaster::TOPIC_PATTERN, rawurlencode($class), rawurlencode(implode('-', $id)));
        }

        if (!preg_match('/[^a-zA-Z0-9_\x7f-\xff\\\\]/', $topic) && class_exists($topic)) {
            return \sprintf(Broadcaster::TOPIC_PATTERN, rawurlencode($topic), '{id}');
        }

        return $topic;
    }
}
