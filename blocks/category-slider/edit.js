/**
 * Slider categorie — editor. The canvas shows the server render, so the
 * categories and their pictures are the real ones; the sidebar picks the
 * categories (as the product rail does), or the block's own tiles (a brand,
 * a collection: picture, name, link), and the layout.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { TermsField } from '../../assets/editor/terms-field.js';
import { MediaControl } from '../hero/media-control.js';
import { name } from './block.json';

const TILE_FIELDS = [
    { key: 'name', label: 'Nome' },
    { key: 'url', label: 'Link', help: 'La pagina che apre: un marchio, una categoria, una collezione.' },
];
const newTile = () => ({ name: '', url: '', image: '', imageId: 0 });
const tileLabel = (tile, index) => `Riquadro ${index + 1}${tile?.name ? ` · ${tile.name}` : ''}`;

const RATIOS = [
    { label: 'Verticale 4:5', value: '4 / 5' },
    { label: 'Verticale 3:4', value: '3 / 4' },
    { label: 'Alta 2:3', value: '2 / 3' },
    { label: 'Molto alta 9:16', value: '9 / 16' },
    { label: 'Quadrata', value: '1 / 1' },
];

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const Empty = () => (
    <Placeholder icon="category" label="Slider categorie">
        Nessuna categoria da mostrare: scegline qualcuna, oppure aggiungi prodotti alle categorie del negozio.
    </Placeholder>
);

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const tiles = Array.isArray(attributes.items) ? attributes.items : [];
    const update = (index, patch) => setAttributes({ items: tiles.map((tile, i) => (i === index ? { ...tile, ...patch } : tile)) });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Categorie">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        {tiles.length ? (
                            <p>Lo slider mostra i riquadri qui sotto. Toglili tutti per mostrare le categorie.</p>
                        ) : (
                            <TermsField
                                label="Categorie"
                                help="In quest’ordine. Vuoto: le categorie principali con prodotti, nell’ordine del negozio. La foto è quella della categoria (Prodotti → Categorie) o, senza, di un suo prodotto."
                                taxonomy="product_cat"
                                value={attributes.category}
                                onChange={set('category')}
                            />
                        )}
                    </div>
                </PanelBody>
                <PanelBody title="Riquadri" initialOpen={tiles.length > 0}>
                    <p>Al posto delle categorie: un marchio, una collezione, una pagina, ognuno con la sua foto, il nome e il link.</p>
                    <ListControl
                        items={tiles}
                        fields={TILE_FIELDS}
                        onChange={set('items')}
                        itemLabel={tileLabel}
                        newItem={newTile}
                        addLabel="Aggiungi un riquadro"
                    />
                </PanelBody>
                {tiles.length ? (
                    <PanelBody title="Foto dei riquadri" initialOpen={false}>
                        {tiles.map((tile, index) => (
                            <div key={index} style={{ marginBottom: 16, paddingBottom: 16, borderBottom: '1px solid #ddd' }}>
                                <MediaControl
                                    label={tileLabel(tile, index)}
                                    url={tile.image}
                                    id={tile.imageId}
                                    onChange={({ id, url }) => update(index, { image: url, imageId: id })}
                                />
                            </div>
                        ))}
                    </PanelBody>
                ) : null}
                <PanelBody title="Aspetto" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl {...field} label="Proporzione delle foto" value={attributes.imageRatio} options={RATIOS} onChange={set('imageRatio')} />
                        <ToggleControl __nextHasNoMarginBottom label="Frecce su desktop" checked={attributes.showNav} onChange={set('showNav')} />
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
