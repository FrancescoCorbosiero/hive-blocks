/**
 * Sezione con shortcode — editor. The canvas shows the server render, the
 * shortcode's output included; the sidebar edits the heading, the
 * shortcode, the button, the band and whether the site shows the section
 * (the Store Hub's Vetrina switches it too).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

// The values the Store Hub writes too.
const BACKGROUNDS = [
    { label: 'Nessuno: lo sfondo della pagina', value: 'white' },
    { label: 'Fascia chiara', value: 'gray' },
    { label: 'Fascia scura', value: 'black' },
];

/** The server printed nothing: no shortcode yet, or one that prints nothing. */
function Empty() {
    return (
        <Notice status="info" isDismissible={false}>
            Scrivi uno shortcode nel pannello a destra, es. [hive_product_rail category="sneakers"]. Se lo shortcode non mostra nulla, la sezione non
            compare.
        </Notice>
    );
}

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    // The wrapper below already carries the alignment, the spacing and the extra classes; the
    // band is the block's own (backgroundColor isn't WordPress's colour support here), so it's sent.
    const { align, className, style, ...preview } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title="Contenuti">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Occhiello" help="La riga piccola sopra il titolo." value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Shortcode"
                            help="La pagina è in cache: lo shortcode deve mostrare lo stesso a ogni visitatore (niente carrello o account)."
                            value={attributes.shortcode}
                            onChange={set('shortcode')}
                        />
                    </div>
                </PanelBody>
                <PanelBody title="Pulsante" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Testo" help="Es. «Vedi tutti». Vuoto: nessun pulsante." value={attributes.buttonText} onChange={set('buttonText')} />
                        <TextControl {...field} type="url" label="Link" value={attributes.buttonUrl} onChange={set('buttonUrl')} />
                    </div>
                </PanelBody>
                <PanelBody title="Aspetto" initialOpen={false}>
                    <SelectControl {...field} label="Sfondo" value={attributes.backgroundColor} options={BACKGROUNDS} onChange={set('backgroundColor')} />
                </PanelBody>
                <PanelBody title="Visibilità" initialOpen={Boolean(attributes.hidden)}>
                    <ToggleControl
                        __nextHasNoMarginBottom
                        label="Nascondi sul sito"
                        help="La sezione resta qui, con il suo shortcode, ma il sito non la mostra. Si accende e si spegne anche dalla Vetrina dello Store Hub."
                        checked={Boolean(attributes.hidden)}
                        onChange={(value) => setAttributes({ hidden: value ? 'yes' : '' })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                {attributes.hidden && (
                    <Notice status="warning" isDismissible={false}>
                        Sezione nascosta sul sito: si riaccende da «Visibilità» o dalla Vetrina dello Store Hub.
                    </Notice>
                )}
                <ServerSideRender block={name} attributes={{ ...preview, hidden: '' }} EmptyResponsePlaceholder={Empty} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
