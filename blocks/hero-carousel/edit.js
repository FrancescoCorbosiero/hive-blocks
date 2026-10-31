/**
 * Carosello — editor. The canvas shows the server render (render.php); the
 * sidebar edits the slides (their texts in a list, their pictures below)
 * and how they move.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ListControl } from '@hive/editor';
import { MediaControl, POSITIONS } from '../hero/media-control.js';
import { name } from './block.json';

const SLIDE_FIELDS = [
    { key: 'eyebrow', label: 'Occhiello' },
    { key: 'title', label: 'Titolo' },
    { key: 'subtitle', label: 'Sottotitolo' },
    { key: 'buttonText', label: 'Testo del pulsante' },
    { key: 'buttonUrl', label: 'Link del pulsante', help: 'Il pulsante compare quando ha sia il testo sia il link.' },
];

const newSlide = () => ({ image: '', imageId: 0, objectPosition: 'center center', eyebrow: '', title: '', subtitle: '', buttonText: '', buttonUrl: '' });
const slideLabel = (slide, index) => `Slide ${index + 1}${slide?.title ? ` · ${slide.title}` : ''}`;

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };
    const slides = Array.isArray(attributes.slides) ? attributes.slides : [];
    const update = (index, patch) => setAttributes({ slides: slides.map((slide, i) => (i === index ? { ...slide, ...patch } : slide)) });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Slide">
                    <ListControl
                        items={slides}
                        fields={SLIDE_FIELDS}
                        onChange={set('slides')}
                        itemLabel={slideLabel}
                        newItem={newSlide}
                        addLabel="Aggiungi una slide"
                    />
                </PanelBody>
                <PanelBody title="Immagini delle slide" initialOpen={false}>
                    <p>Solo l’immagine della prima slide si carica con la pagina; le altre arrivano dopo.</p>
                    {slides.map((slide, index) => (
                        <div key={index} style={{ display: 'grid', gap: 12, marginBottom: 16, paddingBottom: 16, borderBottom: '1px solid #ddd' }}>
                            <MediaControl
                                label={slideLabel(slide, index)}
                                url={slide.image}
                                id={slide.imageId}
                                onChange={({ id, url }) => update(index, { image: url, imageId: id })}
                            />
                            <SelectControl
                                {...field}
                                label="Punto focale"
                                help="Conta soprattutto sui telefoni, dove l’immagine viene ritagliata di più."
                                value={slide.objectPosition || 'center center'}
                                options={POSITIONS}
                                onChange={(value) => update(index, { objectPosition: value })}
                            />
                        </div>
                    ))}
                </PanelBody>
                <PanelBody title="Scorrimento" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <SelectControl
                            {...field}
                            label="Disposizione del testo"
                            value={attributes.layout}
                            options={[
                                { label: 'A sinistra', value: 'left' },
                                { label: 'Al centro', value: 'centered' },
                            ]}
                            onChange={set('layout')}
                        />
                        <RangeControl
                            {...field}
                            label="Scorrimento automatico (secondi)"
                            help="0 per scorrere solo a mano. Si ferma col puntatore sopra o con la tastiera dentro, e non parte per chi preferisce meno animazioni."
                            min={0}
                            max={20}
                            step={0.5}
                            value={(Number(attributes.autoplay) || 0) / 1000}
                            onChange={(seconds) => setAttributes({ autoplay: Math.round((Number(seconds) || 0) * 1000) })}
                        />
                        <ToggleControl __nextHasNoMarginBottom label="Pallini" checked={attributes.showDots} onChange={set('showDots')} />
                        <ToggleControl __nextHasNoMarginBottom label="Frecce (da desktop)" checked={attributes.showArrows} onChange={set('showArrows')} />
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
