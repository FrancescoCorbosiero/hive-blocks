/**
 * Calendario dei drop — editor. The canvas shows the server render: the
 * products with a drop date, by themselves; the sidebar sets the words and
 * how many.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const Empty = () => (
    <Placeholder icon="calendar-alt" label="Calendario dei drop">
        Nessun drop in programma: il blocco si vede quando un prodotto ha una data di drop (Dati prodotto → Generale), oppure scrivi un testo per quando non ce ne sono.
    </Placeholder>
);

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Drop">
                    <p>Elenca da solo i prodotti con una data di drop (Dati prodotto → Generale → Drop), in ordine di uscita.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Sopra il titolo" value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextControl {...field} label="Sottotitolo" value={attributes.subtitle} onChange={set('subtitle')} />
                        <RangeControl {...field} label="Drop mostrati" min={1} max={12} value={attributes.limit} onChange={set('limit')} />
                        <ToggleControl __nextHasNoMarginBottom label="Anche i drop usciti negli ultimi 7 giorni" checked={attributes.showReleased} onChange={set('showReleased')} />
                        <TextControl
                            {...field}
                            label="Testo se non ci sono drop"
                            help="Vuoto: senza drop in programma il blocco non si vede."
                            value={attributes.emptyText}
                            onChange={set('emptyText')}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes EmptyResponsePlaceholder={Empty} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
