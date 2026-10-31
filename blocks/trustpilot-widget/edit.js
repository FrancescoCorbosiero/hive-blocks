/**
 * Trustpilot — editor. The canvas shows the server render; the sidebar
 * takes the rating, the number of reviews, the label and the link to the
 * store's Trustpilot page. Nothing is fetched from Trustpilot: the numbers
 * are the ones written here.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Valutazione">
                    <p>Copia i numeri dalla pagina del negozio su Trustpilot: il blocco non carica nulla da Trustpilot.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <RangeControl {...field} label="Valutazione (su 5)" min={1} max={5} step={0.1} value={attributes.rating} onChange={set('rating')} />
                        <TextControl
                            {...field}
                            label="Numero di recensioni"
                            type="number"
                            min={0}
                            value={attributes.reviewCount}
                            onChange={(value) => setAttributes({ reviewCount: Math.max(0, parseInt(value, 10) || 0) })}
                        />
                        <TextControl {...field} label="Etichetta" help="Es. Eccezionale, Ottimo." value={attributes.label} onChange={set('label')} />
                        <TextControl
                            {...field}
                            label="Pagina su Trustpilot"
                            help="Es. https://it.trustpilot.com/review/resellpiacenza.it"
                            type="url"
                            value={attributes.trustpilotUrl}
                            onChange={set('trustpilotUrl')}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
