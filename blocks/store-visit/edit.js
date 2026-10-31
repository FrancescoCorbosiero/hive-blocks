/**
 * Vieni in negozio — editor. The canvas shows the server render; the
 * sidebar edits the texts, WhatsApp, the days, the closures and the map
 * (its code, and a picture of it from the media library for the cover).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } from '@wordpress/block-editor';
import { Button, Flex, PanelBody, TextareaControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const DAY_FIELDS = [
    { key: 'day', label: 'Giorno' },
    { key: 'time', label: 'Orario', help: 'Es. 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso.' },
];

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Contenuti">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Occhiello" value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextareaControl __nextHasNoMarginBottom label="Sottotitolo" value={attributes.subtitle} onChange={set('subtitle')} />
                        <TextControl
                            {...field}
                            label="Indirizzo"
                            help="«Indicazioni» apre Google Maps verso questo indirizzo."
                            value={attributes.address}
                            onChange={set('address')}
                        />
                        <TextControl {...field} label="Partita IVA (facoltativa)" help="Es. P.IVA: 01875650333, sotto l’indirizzo." value={attributes.vat} onChange={set('vat')} />
                        <TextControl {...field} label="Telefono (facoltativo)" type="tel" value={attributes.phone} onChange={set('phone')} />
                        <TextControl {...field} label="Nota (facoltativa)" value={attributes.note} onChange={set('note')} />
                    </div>
                </PanelBody>
                <PanelBody title="WhatsApp" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl __nextHasNoMarginBottom label="Mostra il pulsante WhatsApp" checked={attributes.showWhatsApp} onChange={set('showWhatsApp')} />
                        <TextControl
                            {...field}
                            label="Numero WhatsApp"
                            help="Con il prefisso del paese, es. 393331234567. Vuoto: quello di Hive → Impostazioni."
                            type="tel"
                            value={attributes.whatsapp}
                            onChange={set('whatsapp')}
                        />
                        <TextControl {...field} label="Messaggio già scritto" value={attributes.whatsappText} onChange={set('whatsappText')} />
                    </div>
                </PanelBody>
                <PanelBody title="Orari" initialOpen={false}>
                    <p>Sul sito il blocco dice se il negozio è aperto adesso («Aperto ora · chiude alle 19:30»), anche quando la pagina arriva dalla cache.</p>
                    <ListControl
                        items={attributes.hours}
                        fields={DAY_FIELDS}
                        onChange={set('hours')}
                        itemLabel={(item, index) => item.day || `Giorno ${index + 1}`}
                        newItem={() => ({ day: '', time: '' })}
                        addLabel="Aggiungi un giorno"
                    />
                </PanelBody>
                <PanelBody title="Chiusure" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label="Chiuso nelle festività nazionali"
                            checked={attributes.closedOnHolidays}
                            onChange={set('closedOnHolidays')}
                        />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Altre date di chiusura"
                            help="Una per riga: 24/12/2026 oppure 2026-12-24."
                            value={attributes.closedDates}
                            onChange={set('closedDates')}
                        />
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
                        <div>
                            <p style={{ marginTop: 0 }}>Immagine della mappa (facoltativa): sotto il pulsante, finché la mappa non si carica.</p>
                            <MediaUploadCheck>
                                <Flex justify="flex-start" gap={2}>
                                    <MediaUpload
                                        allowedTypes={['image']}
                                        value={attributes.mapImageId}
                                        onSelect={(media) => setAttributes({ mapImageId: media.id || 0 })}
                                        render={({ open }) => (
                                            <Button variant="secondary" onClick={open}>
                                                {attributes.mapImageId ? 'Cambia l’immagine' : 'Scegli un’immagine'}
                                            </Button>
                                        )}
                                    />
                                    {attributes.mapImageId > 0 && (
                                        <Button variant="tertiary" isDestructive onClick={() => setAttributes({ mapImageId: 0 })}>
                                            Rimuovi
                                        </Button>
                                    )}
                                </Flex>
                            </MediaUploadCheck>
                        </div>
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
