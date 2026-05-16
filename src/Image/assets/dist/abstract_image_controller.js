import { Controller } from "@hotwired/stimulus";
var _Class = class extends Controller {
	connect() {
		const transformation = this.hasTransformationValue ? this.transformationValue : {};
		const src = this.buildUrl(this.srcValue, transformation);
		this.dispatchEvent("pre-connect", {
			src,
			transformation
		});
		const element = this.element;
		element.src = src;
		if (this.hasAltValue) element.alt = this.altValue;
		if (this.hasWidthValue) element.width = this.widthValue;
		if (this.hasHeightValue) element.height = this.heightValue;
		if (this.hasLoadingValue) element.setAttribute("loading", this.loadingValue);
		element.addEventListener("load", () => {
			this.dispatchEvent("ready", {
				src,
				element
			});
		}, { once: true });
		element.addEventListener("error", () => {
			this.dispatchEvent("error", {
				src,
				element
			});
		}, { once: true });
		this.dispatchEvent("connect", {
			src,
			element
		});
	}
	dispatchEvent(name, payload) {
		this.dispatch(name, {
			detail: payload,
			prefix: "ux-image"
		});
	}
};
_Class.values = {
	src: String,
	alt: String,
	width: Number,
	height: Number,
	loading: String,
	transformation: Object,
	providerOptions: Object
};
export { _Class as default };
