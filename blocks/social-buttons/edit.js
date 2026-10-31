/**
 * Pulsanti social — editor. The canvas shows the server render as a row
 * where the block is (on the site the buttons stay fixed on the side of the
 * screen); the sidebar holds the profiles' links.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const NETWORKS = [
    { key: 'instagram', label: 'Instagram', placeholder: 'https://www.instagram.com/…' },
    { key: 'facebook', label: 'Facebook', placeholder: 'https://www.facebook.com/…' },
    { key: 'tiktok', label: 'TikTok', placeholder: 'https://www.tiktok.com/@…' },
    { key: 'youtube', label: 'YouTube', placeholder: 'https://www.youtube.com/@…' },
    { key: 'twitter', label: 'X (Twitter)', placeholder: 'https://x.com/…' },
];

/** The server printed nothing: no profile has a link yet. */
function NoLinks() {
    return (
        <Notice status="info" isDismissible={false}>
            Aggiungi il link di almeno un profilo nel pannello a destra.
        </Notice>
    );
}

function Edit({ attributes, setAttributes }) {
    return (
        <>
            <InspectorControls>
                <PanelBody title="Profili">
                    <p>Sul sito i pulsanti restano fissi sul lato destro dello schermo. Si vedono solo i profili con un link.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        {NETWORKS.map(({ key, label, placeholder }) => (
                            <TextControl
                                key={key}
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                                type="url"
                                label={label}
                                placeholder={placeholder}
                                value={attributes[key]}
                                onChange={(value) => setAttributes({ [key]: value })}
                            />
                        ))}
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'hive-preview' })}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes EmptyResponsePlaceholder={NoLinks} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
