/**
 * Domande frequenti — editor. The canvas shows the server render (the
 * questions, closed, as the visitor first sees them); the sidebar edits the
 * heading and the questions. The FAQPage data for Google comes from the
 * same questions, on the page.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const QUESTION_FIELDS = [
    { key: 'question', label: 'Domanda' },
    { key: 'answer', label: 'Risposta', help: 'Si possono usare grassetto, corsivo e link in HTML (<strong>, <em>, <a>).' },
];

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Contenuti">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextControl {...field} label="Sottotitolo" value={attributes.subtitle} onChange={set('subtitle')} />
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label="Più risposte aperte insieme"
                            help={attributes.allowMultiple ? 'Ogni domanda si apre e si chiude da sola.' : 'Aprendo una domanda si chiude quella aperta.'}
                            checked={attributes.allowMultiple}
                            onChange={set('allowMultiple')}
                        />
                    </div>
                </PanelBody>
                <PanelBody title="Domande e risposte" initialOpen={false}>
                    <p>Le stesse domande vanno a Google come dati strutturati (FAQPage), una volta per pagina.</p>
                    <ListControl
                        items={attributes.items}
                        fields={QUESTION_FIELDS}
                        onChange={set('items')}
                        itemLabel={(item, index) => item.question || `Domanda ${index + 1}`}
                        newItem={() => ({ question: '', answer: '' })}
                        addLabel="Aggiungi una domanda"
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
