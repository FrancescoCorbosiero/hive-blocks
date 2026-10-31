/**
 * Badge di fiducia — editor. The canvas shows the server render; the
 * sidebar edits the look, the badges (ListControl: their labels, order,
 * adding and removing) and each badge's icon.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const ICONS = [
    { label: 'Autentico', value: 'authentic' },
    { label: 'Spedizione', value: 'shipping' },
    { label: 'Reso', value: 'returns' },
    { label: 'Pagamento sicuro', value: 'secure' },
    { label: 'Assistenza', value: 'support' },
    { label: 'Qualità', value: 'quality' },
];

const VARIANTS = [
    { label: 'Striscia chiara', value: 'default' },
    { label: 'Fascia scura', value: 'carousel' },
];

function Edit({ attributes, setAttributes }) {
    const badges = Array.isArray(attributes.badges) ? attributes.badges : [];
    const setIcon = (index, icon) => setAttributes({ badges: badges.map((badge, i) => (i === index ? { ...badge, icon } : badge)) });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Aspetto">
                    <SelectControl
                        {...field}
                        label="Stile"
                        help="Se i badge non ci stanno, sul telefono la striscia scorre di lato con il dito."
                        value={attributes.variant}
                        options={VARIANTS}
                        onChange={(variant) => setAttributes({ variant })}
                    />
                </PanelBody>
                <PanelBody title="Badge" initialOpen={false}>
                    <ListControl
                        items={badges}
                        fields={[{ key: 'title', label: 'Testo' }]}
                        onChange={(next) => setAttributes({ badges: next })}
                        itemLabel={(item, index) => item.title || `Badge ${index + 1}`}
                        newItem={() => ({ icon: 'authentic', title: '' })}
                        addLabel="Aggiungi un badge"
                    />
                </PanelBody>
                <PanelBody title="Icone" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        {badges.map((badge, index) => (
                            <SelectControl
                                {...field}
                                key={index}
                                label={badge.title || `Badge ${index + 1}`}
                                value={badge.icon || 'authentic'}
                                options={ICONS}
                                onChange={(icon) => setIcon(index, icon)}
                            />
                        ))}
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
