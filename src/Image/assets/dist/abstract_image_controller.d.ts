import { Controller } from "@hotwired/stimulus";
type Fit = 'contain' | 'cover' | 'fill' | 'crop';
type Format = 'webp' | 'jpeg' | 'png' | 'avif';
type Transformation = {
  width?: number;
  height?: number;
  format?: Format;
  quality?: number;
  fit?: Fit;
};
declare abstract class export_default<ProviderOptions extends Record<string, unknown>> extends Controller<HTMLImageElement> {
  static values: {
    src: StringConstructor;
    alt: StringConstructor;
    width: NumberConstructor;
    height: NumberConstructor;
    loading: StringConstructor;
    transformation: ObjectConstructor;
    providerOptions: ObjectConstructor;
  };
  srcValue: string;
  altValue: string;
  widthValue: number;
  heightValue: number;
  loadingValue: string;
  transformationValue: Transformation;
  providerOptionsValue: ProviderOptions;
  hasSrcValue: boolean;
  hasAltValue: boolean;
  hasWidthValue: boolean;
  hasHeightValue: boolean;
  hasLoadingValue: boolean;
  hasTransformationValue: boolean;
  hasProviderOptionsValue: boolean;
  connect(): void;
  protected abstract buildUrl(src: string, transformation: Transformation): string;
  protected dispatchEvent(name: string, payload: Record<string, unknown>): void;
}
export { Fit, Format, Transformation, export_default as default };