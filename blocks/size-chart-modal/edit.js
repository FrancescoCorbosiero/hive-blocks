/**
 * Guida alle taglie — editor. The canvas shows the server render (the
 * button); the sidebar picks the guide from Hive → Guide alle taglie, or the
 * standard table with its title and description, and sets up the button.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { cleanForSlug } from '@wordpress/url';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

// Published guides only, readable by anyone who can edit the page.
const GUIDES = ['postType', 'hive_size_guide', { per_page: 100, status: 'publish', orderby: 'title', order: 'asc', context: 'view', _fields: 'id,title' }];

/** The published size guides; whether the list has arrived, or couldn't. */
function useGuides() {
    return useSelect((select) => {
        const store = select(coreStore);
        return {
            guides: store.getEntityRecords(...GUIDES),
            loaded: store.hasFinishedResolution('getEntityRecords', GUIDES),
            failed: store.hasResolutionFailed('getEntityRecords', GUIDES),
        };
    }, []);
}

function Edit({ attributes, setAttributes }) {
    const { guideId, modalId, title, description, triggerText, showTrigger } = attributes;
    const { guides, loaded, failed } = useGuides();
    const list = (guides || []).map((guide) => ({ label: decodeEntities(guide.title?.rendered || '') || `Guida n. ${guide.id}`, value: String(guide.id) }));
    const options = [{ label: 'Tabella standard (US, UK, EU, CM)', value: '0' }, ...list];
    const listed = list.some((option) => option.value === String(guideId));
    const missing = guideId > 0 && loaded && !failed && !listed;
    if (guideId > 0 && !listed) {
        options.push({ label: missing ? `Guida n. ${guideId} (non più pubblicata)` : `Guida n. ${guideId}`, value: String(guideId) });
    }
    const help = failed ? 'Non è stato possibile caricare le guide: riprova più tardi.' : loaded ? 'Le guide si scrivono in Hive → Guide alle taglie.' : 'Caricamento delle guide…';
    const set = (key) => (value) => setAttributes({ [key]: value });
    // What the server makes of it (sanitize_title()).
    const anchor = `#${cleanForSlug(modalId) || 'size-chart'}`;

    return (
        <>
            <InspectorControls>
                <PanelBody title="Guida">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl
                            {...field}
                            label="Guida da aprire"
                            help={help}
                            value={String(guideId || 0)}
                            options={options}
                            onChange={(value) => setAttributes({ guideId: Number(value) || 0 })}
                        />
                        {missing && (
                            <Notice status="warning" isDismissible={false}>
                                Questa guida non è più pubblicata: sul sito si apre la tabella standard.
                            </Notice>
                        )}
                        {!guideId && (
                            <>
                                <TextControl {...field} label="Titolo" value={title} onChange={set('title')} />
                                <TextControl {...field} label="Descrizione" help="Sopra la tabella." value={description} onChange={set('description')} />
                            </>
                        )}
                    </div>
                </PanelBody>
                <PanelBody title="Pulsante">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <ToggleControl __nextHasNoMarginBottom label="Mostra il pulsante" checked={showTrigger} onChange={set('showTrigger')} />
                        {showTrigger && <TextControl {...field} label="Testo del pulsante" value={triggerText} onChange={set('triggerText')} />}
                        <TextControl
                            {...field}
                            label="Nome per i link"
                            help={`Un link a ${anchor} in questa pagina apre la guida, anche senza pulsante.`}
                            value={modalId}
                            onChange={set('modalId')}
                        />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                {showTrigger ? (
                    <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes />
                ) : (
                    <Notice status="info" isDismissible={false}>
                        Pulsante nascosto: la guida si apre dai link a {anchor} in questa pagina.
                    </Notice>
                )}
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
