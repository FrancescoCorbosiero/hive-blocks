/**
 * Mappa — editor. The canvas shows the server render: the map's cover, as
 * the visitor sees it before asking for the map. The sidebar takes the
 * code Google Maps gives, the address, the height and the corners.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, RangeControl, TextareaControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

/** No usable map in the code: what to paste, and where. */
function NoMap() {
    return (
        <Placeholder
            icon="location"
            label="Mappa"
            instructions="Incolla nel pannello laterale il codice di Google Maps: Condividi → Incorpora una mappa → Copia HTML."
        />
    );
}

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Mappa">
                    <p>La mappa si carica solo quando il visitatore la apre: la pagina è più leggera e Google non imposta cookie prima.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Codice di Google Maps"
                            help="In Google Maps: Condividi → Incorpora una mappa → Copia HTML. Va bene anche solo l’indirizzo dell’iframe."
                            rows={5}
                            value={attributes.embedCode}
                            onChange={set('embedCode')}
                        />
                        <TextControl
                            {...field}
                            label="Indirizzo (facoltativo)"
                            help="Sulla copertina della mappa; «Apri in Google Maps» lo cerca."
                            value={attributes.address}
                            onChange={set('address')}
                        />
                        <RangeControl {...field} label="Altezza (px)" min={200} max={800} step={10} value={attributes.height} onChange={set('height')} />
                        <RangeControl
                            {...field}
                            label="Angoli (px)"
                            help="Seguono anche la forma scelta in Hive → Impostazioni."
                            min={0}
                            max={24}
                            value={attributes.borderRadius}
                            onChange={set('borderRadius')}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes EmptyResponsePlaceholder={NoMap} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
