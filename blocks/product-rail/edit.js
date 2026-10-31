/**
 * Rail di prodotti — editor. The canvas shows the server render, so the
 * cards are the real ones; the sidebar chooses the products, their order
 * (also set by the Store Hub's Vetrina) and the layout.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { FormTokenField, PanelBody, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const TYPES = [
    { label: 'Nuovi arrivi', value: 'recent' },
    { label: 'In evidenza', value: 'featured' },
    { label: 'Più venduti', value: 'best_selling' },
    { label: 'In saldo', value: 'sale' },
    { label: 'Più votati', value: 'top_rated' },
];

const FALLBACKS = [
    { label: 'Automatico', value: '' },
    { label: 'Ordine del negozio', value: 'menu_order' },
    { label: 'Più recenti', value: 'date' },
    { label: 'Popolarità', value: 'popularity' },
    { label: 'Prezzo crescente', value: 'price' },
    { label: 'Prezzo decrescente', value: 'price-desc' },
    { label: 'Valutazione', value: 'rating' },
];

const RATIOS = [
    { label: 'Quadrata', value: 'square' },
    { label: 'Verticale 3:4', value: 'portrait' },
    { label: 'Verticale 4:5', value: 'tall' },
    { label: 'Orizzontale 4:3', value: 'landscape' },
    { label: 'Panoramica 16:9', value: 'wide' },
];

const list = (value) => (value ? value.split(',').filter(Boolean) : []);

/** Terms of a taxonomy, as slug → name (empty while loading or when the taxonomy isn't in the REST API). */
function useTerms(taxonomy) {
    return useSelect(
        (select) => {
            const terms = select(coreStore).getEntityRecords('taxonomy', taxonomy, { per_page: 100, hide_empty: false, _fields: 'id,slug,name' });
            return Object.fromEntries((terms || []).map((term) => [term.slug, term.name]));
        },
        [taxonomy]
    );
}

/** Slugs of a taxonomy, picked from its terms (several allowed). */
function TermsField({ label, help, taxonomy, value, onChange }) {
    const terms = useTerms(taxonomy);
    return (
        <FormTokenField
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={label}
            value={list(value)}
            suggestions={Object.keys(terms)}
            displayTransform={(slug) => terms[slug] || slug}
            onChange={(tokens) => onChange(tokens.map(String).join(','))}
            __experimentalExpandOnFocus
            __experimentalShowHowTo={false}
            help={help}
        />
    );
}

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

    return (
        <>
            <InspectorControls>
                <PanelBody title="Prodotti">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <SelectControl {...field} label="Quali prodotti" value={attributes.type} options={TYPES} onChange={set('type')} />
                        <TermsField label="Categorie" taxonomy="product_cat" value={attributes.category} onChange={set('category')} />
                        <TermsField label="Marche" taxonomy="product_brand" value={attributes.brand} onChange={set('brand')} help="Si possono scrivere anche gli slug di altre tassonomie delle marche." />
                        <TermsField label="Tag" taxonomy="product_tag" value={attributes.tag} onChange={set('tag')} />
                        <TextControl {...field} label="Solo questi prodotti (ID)" help="Es. 12, 5, 9: in quest’ordine, al posto della scelta sopra." value={attributes.ids} onChange={set('ids')} />
                        <RangeControl {...field} label="Quanti prodotti" min={1} max={48} value={attributes.limit} onChange={set('limit')} />
                    </div>
                </PanelBody>
                <PanelBody title="Ordine" initialOpen={false}>
                    <p>Lo Store Hub (Vetrina) scrive qui i prodotti in testa e quelli esclusi.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="In testa (ID, in ordine)" value={attributes.pin} onChange={set('pin')} />
                        <TextControl {...field} label="Esclusi (ID)" value={attributes.exclude} onChange={set('exclude')} />
                        <SelectControl {...field} label="Poi, in ordine di" value={attributes.fallback} options={FALLBACKS} onChange={set('fallback')} />
                    </div>
                </PanelBody>
                <PanelBody title="Aspetto" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <RangeControl {...field} label="Schede visibili su desktop" min={1} max={8} value={attributes.columns} onChange={set('columns')} />
                        <RangeControl {...field} label="Su tablet (0 = automatico)" min={0} max={8} value={attributes.columns_tablet} onChange={set('columns_tablet')} />
                        <RangeControl {...field} label="Su telefono (0 = la successiva si intravede)" min={0} max={4} value={attributes.columns_mobile} onChange={set('columns_mobile')} />
                        <SelectControl {...field} label="Proporzione delle foto" value={attributes.ratio} options={RATIOS} onChange={set('ratio')} />
                        <SelectControl
                            {...field}
                            label="Foto"
                            value={attributes.fit}
                            options={[
                                { label: 'Intere (consigliato per le foto prodotto)', value: 'contain' },
                                { label: 'Ritagliate per riempire', value: 'cover' },
                            ]}
                            onChange={set('fit')}
                        />
                        <ToggleControl __nextHasNoMarginBottom label="«Vedi tutti»" checked={attributes.show_all} onChange={set('show_all')} />
                        {attributes.show_all && (
                            <TextControl {...field} label="Link di «Vedi tutti»" help="Vuoto: la pagina della categoria o della marca." value={attributes.link} onChange={set('link')} />
                        )}
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
