<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\Turbo\Tests\Bridge\Mercure;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mercure\EventSubscriber\SetCookieSubscriber;
use Symfony\UX\Turbo\Tests\Fixtures\Book;

final class MercureStreamSourceRendererTest extends KernelTestCase
{
    /**
     * @param array<mixed> $context
     */
    #[DataProvider('provideTestCases')]
    public function testRenderTurboStreamFrom(string $template, array $context, string $expectedResult)
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(\Twig\Environment::class, $twig);

        $this->assertSame($expectedResult, $twig->createTemplate($template)->render($context));
    }

    public function testMultiplePrivateSourcesIssueASingleMergedCookie(): void
    {
        $container = self::getContainer();
        $twig = $container->get('twig');

        // Two private stream sources rendered during the same request: this used to throw
        // because the Mercure Authorization refuses to set its cookie twice for one hub.
        $twig->createTemplate("{{ turbo_stream_from('topic_a', private=true) }}")->render();
        $twig->createTemplate("{{ turbo_stream_from(['topic_b', 'topic_a'], private=true) }}")->render();

        $response = $this->emitResponse($container);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies, 'A single Mercure authorization cookie is issued.');
        self::assertSame('mercureAuthorization', $cookies[0]->getName());

        $subscribed = $this->decodeSubscribeClaim($cookies[0]->getValue());
        sort($subscribed);
        self::assertSame(['topic_a', 'topic_b'], $subscribed, 'The cookie authorizes every private topic of the request.');
    }

    public function testPublicSourceDoesNotIssueACookie(): void
    {
        $container = self::getContainer();
        $container->get('twig')->createTemplate("{{ turbo_stream_from('a_topic') }}")->render();

        self::assertCount(0, $this->emitResponse($container)->headers->getCookies());
    }

    public function testAnAlreadyIssuedCookieIsLeftUntouched(): void
    {
        $container = self::getContainer();
        $container->get('twig')->createTemplate("{{ turbo_stream_from('topic_a', private=true) }}")->render();

        // Simulate an application that manages its own authorization cookie for the hub: it is
        // scoped to the hub URL exactly as the Mercure Authorization would scope it.
        $request = Request::create('http://127.0.0.1/');
        $request->attributes->set('_mercure_authorization_cookies', [
            'default' => Cookie::create('mercureAuthorization', 'application-managed-token', 0, '/.well-known/mercure'),
        ]);

        $cookies = $this->emitResponse($container, $request)->headers->getCookies();

        self::assertCount(1, $cookies, 'The renderer does not add a second cookie.');
        self::assertSame('application-managed-token', $cookies[0]->getValue(), 'The existing cookie is left untouched.');
    }

    private function emitResponse(ContainerInterface $container, ?Request $request = null): Response
    {
        $response = new Response();
        $event = new ResponseEvent(self::$kernel, $request ?? Request::create('http://127.0.0.1/'), HttpKernelInterface::MAIN_REQUEST, $response);

        // Reproduce the response pipeline in priority order: Mercure's SetCookieSubscriber
        // (priority 0) transfers any cookie already set to the response first, then our late
        // subscriber (priority -200) issues the cookie for the collected private topics.
        (new SetCookieSubscriber())->onKernelResponse($event);
        $container->get('turbo.mercure.authorization_subscriber')->onKernelResponse($event);

        return $response;
    }

    /**
     * @return list<string>
     */
    private function decodeSubscribeClaim(string $jwt): array
    {
        [, $payload] = explode('.', $jwt);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true, 512, \JSON_THROW_ON_ERROR);

        return $claims['mercure']['subscribe'] ?? [];
    }

    /**
     * @return iterable<array{0: string, 1: array<mixed>, 2: string}>
     */
    public static function provideTestCases(): iterable
    {
        $book = new Book();
        $book->id = 123;

        yield 'string topic — public' => [
            "{{ turbo_stream_from('a_topic') }}",
            [],
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=a_topic"></turbo-mercure-stream-source>',
        ];

        yield 'string topic — private' => [
            "{{ turbo_stream_from('a_topic', private=true) }}",
            [],
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=a_topic" private></turbo-mercure-stream-source>',
        ];

        yield 'class name — single backslash (Twig drops \\X)' => [
            "{{ turbo_stream_from('Symfony\\UX\\Turbo\\Tests\\Fixtures\\Book') }}",
            [],
            // single-quoted Twig string: \X drops the backslash → 'SymfonyUXTurboTestsFixturesBook'
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=SymfonyUXTurboTestsFixturesBook"></turbo-mercure-stream-source>',
        ];

        yield 'class name — double backslash (correct usage)' => [
            "{{ turbo_stream_from('Symfony\\\\UX\\\\Turbo\\\\Tests\\\\Fixtures\\\\Book') }}",
            [],
            // \\\\ in PHP string → \\ in Twig source → \ in Twig output → 'Symfony\UX\Turbo\Tests\Fixtures\Book' → class_exists → URL pattern
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=https%3A%2F%2Fsymfony.com%2Fux-turbo%2FSymfony%255CUX%255CTurbo%255CTests%255CFixtures%255CBook%2F%7Bid%7D"></turbo-mercure-stream-source>',
        ];

        yield 'entity topic' => [
            '{{ turbo_stream_from(book) }}',
            ['book' => $book],
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=https%3A%2F%2Fsymfony.com%2Fux-turbo%2FSymfony%255CUX%255CTurbo%255CTests%255CFixtures%255CBook%2F123"></turbo-mercure-stream-source>',
        ];

        yield 'array of topics' => [
            "{{ turbo_stream_from(['topic_a', 'topic_b']) }}",
            [],
            '<turbo-mercure-stream-source src="http://127.0.0.1:3000/.well-known/mercure?topic=topic_a&amp;topic=topic_b"></turbo-mercure-stream-source>',
        ];
    }
}
