import { connectStreamSource, disconnectStreamSource } from '@hotwired/turbo';

type ConnectionListener = (connected: boolean) => void;

/**
 * Shares a single EventSource per Mercure hub (URL + credentials mode), so that several
 * <turbo-mercure-stream-source> elements subscribing to different topics of the same hub
 * do not each open their own connection.
 */
class MercureHubConnection {
    private es: EventSource | undefined;
    private readonly topicsBySubscriber = new Map<object, string[]>();
    private readonly listeners = new Map<object, ConnectionListener>();
    private reconnectScheduled = false;

    constructor(
        private readonly baseUrl: URL,
        private readonly withCredentials: boolean
    ) {}

    subscribe(subscriber: object, topics: string[], listener: ConnectionListener): void {
        this.topicsBySubscriber.set(subscriber, topics);
        this.listeners.set(subscriber, listener);
        this.scheduleReconnect();
    }

    unsubscribe(subscriber: object): void {
        this.topicsBySubscriber.delete(subscriber);
        this.listeners.delete(subscriber);
        this.scheduleReconnect();
    }

    get isEmpty(): boolean {
        return 0 === this.topicsBySubscriber.size;
    }

    // Reconnecting is deferred to a microtask so that several elements mounted in the same tick
    // (the common case) end up sharing a single EventSource, instead of opening one per element.
    private scheduleReconnect(): void {
        if (this.reconnectScheduled) {
            return;
        }

        this.reconnectScheduled = true;
        queueMicrotask(() => {
            this.reconnectScheduled = false;
            this.reconnect();
        });
    }

    private reconnect(): void {
        this.close();

        if (this.isEmpty) {
            return;
        }

        const url = new URL(this.baseUrl);
        for (const topics of this.topicsBySubscriber.values()) {
            for (const topic of topics) {
                url.searchParams.append('topic', topic);
            }
        }

        const es = new EventSource(url, { withCredentials: this.withCredentials });
        this.es = es;
        connectStreamSource(es);
        es.addEventListener('open', () => this.notify(true));
        es.addEventListener('error', () => this.notify(false));
    }

    private notify(connected: boolean): void {
        for (const listener of this.listeners.values()) {
            listener(connected);
        }
    }

    private close(): void {
        if (this.es) {
            disconnectStreamSource(this.es);
            this.es.close();
            this.es = undefined;
        }
    }
}

const connections = new Map<string, MercureHubConnection>();

function hubKey(url: URL, withCredentials: boolean): string {
    const key = new URL(url);
    key.searchParams.delete('topic');

    return `${withCredentials ? '1' : '0'}|${key.toString()}`;
}

function subscribeToHub(url: URL, withCredentials: boolean, subscriber: object, listener: ConnectionListener): string {
    const key = hubKey(url, withCredentials);

    let connection = connections.get(key);
    if (!connection) {
        const baseUrl = new URL(url);
        baseUrl.searchParams.delete('topic');
        connection = new MercureHubConnection(baseUrl, withCredentials);
        connections.set(key, connection);
    }

    connection.subscribe(subscriber, url.searchParams.getAll('topic'), listener);

    return key;
}

function unsubscribeFromHub(key: string, subscriber: object): void {
    const connection = connections.get(key);
    if (!connection) {
        return;
    }

    connection.unsubscribe(subscriber);
    if (connection.isEmpty) {
        connections.delete(key);
    }
}

class TurboMercureStreamSourceElement extends HTMLElement {
    static observedAttributes = ['src', 'private'];

    private hubKey: string | undefined;

    connectedCallback() {
        const src = this.getAttribute('src');
        if (null === src) {
            throw new Error('The "src" attribute is required on <turbo-mercure-stream-source>.');
        }

        const url = new URL(src, window.location.href);

        this.hubKey = subscribeToHub(url, this.hasAttribute('private'), this, (connected) => {
            if (connected) {
                this.setAttribute('connected', '');
            } else {
                this.removeAttribute('connected');
            }
        });
    }

    disconnectedCallback() {
        if (this.hubKey) {
            unsubscribeFromHub(this.hubKey, this);
            this.hubKey = undefined;
        }
        this.removeAttribute('connected');
    }

    attributeChangedCallback() {
        if (this.hubKey) {
            this.disconnectedCallback();
            this.connectedCallback();
        }
    }
}

if (!customElements.get('turbo-mercure-stream-source')) {
    customElements.define('turbo-mercure-stream-source', TurboMercureStreamSourceElement);
}
