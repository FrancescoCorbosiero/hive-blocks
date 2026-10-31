/**
 * Orari del negozio — editor. The canvas shows the server render
 * (render.php), so the editor and the page print the same thing; the
 * sidebar edits the heading, the days and the closures.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const DAY_FIELDS = [
    { key: 'day', label: 'Giorno' },
    { key: 'time', label: 'Orario', help: 'Es. 10.30 - 13.00 | 15.30 - 19.30, oppure Chiuso.' },
];

function Edit({ attributes, setAttributes }) {
    const { title, note, hours, closedOnHolidays, closedDates } = attributes;
    const blockProps = useBlockProps();

    return (
        <>
            <InspectorControls>
                <PanelBody title="Intestazione">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label="Titolo"
                            value={title}
                            onChange={(value) => setAttributes({ title: value })}
                        />
                        <TextControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label="Nota (facoltativa)"
                            value={note}
                            onChange={(value) => setAttributes({ note: value })}
                        />
                    </div>
                </PanelBody>
                <PanelBody title="Giorni" initialOpen={false}>
                    <ListControl
                        items={hours}
                        fields={DAY_FIELDS}
                        onChange={(next) => setAttributes({ hours: next })}
                        itemLabel={(item, index) => item.day || `Giorno ${index + 1}`}
                        newItem={() => ({ day: '', time: '' })}
                        addLabel="Aggiungi un giorno"
                    />
                </PanelBody>
                <PanelBody title="Chiusure" initialOpen={false}>
                    <p>
                        Sul sito il blocco dice se il negozio è aperto adesso («Aperto ora · chiude alle 19:30»), anche
                        quando la pagina arriva dalla cache.
                    </p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label="Chiuso nelle festività nazionali"
                            checked={closedOnHolidays}
                            onChange={(value) => setAttributes({ closedOnHolidays: value })}
                        />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Altre date di chiusura"
                            help="Una per riga: 24/12/2026 oppure 2026-12-24."
                            value={closedDates}
                            onChange={(value) => setAttributes({ closedDates: value })}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
