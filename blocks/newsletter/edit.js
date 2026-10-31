/**
 * Newsletter — editor: the words, and whether to ask for sizes (and brands)
 * for the size alerts.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

function Edit({ attributes, setAttributes }) {
    const { title, text, placeholder, buttonText, disclaimer, askSize, sizeLabel, askBrands } = attributes;
    const blockProps = useBlockProps();
    const field = (key, label, Control = TextControl) => (
        <Control __next40pxDefaultSize __nextHasNoMarginBottom label={label} value={attributes[key]} onChange={(value) => setAttributes({ [key]: value })} />
    );
    return (
        <>
            <InspectorControls>
                <PanelBody title="Testi">
                    <div style={{ display: 'grid', gap: 16 }}>
                        {field('title', 'Titolo')}
                        <TextareaControl __nextHasNoMarginBottom label="Testo" value={text} onChange={(value) => setAttributes({ text: value })} />
                        {field('placeholder', 'Testo nel campo email')}
                        {field('buttonText', 'Pulsante')}
                        <TextareaControl __nextHasNoMarginBottom label="Nota sotto il modulo" value={disclaimer} onChange={(value) => setAttributes({ disclaimer: value })} />
                    </div>
                </PanelBody>
                <PanelBody title="Avvisi per taglia">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl __nextHasNoMarginBottom label="Chiedi le taglie" help="Fino a 3: un’email quando arriva un paio disponibile, dopo la conferma." checked={askSize} onChange={(value) => setAttributes({ askSize: value })} />
                        {askSize && field('sizeLabel', 'Titolo della scelta')}
                        {askSize && <ToggleControl __nextHasNoMarginBottom label="Chiedi anche i marchi" checked={askBrands} onChange={(value) => setAttributes({ askBrands: value })} />}
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender block={name} attributes={{ title, text, placeholder, buttonText, disclaimer, askSize, sizeLabel, askBrands }} skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit });
