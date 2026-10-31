/**
 * Garanzia di autenticità — editor. The canvas shows the server render;
 * the sidebar edits the texts, the seal, the steps and the button.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const STEP_FIELDS = [
    { key: 'title', label: 'Titolo' },
    { key: 'text', label: 'Testo' },
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
                        <TextareaControl __nextHasNoMarginBottom label="Descrizione" value={attributes.description} onChange={set('description')} />
                    </div>
                </PanelBody>
                <PanelBody title="Sigillo" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Testo del sigillo" value={attributes.badgeText} onChange={set('badgeText')} />
                        <TextControl {...field} label="Firma sotto il sigillo" help="Vuota: il nome del sito." value={attributes.badgeSub} onChange={set('badgeSub')} />
                    </div>
                </PanelBody>
                <PanelBody title="Passaggi" initialOpen={false}>
                    <ListControl
                        items={attributes.steps}
                        fields={STEP_FIELDS}
                        onChange={set('steps')}
                        itemLabel={(item, index) => item.title || `Passaggio ${index + 1}`}
                        newItem={() => ({ title: '', text: '' })}
                        addLabel="Aggiungi un passaggio"
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
