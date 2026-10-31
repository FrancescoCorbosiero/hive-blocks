/**
 * Hero — editor. The canvas shows the server render (render.php), so the
 * editor and the page print the same thing; the sidebar edits the layout,
 * the texts, the buttons, the media and the values. The inserter offers
 * the three layouts as variations (block.json): Copertina, Diviso, Chi siamo.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { MediaControl, POSITIONS } from './media-control.js';
import { name } from './block.json';

const LAYOUTS = [
    { label: 'Copertina: l’immagine o il video dietro al testo', value: 'cover' },
    { label: 'Diviso: l’immagine accanto al testo', value: 'split' },
    { label: 'Chi siamo: testo, valori e immagine', value: 'about' },
];

const HEIGHTS = [
    { label: 'Quanto il contenuto', value: 'auto' },
    { label: 'Alta (70% dello schermo)', value: 'tall' },
    { label: 'Tutto lo schermo', value: 'full' },
];

const VALUE_FIELDS = [
    { key: 'title', label: 'Titolo' },
    { key: 'text', label: 'Testo' },
];

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };
    const cover = attributes.layout === 'cover';

    return (
        <>
            <InspectorControls>
                <PanelBody title="Testi">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl {...field} label="Disposizione" value={attributes.layout} options={LAYOUTS} onChange={set('layout')} />
                        <TextControl {...field} label="Etichetta con lo scudo" help="Es. 100% autentico. Vuota per nasconderla." value={attributes.badge} onChange={set('badge')} />
                        <TextControl {...field} label="Occhiello" help="Il testo piccolo sopra il titolo." value={attributes.eyebrow} onChange={set('eyebrow')} />
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TextControl
                            {...field}
                            label="Parte del titolo in evidenza"
                            help="Facoltativa: una parola o frase del titolo da mostrare nel colore del brand, es. PIACENZA."
                            value={attributes.titleAccent}
                            onChange={set('titleAccent')}
                        />
                        <SelectControl
                            {...field}
                            label="Livello del titolo"
                            help="H1 se è il titolo principale della pagina (e la pagina non ne ha già uno), altrimenti H2."
                            value={attributes.headingLevel}
                            options={[
                                { label: 'H1', value: 'h1' },
                                { label: 'H2', value: 'h2' },
                            ]}
                            onChange={set('headingLevel')}
                        />
                        <TextareaControl __nextHasNoMarginBottom label="Sottotitolo" value={attributes.subtitle} onChange={set('subtitle')} />
                    </div>
                </PanelBody>
                <PanelBody title="Pulsanti" initialOpen={false}>
                    <p>Un pulsante compare quando ha sia il testo sia il link.</p>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Testo del pulsante" value={attributes.buttonText} onChange={set('buttonText')} />
                        <TextControl {...field} label="Link del pulsante" value={attributes.buttonUrl} onChange={set('buttonUrl')} />
                        <SelectControl
                            {...field}
                            label="Stile del pulsante"
                            value={attributes.buttonStyle}
                            options={[
                                { label: 'Pieno', value: 'solid' },
                                { label: 'Contorno', value: 'outline' },
                            ]}
                            onChange={set('buttonStyle')}
                        />
                        <TextControl {...field} label="Testo del secondo pulsante" value={attributes.secondaryButtonText} onChange={set('secondaryButtonText')} />
                        <TextControl {...field} label="Link del secondo pulsante" value={attributes.secondaryButtonUrl} onChange={set('secondaryButtonUrl')} />
                    </div>
                </PanelBody>
                <PanelBody title="Immagine e video" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <MediaControl
                            label={cover ? 'Immagine di sfondo' : 'Immagine'}
                            help={attributes.videoUrl ? 'È anche la copertina del video, finché il video non parte.' : undefined}
                            url={attributes.imageUrl}
                            id={attributes.imageId}
                            onChange={({ id, url }) => setAttributes({ imageId: id, imageUrl: url })}
                        />
                        <SelectControl {...field} label="Punto focale dell’immagine" value={attributes.imagePosition} options={POSITIONS} onChange={set('imagePosition')} />
                        <MediaControl
                            label="Video (facoltativo)"
                            type="video"
                            url={attributes.videoUrl}
                            onChange={({ url }) => setAttributes({ videoUrl: url })}
                        />
                        <TextControl
                            {...field}
                            label="Oppure il link del video"
                            help="Un MP4 breve e leggero, senza audio: parte da solo quando la pagina è caricata, e mai per chi preferisce meno animazioni."
                            value={attributes.videoUrl}
                            onChange={set('videoUrl')}
                        />
                        {cover && (
                            <>
                                <MediaControl
                                    label="Immagine in primo piano (facoltativa)"
                                    help="Un prodotto scontornato (PNG), al centro sopra lo sfondo."
                                    url={attributes.foregroundUrl}
                                    id={attributes.foregroundId}
                                    onChange={({ id, url }) => setAttributes({ foregroundId: id, foregroundUrl: url })}
                                />
                                <RangeControl {...field} label="Scurisci lo sfondo (%)" min={0} max={90} step={5} value={attributes.overlay} onChange={set('overlay')} />
                            </>
                        )}
                        <ToggleControl
                            __nextHasNoMarginBottom
                            label="In cima alla pagina"
                            help="L’immagine si carica subito, con priorità alta. Spegnilo se il blocco non è il primo che si vede."
                            checked={attributes.aboveFold}
                            onChange={set('aboveFold')}
                        />
                    </div>
                </PanelBody>
                <PanelBody title="Aspetto" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl {...field} label="Altezza" value={attributes.height} options={HEIGHTS} onChange={set('height')} />
                        {!cover && (
                            <>
                                <SelectControl
                                    {...field}
                                    label="Lato dell’immagine (da tablet in su)"
                                    value={attributes.mediaSide}
                                    options={[
                                        { label: 'Sinistra', value: 'left' },
                                        { label: 'Destra', value: 'right' },
                                    ]}
                                    onChange={set('mediaSide')}
                                />
                                <SelectControl
                                    {...field}
                                    label="Colori"
                                    value={attributes.tone}
                                    options={[
                                        { label: 'Quelli del tema', value: 'auto' },
                                        { label: 'Scuri', value: 'dark' },
                                    ]}
                                    onChange={set('tone')}
                                />
                            </>
                        )}
                        {cover && (
                            <ToggleControl
                                __nextHasNoMarginBottom
                                label="Mostra «Scorri» in basso"
                                checked={attributes.showScrollIndicator}
                                onChange={set('showScrollIndicator')}
                            />
                        )}
                    </div>
                </PanelBody>
                <PanelBody title="Valori" initialOpen={attributes.layout === 'about'}>
                    <p>Una lista con la spunta sotto il testo, come i valori del negozio.</p>
                    <ListControl
                        items={attributes.values}
                        fields={VALUE_FIELDS}
                        onChange={set('values')}
                        itemLabel={(item, index) => item.title || `Valore ${index + 1}`}
                        newItem={() => ({ title: '', text: '' })}
                        addLabel="Aggiungi un valore"
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
