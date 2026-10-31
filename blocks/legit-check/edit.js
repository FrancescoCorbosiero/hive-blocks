/**
 * Legit check — editor. The canvas shows the server render; the sidebar
 * edits the texts, the checks (a detail, the real one, the fake one) and
 * the button.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const CHECK_FIELDS = [
    { key: 'area', label: 'Dettaglio', help: 'Es. Cuciture, Suola, Scatola.' },
    { key: 'real', label: 'Autentico' },
    { key: 'fake', label: 'Falso' },
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
                    </div>
                </PanelBody>
                <PanelBody title="Verifiche" initialOpen={false}>
                    <ListControl
                        items={attributes.checks}
                        fields={CHECK_FIELDS}
                        onChange={set('checks')}
                        itemLabel={(item, index) => item.area || `Verifica ${index + 1}`}
                        newItem={() => ({ area: '', real: '', fake: '' })}
                        addLabel="Aggiungi una verifica"
                    />
                </PanelBody>
                <PanelBody title="Pulsante" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Testo del pulsante" value={attributes.buttonText} onChange={set('buttonText')} />
                        <TextControl {...field} label="Link del pulsante" help="Senza link il pulsante non compare." type="url" value={attributes.buttonUrl} onChange={set('buttonUrl')} />
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
