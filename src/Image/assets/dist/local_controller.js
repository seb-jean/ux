import _Class from "./abstract_image_controller.js";
var local_controller_default = class extends _Class {
	buildUrl(src, transformation) {
		if (Object.keys(transformation).length === 0) return src;
		const endpoint = this.hasProviderOptionsValue && this.providerOptionsValue.endpoint ? this.providerOptionsValue.endpoint : "/_image";
		const params = new URLSearchParams({ src });
		if (transformation.width !== void 0) params.set("w", String(transformation.width));
		if (transformation.height !== void 0) params.set("h", String(transformation.height));
		if (transformation.format !== void 0) params.set("format", transformation.format);
		if (transformation.quality !== void 0) params.set("q", String(transformation.quality));
		if (transformation.fit !== void 0) params.set("fit", transformation.fit);
		return `${endpoint}?${params.toString()}`;
	}
};
export { local_controller_default as default };
