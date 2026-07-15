/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { clearDOM, mountDOM } from '../../../../../test/stimulus-helpers';
import '../../src/mercure_stream_source_element';

class MockEventSource extends EventTarget {
    static instances: MockEventSource[] = [];

    readonly url: string;
    readonly withCredentials: boolean;
    readyState = 0;

    constructor(url: string | URL, options?: EventSourceInit) {
        super();
        this.url = url.toString();
        this.withCredentials = options?.withCredentials ?? false;
        MockEventSource.instances.push(this);
    }

    close() {
        this.readyState = 2;
    }

    open() {
        this.readyState = 1;
        this.dispatchEvent(new Event('open'));
    }
}

// The custom element defers opening the shared EventSource to a microtask so that
// elements mounted in the same tick end up sharing a single connection: flush it before asserting.
const flushMicrotasks = () => new Promise((resolve) => queueMicrotask(resolve));

describe('<turbo-mercure-stream-source>', () => {
    beforeEach(() => {
        MockEventSource.instances = [];
        // @ts-expect-error partial EventSource mock, sufficient for this suite
        global.EventSource = MockEventSource;
    });

    afterEach(() => {
        clearDOM();
    });

    it('shares a single EventSource between two elements subscribing to the same hub', async () => {
        mountDOM(
            '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=foo"></turbo-mercure-stream-source>' +
                '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=bar"></turbo-mercure-stream-source>'
        );
        await flushMicrotasks();

        expect(MockEventSource.instances).toHaveLength(1);

        const url = new URL(MockEventSource.instances[0].url);
        expect(url.searchParams.getAll('topic').sort()).toEqual(['bar', 'foo']);
    });

    it('opens independent EventSources for different hubs', async () => {
        mountDOM(
            '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=foo"></turbo-mercure-stream-source>' +
                '<turbo-mercure-stream-source src="https://other.example.com/.well-known/mercure?topic=bar"></turbo-mercure-stream-source>'
        );
        await flushMicrotasks();

        expect(MockEventSource.instances).toHaveLength(2);
    });

    it('propagates the "connected" attribute to every element sharing the hub', async () => {
        const container = mountDOM(
            '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=foo"></turbo-mercure-stream-source>' +
                '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=bar"></turbo-mercure-stream-source>'
        );
        await flushMicrotasks();

        MockEventSource.instances[0].open();

        const elements = container.querySelectorAll('turbo-mercure-stream-source');
        expect(elements).toHaveLength(2);
        elements.forEach((element) => expect(element).toHaveAttribute('connected', ''));
    });

    it('reconnects with the remaining topics and eventually closes when elements disconnect', async () => {
        const container = mountDOM(
            '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=foo"></turbo-mercure-stream-source>' +
                '<turbo-mercure-stream-source src="https://example.com/.well-known/mercure?topic=bar"></turbo-mercure-stream-source>'
        );
        await flushMicrotasks();

        expect(MockEventSource.instances).toHaveLength(1);
        const first = MockEventSource.instances[0];

        // Remove one of the two elements: the shared connection must be reopened with only the remaining topic.
        container.querySelector('turbo-mercure-stream-source')?.remove();
        await flushMicrotasks();

        expect(first.readyState).toBe(2);
        expect(MockEventSource.instances).toHaveLength(2);

        const second = MockEventSource.instances[1];
        const url = new URL(second.url);
        expect(url.searchParams.getAll('topic')).toEqual(['bar']);

        // Remove the last element: the connection must be closed and no new one opened.
        clearDOM();
        await flushMicrotasks();

        expect(second.readyState).toBe(2);
        expect(MockEventSource.instances).toHaveLength(2);
    });
});
