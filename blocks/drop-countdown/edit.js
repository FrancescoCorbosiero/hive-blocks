/**
 * Conto alla rovescia del drop — editor. The canvas shows the server render
 * (the countdown itself ticks only on the site); the sidebar picks the
 * product (by id, as the product rail does) or a date, and the words.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ImageField } from '../../assets/editor/image-field.js';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const Empty = () => (
    <Placeholder icon="clock" label="Conto alla rovescia del drop">
        Niente da contare: scegli un prodotto con una data di drop (Dati prodotto → Generale), oppure scrivi una data e un nome.
    </Placeholder>
);

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const { productId } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title="Drop">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl
                            {...field}
                            type="number"
                            min={0}
                            label="Prodotto (ID)"
                            help="Il blocco ne prende la data del drop, la foto, il prezzo e il link; all’uscita il pulsante diventa «Acquista ora». Vuoto, e senza data qui sotto: il prossimo drop del negozio."
                            value={productId || ''}
                            onChange={(value) => setAttributes({ productId: Math.max(0, parseInt(value, 10) || 0) })}
                        />
                        <TextControl
                            {...field}
                            type="datetime-local"
                            label={productId ? 'Data, se il prodotto non ha un drop' : 'Data e ora dell’uscita'}
                            help="Ora del negozio."
                            value={attributes.releaseDate}
                            onChange={set('releaseDate')}
                        />
                        <TextControl {...field} label="Nome" help={productId ? 'Vuoto: il nome del prodotto.' : undefined} value={attributes.productName} onChange={set('productName')} />
                        <ImageField label="Foto" help={productId ? 'Vuoto: la foto del prodotto.' : undefined} value={attributes.productImageId} onChange={set('productImageId')} />
                    </div>
                </PanelBody>
                <PanelBody title="Testi e link" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Sopra il titolo" value={attributes.eyebrow} onChange={set('eyebrow')} />
                        {productId ? (
                            <p style={{ margin: 0 }}>Con un prodotto i link portano alla sua pagina: «Vedi il prodotto» prima dell’uscita, «Acquista ora» dopo.</p>
                        ) : (
                            <>
                                <TextControl {...field} label="Testo del pulsante" value={attributes.buttonText} onChange={set('buttonText')} />
                                <TextControl {...field} type="url" label="Link del pulsante" value={attributes.buttonUrl} onChange={set('buttonUrl')} />
                            </>
                        )}
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
