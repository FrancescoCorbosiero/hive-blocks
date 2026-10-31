/**
 * Pulsante WhatsApp — editor. The canvas shows the server render where the
 * block is (on the site it stays fixed in the bottom corner); the sidebar
 * edits the number, the message and the text.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

/** The server printed nothing: there's no number to write to. */
function NoNumber() {
    return (
        <Notice status="warning" isDismissible={false}>
            Manca il numero WhatsApp: scrivilo nel pannello a destra, oppure in Hive → Impostazioni perché valga per tutto il sito.
        </Notice>
    );
}

function Edit({ attributes, setAttributes }) {
    const { phoneNumber, message, buttonText, iconOnly } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title="WhatsApp">
                    <p>Sul sito il pulsante resta fisso nell’angolo in basso a destra e compare quando si scorre la pagina.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl
                            {...field}
                            type="tel"
                            label="Numero"
                            help="Con il prefisso internazionale, es. 393331234567. Vuoto: quello di Hive → Impostazioni."
                            value={phoneNumber}
                            onChange={(value) => setAttributes({ phoneNumber: value })}
                        />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label="Messaggio"
                            help="Già scritto nella chat: basta inviarlo."
                            value={message}
                            onChange={(value) => setAttributes({ message: value })}
                        />
                        <TextControl {...field} label="Testo del pulsante" value={buttonText} onChange={(value) => setAttributes({ buttonText: value })} />
                        <ToggleControl __nextHasNoMarginBottom label="Solo icona" checked={iconOnly} onChange={(value) => setAttributes({ iconOnly: value })} />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'hive-preview' })}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes EmptyResponsePlaceholder={NoNumber} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
