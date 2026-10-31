/**
 * Acquisti recenti — editor. The canvas shows the server render, which in
 * the editor stands still with the latest paid order; the sidebar sets which
 * purchases and how often.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

/** A RangeControl in seconds for an attribute kept in milliseconds. */
function Seconds({ label, help, value, min, max, onChange }) {
    return <RangeControl {...field} label={label} help={help} min={min} max={max} step={1} value={Math.round((value || 0) / 1000)} onChange={(seconds) => onChange((seconds || 0) * 1000)} />;
}

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Acquisti mostrati">
                    <p>
                        L’avviso mostra gli ultimi ordini pagati (In lavorazione o Completati): prodotto, città di spedizione e da quanto. Mai nomi, email o importi. Non compare
                        nel carrello e al checkout.
                    </p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <RangeControl {...field} label="Ordini degli ultimi (giorni)" min={1} max={30} value={attributes.days} onChange={set('days')} />
                        <RangeControl {...field} label="Acquisti caricati" min={1} max={20} value={attributes.limit} onChange={set('limit')} />
                        <RangeControl
                            {...field}
                            label="Avvisi per visita, al massimo"
                            help="Ogni acquisto si vede una volta; chi chiude l’avviso non lo rivede fino alla prossima visita."
                            min={1}
                            max={10}
                            value={attributes.maxPerVisit}
                            onChange={set('maxPerVisit')}
                        />
                        <ToggleControl __nextHasNoMarginBottom label="Mostra la città" checked={attributes.showCity} onChange={set('showCity')} />
                    </div>
                </PanelBody>
                <PanelBody title="Aspetto e tempi" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <ToggleControl __nextHasNoMarginBottom label="«Acquisto verificato»" checked={attributes.showVerified} onChange={set('showVerified')} />
                        <Seconds label="Primo avviso dopo (secondi)" help="Contati da quando la pagina ha finito di caricare." min={0} max={60} value={attributes.initialDelay} onChange={set('initialDelay')} />
                        <Seconds label="Uno ogni (secondi)" min={5} max={120} value={attributes.interval} onChange={set('interval')} />
                        <Seconds label="Resta per (secondi)" min={2} max={20} value={attributes.displayDuration} onChange={set('displayDuration')} />
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
