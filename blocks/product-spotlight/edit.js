/**
 * Prodotto in evidenza — editor. The canvas shows the server render, so the
 * product, its price and its button are the real ones; the sidebar picks
 * the product (by id, as the product rail does) and the words around it.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ImageField } from '../../assets/editor/image-field.js';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const Empty = () => (
    <Placeholder icon="star-filled" label="Prodotto in evidenza">
        Nessun prodotto da mostrare: scrivi l’ID di un prodotto pubblicato, oppure metti in evidenza un prodotto disponibile (Prodotti → la stella).
    </Placeholder>
);

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Prodotto">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl
                            {...field}
                            type="number"
                            min={0}
                            label="Prodotto (ID)"
                            help="Vuoto: l’ultimo prodotto in evidenza che si può comprare, altrimenti il più recente."
                            value={attributes.productId || ''}
                            onChange={(value) => setAttributes({ productId: Math.max(0, parseInt(value, 10) || 0) })}
                        />
                        <ImageField label="Foto" help="Vuoto: la foto del prodotto." value={attributes.imageUrlId} onChange={set('imageUrlId')} />
                    </div>
                </PanelBody>
                <PanelBody title="Testi" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Sopra il titolo" value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" help="Vuoto: il nome del prodotto." value={attributes.title} onChange={set('title')} />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Descrizione"
                            help="Vuoto: la descrizione breve del prodotto."
                            value={attributes.description}
                            onChange={set('description')}
                        />
                        <TextControl {...field} label="Testo del pulsante" help="Vuoto: «Aggiungi al carrello»." value={attributes.buttonText} onChange={set('buttonText')} />
                    </div>
                </PanelBody>
                <PanelBody title="Dettagli" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Condizione" help="Es. Nuovo, con scatola." value={attributes.condition} onChange={set('condition')} />
                        <TextControl {...field} label="Taglia" help="Es. 42 EU." value={attributes.size} onChange={set('size')} />
                        <TextControl {...field} label="Bollino sulla foto" help="Es. Autenticità verificata." value={attributes.authenticity} onChange={set('authenticity')} />
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
