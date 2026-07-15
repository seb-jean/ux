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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Exception\ExceptionInterface as MercureException;

/**
 * Collects the private topics subscribed to during a request and issues a single
 * Mercure authorization cookie per hub once the response is ready.
 *
 * Setting the cookie eagerly while rendering each `<turbo-mercure-stream-source>`
 * would issue one cookie per call, and the Mercure Authorization refuses to set the
 * cookie more than once per hub during the same request. Deferring and merging the
 * topics allows several private stream sources to coexist on the same page and lets
 * the cookie authorize all of them at once.
 *
 * This subscriber runs late, after Mercure's own SetCookieSubscriber and after any
 * application listener, so an authorization cookie the application issues for the hub
 * (for instance to manage its own list of authorized topics) takes precedence and is
 * left untouched.
 *
 * @author Sébastien Jean <sebastien.jean76@gmail.com>
 *
 * @internal
 */
final class MercureAuthorizationSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, array<string, true>> a set of topics indexed by hub name
     */
    private array $topicsByHub = [];

    public function __construct(
        private readonly Authorization $authorization,
    ) {
    }

    /**
     * @param list<string> $topics
     */
    public function subscribe(string $hub, array $topics): void
    {
        foreach ($topics as $topic) {
            $this->topicsByHub[$hub][$topic] = true;
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->topicsByHub) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        foreach ($this->topicsByHub as $hub => $topics) {
            try {
                // setCookie() mints the cookie and stores it in the request attributes; we
                // move it onto the response ourselves below.
                $this->authorization->setCookie($request, array_keys($topics), [], [], $hub);
            } catch (MercureException) {
                // The cookie cannot be created (already set during this request, hub on a
                // different domain, no token factory, ...): leave the existing setup as-is.
                continue;
            }

            $cookies = $request->attributes->get('_mercure_authorization_cookies', []);
            $cookie = $cookies[$hub] ?? null;
            unset($cookies[$hub]);
            $request->attributes->set('_mercure_authorization_cookies', $cookies);

            // Skip when the authorization cookie for this hub is already on the response,
            // typically because the application manages it itself.
            if ($cookie instanceof Cookie && !$this->responseAlreadyHasCookie($response, $cookie)) {
                $response->headers->setCookie($cookie);
            }
        }

        $this->topicsByHub = [];
    }

    private function responseAlreadyHasCookie(Response $response, Cookie $cookie): bool
    {
        foreach ($response->headers->getCookies() as $existing) {
            if ($existing->getName() === $cookie->getName()
                && $existing->getPath() === $cookie->getPath()
                && $existing->getDomain() === $cookie->getDomain()
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getSubscribedEvents(): array
    {
        // Runs after Mercure's SetCookieSubscriber (priority 0), which moves the cookie from
        // the request attributes to the response, and after application listeners, so a cookie
        // the application already issued for the hub is preserved.
        return [KernelEvents::RESPONSE => ['onKernelResponse', -200]];
    }
}
