/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Application } from '@hotwired/stimulus';
import { getByTestId, waitFor } from '@testing-library/dom';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { clearDOM, mountDOM } from '../../../../../test/stimulus-helpers';
import LocalController from '../../src/local_controller';

const startStimulus = () => {
    const application = Application.start();
    application.register('ux-image--local', LocalController);
};

describe('LocalController', () => {
    let container: HTMLElement;

    afterEach(() => {
        clearDOM();
    });

    describe('without transformation', () => {
        beforeEach(() => {
            container = mountDOM(`
                <img
                    data-testid="img"
                    data-controller="ux-image--local"
                    data-ux-image--local-src-value="/images/photo.jpg"
                    data-ux-image--local-alt-value="A photo"
                />
            `);
        });

        it('sets the src directly without transformation endpoint', async () => {
            const img = getByTestId(container, 'img');

            startStimulus();
            await waitFor(() => expect(img).toHaveAttribute('src', '/images/photo.jpg'));
            expect(img).toHaveAttribute('alt', 'A photo');
        });
    });

    describe('with transformation', () => {
        beforeEach(() => {
            container = mountDOM(`
                <img
                    data-testid="img"
                    data-controller="ux-image--local"
                    data-ux-image--local-src-value="/images/photo.jpg"
                    data-ux-image--local-transformation-value='{"width":800,"height":600,"format":"webp","quality":85,"fit":"cover"}'
                />
            `);
        });

        it('builds a transformation URL with query parameters', async () => {
            const img = getByTestId(container, 'img');

            startStimulus();
            await waitFor(() =>
                expect(img).toHaveAttribute(
                    'src',
                    '/_image?src=%2Fimages%2Fphoto.jpg&w=800&h=600&format=webp&q=85&fit=cover'
                )
            );
        });
    });

    describe('with custom endpoint', () => {
        beforeEach(() => {
            container = mountDOM(`
                <img
                    data-testid="img"
                    data-controller="ux-image--local"
                    data-ux-image--local-src-value="/images/photo.jpg"
                    data-ux-image--local-transformation-value='{"width":400}'
                    data-ux-image--local-provider-options-value='{"endpoint":"/resize"}'
                />
            `);
        });

        it('uses the custom endpoint', async () => {
            const img = getByTestId(container, 'img');

            startStimulus();
            await waitFor(() =>
                expect(img).toHaveAttribute('src', '/resize?src=%2Fimages%2Fphoto.jpg&w=400')
            );
        });
    });

    describe('with width, height, loading attributes', () => {
        beforeEach(() => {
            container = mountDOM(`
                <img
                    data-testid="img"
                    data-controller="ux-image--local"
                    data-ux-image--local-src-value="/images/photo.jpg"
                    data-ux-image--local-width-value="800"
                    data-ux-image--local-height-value="600"
                    data-ux-image--local-loading-value="lazy"
                />
            `);
        });

        it('applies width, height and loading attributes', async () => {
            const img = getByTestId(container, 'img');

            startStimulus();
            await waitFor(() => expect(img).toHaveAttribute('src', '/images/photo.jpg'));
            expect(img).toHaveAttribute('width', '800');
            expect(img).toHaveAttribute('height', '600');
            expect(img).toHaveAttribute('loading', 'lazy');
        });
    });
});
