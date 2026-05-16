/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Controller } from '@hotwired/stimulus';

export type Fit = 'contain' | 'cover' | 'fill' | 'crop';
export type Format = 'webp' | 'jpeg' | 'png' | 'avif';

export type Transformation = {
    width?: number;
    height?: number;
    format?: Format;
    quality?: number;
    fit?: Fit;
};

export default abstract class<ProviderOptions extends Record<string, unknown>> extends Controller<HTMLImageElement> {
    static values = {
        src: String,
        alt: String,
        width: Number,
        height: Number,
        loading: String,
        transformation: Object,
        providerOptions: Object,
    };

    declare srcValue: string;
    declare altValue: string;
    declare widthValue: number;
    declare heightValue: number;
    declare loadingValue: string;
    declare transformationValue: Transformation;
    declare providerOptionsValue: ProviderOptions;

    declare hasSrcValue: boolean;
    declare hasAltValue: boolean;
    declare hasWidthValue: boolean;
    declare hasHeightValue: boolean;
    declare hasLoadingValue: boolean;
    declare hasTransformationValue: boolean;
    declare hasProviderOptionsValue: boolean;

    connect(): void {
        const transformation = this.hasTransformationValue ? this.transformationValue : {};
        const src = this.buildUrl(this.srcValue, transformation);

        this.dispatchEvent('pre-connect', { src, transformation });

        const element = this.element;
        element.src = src;

        if (this.hasAltValue) {
            element.alt = this.altValue;
        }
        if (this.hasWidthValue) {
            element.width = this.widthValue;
        }
        if (this.hasHeightValue) {
            element.height = this.heightValue;
        }
        if (this.hasLoadingValue) {
            element.setAttribute('loading', this.loadingValue);
        }

        element.addEventListener(
            'load',
            () => {
                this.dispatchEvent('ready', { src, element });
            },
            { once: true }
        );

        element.addEventListener(
            'error',
            () => {
                this.dispatchEvent('error', { src, element });
            },
            { once: true }
        );

        this.dispatchEvent('connect', { src, element });
    }

    protected abstract buildUrl(src: string, transformation: Transformation): string;

    protected dispatchEvent(name: string, payload: Record<string, unknown>): void {
        this.dispatch(name, { detail: payload, prefix: 'ux-image' });
    }
}
