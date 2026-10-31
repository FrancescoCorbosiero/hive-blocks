/**
 * Popup promozionale — editor. The canvas shows the server render, which in
 * the editor draws the offer in place; on the site it opens in a dialog. The
 * sidebar writes the offer and says when it opens.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ImageField } from '../../assets/editor/image-field.js';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    // Blocks saved by golden-hive-blocks' editor may hold seconds (under 100) instead of milliseconds.
    const seconds = attributes.showDelay > 0 && attributes.showDelay < 100 ? attributes.showDelay : Math.round((attributes.showDelay || 0) / 1000);

    return (
        <>
            <InspectorControls>
                <PanelBody title="Offerta">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ImageField label="Immagine" help="Facoltativa: accanto al testo su desktop, sopra sui telefoni." value={attributes.imageUrlId} onChange={set('imageUrlId')} />
                        <TextControl {...field} label="Etichetta" value={attributes.badge} onChange={set('badge')} />
                        <TextControl {...field} label="Titolo" help="Senza titolo il popup non c’è." value={attributes.title} onChange={set('title')} />
                        <TextareaControl __nextHasNoMarginBottom label="Testo" value={attributes.text} onChange={set('text')} />
                        <TextControl {...field} label="Codice sconto" help="Con il pulsante «Copia»." value={attributes.couponCode} onChange={set('couponCode')} />
                        <TextControl {...field} label="Testo del pulsante" value={attributes.buttonText} onChange={set('buttonText')} />
                        <TextControl {...field} type="url" label="Link del pulsante" help="Senza link il pulsante non c’è." value={attributes.buttonUrl} onChange={set('buttonUrl')} />
                        <TextControl {...field} label="Note in piccolo" value={attributes.disclaimer} onChange={set('disclaimer')} />
                    </div>
                </PanelBody>
                <PanelBody title="Quando si apre" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl __nextHasNoMarginBottom label="Da solo" help="Mai nel carrello e al checkout." checked={attributes.autoShow} onChange={set('autoShow')} />
                        {attributes.autoShow && (
                            <>
                                <SelectControl
                                    {...field}
                                    label="Quando"
                                    value={attributes.trigger}
                                    options={[
                                        { label: 'Dopo qualche secondo', value: 'delay' },
                                        { label: 'Quando il visitatore sta per andarsene', value: 'exit' },
                                    ]}
                                    help={
                                        attributes.trigger === 'exit'
                                            ? 'Il mouse esce dalla pagina verso le schede o la barra degli indirizzi, non prima dei secondi qui sotto. Sui telefoni, dopo quei secondi.'
                                            : undefined
                                    }
                                    onChange={set('trigger')}
                                />
                                <RangeControl {...field} label="Secondi" min={0} max={60} value={seconds} onChange={(value) => setAttributes({ showDelay: (value || 0) * 1000 })} />
                                <ToggleControl
                                    __nextHasNoMarginBottom
                                    label="Una volta sola per visitatore"
                                    help={attributes.showOnce ? 'Chi l’ha visto non lo rivede (cambia l’ID per mostrarlo di nuovo a tutti).' : 'Una volta per visita.'}
                                    checked={attributes.showOnce}
                                    onChange={set('showOnce')}
                                />
                            </>
                        )}
                        <TextControl
                            {...field}
                            label="ID"
                            help={`Un link a #${attributes.modalId || 'promo-popup'} apre il popup in qualsiasi momento.`}
                            value={attributes.modalId}
                            onChange={set('modalId')}
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
