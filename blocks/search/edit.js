/**
 * Cerca — editor: how the button looks (a search field, a button, an icon),
 * its words, and the popular searches the dialog suggests.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

function Edit({ attributes, setAttributes }) {
    const { look, label, placeholder, popular } = attributes;
    const blockProps = useBlockProps();
    return (
        <>
            <InspectorControls>
                <PanelBody title="Ricerca">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label="Aspetto"
                            value={look}
                            options={[
                                { value: 'field', label: 'Un campo di ricerca' },
                                { value: 'button', label: 'Un pulsante con la scritta' },
                                { value: 'icon', label: 'Solo la lente' },
                            ]}
                            onChange={(value) => setAttributes({ look: value })}
                        />
                        <TextControl __next40pxDefaultSize __nextHasNoMarginBottom label="Scritta del pulsante" value={label} onChange={(value) => setAttributes({ label: value })} />
                        <TextControl __next40pxDefaultSize __nextHasNoMarginBottom label="Testo nel campo" value={placeholder} onChange={(value) => setAttributes({ placeholder: value })} />
                        <TextControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label="Ricerche popolari"
                            help="Separate da virgole, es. Jordan 4, Dunk Low, Samba"
                            value={popular}
                            onChange={(value) => setAttributes({ popular: value })}
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

registerBlockType(name, { edit: Edit });
