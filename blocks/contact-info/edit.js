/**
 * Contatti — editor. The canvas shows the server render; the sidebar edits
 * the heading, the details, WhatsApp and the map.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Contenuti">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextControl {...field} label="Sottotitolo" value={attributes.subtitle} onChange={set('subtitle')} />
                        <TextControl {...field} label="Indirizzo" value={attributes.address} onChange={set('address')} />
                        <TextControl {...field} label="Partita IVA" help="Es. P.IVA: 01875650333." value={attributes.vat} onChange={set('vat')} />
                    </div>
                </PanelBody>
                <PanelBody title="Contatti" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Telefono" type="tel" value={attributes.phone} onChange={set('phone')} />
                        <TextControl {...field} label="Email" type="email" value={attributes.email} onChange={set('email')} />
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label="Mostra WhatsApp"
                            checked={attributes.showWhatsApp}
                            onChange={set('showWhatsApp')}
                        />
                        {attributes.showWhatsApp && (
                            <>
                                <TextControl
                                    {...field}
                                    label="Numero WhatsApp"
                                    help="Con il prefisso del paese, es. 393331234567. Vuoto: quello di Hive → Impostazioni."
                                    type="tel"
                                    value={attributes.whatsapp}
                                    onChange={set('whatsapp')}
                                />
                                <TextControl {...field} label="Messaggio già scritto" value={attributes.whatsappText} onChange={set('whatsappText')} />
                            </>
                        )}
                    </div>
                </PanelBody>
                <PanelBody title="Mappa" initialOpen={false}>
                    <p>La mappa si carica solo quando il visitatore la apre: la pagina è più leggera e Google non imposta cookie prima.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Codice di Google Maps"
                            help="In Google Maps: Condividi → Incorpora una mappa → Copia HTML."
                            rows={5}
                            value={attributes.mapEmbed}
                            onChange={set('mapEmbed')}
                        />
                        <TextControl
                            {...field}
                            label="Link a Google Maps (facoltativo)"
                            help="Per «Apri in Google Maps». Vuoto: la ricerca dell’indirizzo."
                            type="url"
                            value={attributes.mapUrl}
                            onChange={set('mapUrl')}
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
