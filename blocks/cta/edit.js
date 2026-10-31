/**
 * Invito all'azione — editor. The canvas shows the server render
 * (render.php); the sidebar edits the layout, the texts, the button and the
 * picture. The inserter offers the two layouts as variations (block.json):
 * Banner and Immagine e testo.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { MediaControl } from '../hero/media-control.js';
import { name } from './block.json';

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };
    const banner = attributes.layout !== 'split';

    return (
        <>
            <InspectorControls>
                <PanelBody title="Testi">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl
                            {...field}
                            label="Disposizione"
                            value={attributes.layout}
                            options={[
                                { label: 'Banner a tutta larghezza', value: 'banner' },
                                { label: 'Immagine e testo', value: 'split' },
                            ]}
                            onChange={set('layout')}
                        />
                        <TextControl {...field} label="Occhiello" help="Il testo piccolo sopra il titolo." value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextareaControl __nextHasNoMarginBottom label="Testo" value={attributes.text} onChange={set('text')} />
                    </div>
                </PanelBody>
                <PanelBody title="Pulsante" initialOpen={false}>
                    <p>Il pulsante compare quando ha sia il testo sia il link.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Testo del pulsante" value={attributes.buttonText} onChange={set('buttonText')} />
                        <TextControl {...field} label="Link del pulsante" value={attributes.buttonUrl} onChange={set('buttonUrl')} />
                    </div>
                </PanelBody>
                <PanelBody title="Immagine e aspetto" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <MediaControl
                            label={banner ? 'Immagine di sfondo (facoltativa)' : 'Immagine'}
                            url={attributes.imageUrl}
                            id={attributes.imageId}
                            onChange={({ id, url }) => setAttributes({ imageId: id, imageUrl: url })}
                        />
                        {banner ? (
                            <ToggleControl __nextHasNoMarginBottom label="Linea luminosa in alto" checked={attributes.showGlow} onChange={set('showGlow')} />
                        ) : (
                            <ToggleControl __nextHasNoMarginBottom label="Immagine a destra" checked={attributes.reverse} onChange={set('reverse')} />
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
