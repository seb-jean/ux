import { connectStreamSource, disconnectStreamSource } from "@hotwired/turbo";
var MercureHubConnection = class {
	constructor(baseUrl, withCredentials) {
		this.baseUrl = baseUrl;
		this.withCredentials = withCredentials;
		this.topicsBySubscriber = /* @__PURE__ */ new Map();
		this.listeners = /* @__PURE__ */ new Map();
		this.reconnectScheduled = false;
	}
	subscribe(subscriber, topics, listener) {
		this.topicsBySubscriber.set(subscriber, topics);
		this.listeners.set(subscriber, listener);
		this.scheduleReconnect();
	}
	unsubscribe(subscriber) {
		this.topicsBySubscriber.delete(subscriber);
		this.listeners.delete(subscriber);
		this.scheduleReconnect();
	}
	get isEmpty() {
		return 0 === this.topicsBySubscriber.size;
	}
	scheduleReconnect() {
		if (this.reconnectScheduled) return;
		this.reconnectScheduled = true;
		queueMicrotask(() => {
			this.reconnectScheduled = false;
			this.reconnect();
		});
	}
	reconnect() {
		this.close();
		if (this.isEmpty) return;
		const url = new URL(this.baseUrl);
		for (const topics of this.topicsBySubscriber.values()) for (const topic of topics) url.searchParams.append("topic", topic);
		const es = new EventSource(url, { withCredentials: this.withCredentials });
		this.es = es;
		connectStreamSource(es);
		es.addEventListener("open", () => this.notify(true));
		es.addEventListener("error", () => this.notify(false));
	}
	notify(connected) {
		for (const listener of this.listeners.values()) listener(connected);
	}
	close() {
		if (this.es) {
			disconnectStreamSource(this.es);
			this.es.close();
			this.es = void 0;
		}
	}
};
const connections = /* @__PURE__ */ new Map();
function hubKey(url, withCredentials) {
	const key = new URL(url);
	key.searchParams.delete("topic");
	return `${withCredentials ? "1" : "0"}|${key.toString()}`;
}
function subscribeToHub(url, withCredentials, subscriber, listener) {
	const key = hubKey(url, withCredentials);
	let connection = connections.get(key);
	if (!connection) {
		const baseUrl = new URL(url);
		baseUrl.searchParams.delete("topic");
		connection = new MercureHubConnection(baseUrl, withCredentials);
		connections.set(key, connection);
	}
	connection.subscribe(subscriber, url.searchParams.getAll("topic"), listener);
	return key;
}
function unsubscribeFromHub(key, subscriber) {
	const connection = connections.get(key);
	if (!connection) return;
	connection.unsubscribe(subscriber);
	if (connection.isEmpty) connections.delete(key);
}
var TurboMercureStreamSourceElement = class extends HTMLElement {
	connectedCallback() {
		const src = this.getAttribute("src");
		if (null === src) throw new Error("The \"src\" attribute is required on <turbo-mercure-stream-source>.");
		const url = new URL(src, window.location.href);
		this.hubKey = subscribeToHub(url, this.hasAttribute("private"), this, (connected) => {
			if (connected) this.setAttribute("connected", "");
			else this.removeAttribute("connected");
		});
	}
	disconnectedCallback() {
		if (this.hubKey) {
			unsubscribeFromHub(this.hubKey, this);
			this.hubKey = void 0;
		}
		this.removeAttribute("connected");
	}
	attributeChangedCallback() {
		if (this.hubKey) {
			this.disconnectedCallback();
			this.connectedCallback();
		}
	}
};
TurboMercureStreamSourceElement.observedAttributes = ["src", "private"];
if (!customElements.get("turbo-mercure-stream-source")) customElements.define("turbo-mercure-stream-source", TurboMercureStreamSourceElement);
