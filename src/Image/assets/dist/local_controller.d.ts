import export_default$1, { Transformation } from "./abstract_image_controller.js";
type LocalProviderOptions = {
  endpoint?: string;
};
declare class export_default extends export_default$1<LocalProviderOptions> {
  protected buildUrl(src: string, transformation: Transformation): string;
}
export { export_default as default };