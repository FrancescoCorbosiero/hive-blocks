/**
 * Nella tua taglia — editor: the title, the text above the sizes, how many
 * products and columns. The canvas shows the size choice as visitors first
 * see it.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

function Edit({ attributes, setAttributes }) {
    const { title, text, limit, columns } = attributes;
    const blockProps = useBlockProps();
    return (
        <>
            <InspectorControls>
                <PanelBody title="Nella tua taglia">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl __next40pxDefaultSize __nextHasNoMarginBottom label="Titolo" value={title} onChange={(value) => setAttributes({ title: value })} />
                        <TextareaControl __nextHasNoMarginBottom label="Testo sopra le taglie" value={text} onChange={(value) => setAttributes({ text: value })} />
                        <RangeControl __next40pxDefaultSize __nextHasNoMarginBottom label="Prodotti" min={1} max={24} value={limit} onChange={(value) => setAttributes({ limit: value })} />
                        <RangeControl __next40pxDefaultSize __nextHasNoMarginBottom label="Colonne" min={1} max={8} value={columns} onChange={(value) => setAttributes({ columns: value })} />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <ServerSideRender block={name} attributes={{ title, text, limit, columns }} skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit });
