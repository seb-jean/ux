/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import AbstractImageController, { type Transformation } from './abstract_image_controller';

type LocalProviderOptions = {
    endpoint?: string;
};

export default class extends AbstractImageController<LocalProviderOptions> {
    protected buildUrl(src: string, transformation: Transformation): string {
        if (Object.keys(transformation).length === 0) {
            return src;
        }

        const endpoint =
            this.hasProviderOptionsValue && this.providerOptionsValue.endpoint
                ? this.providerOptionsValue.endpoint
                : '/_image';

        const params = new URLSearchParams({ src });
        if (transformation.width !== undefined) params.set('w', String(transformation.width));
        if (transformation.height !== undefined) params.set('h', String(transformation.height));
        if (transformation.format !== undefined) params.set('format', transformation.format);
        if (transformation.quality !== undefined) params.set('q', String(transformation.quality));
        if (transformation.fit !== undefined) params.set('fit', transformation.fit);

        return `${endpoint}?${params.toString()}`;
    }
}
