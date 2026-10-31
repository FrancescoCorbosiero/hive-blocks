/**
 * Instagram — editor. The canvas shows the server render; the sidebar edits
 * the heading, the profile, the columns and the photos: each one from the
 * media library, with its alternative text, the link to its post and, for
 * "Acquista il look", the products it shows (a click on the photo tags one
 * there).
 *
 * The photos are a list of their own, with ListControl's buttons: @hive/editor's
 * ListControl edits text fields only, and each photo here also has its
 * picture and its tags.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } from '@wordpress/block-editor';
import { Button, Card, CardBody, CardHeader, ComboboxControl, Flex, PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';
import ServerSideRender from '@wordpress/server-side-render';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };
const option = (product) => ({ value: String(product.id), label: decodeEntities(product.name || '') || `Prodotto n. ${product.id}` });

/** A product, searched by name among the shop's published ones (WooCommerce's Store API). */
function ProductPicker({ value, onChange }) {
    const [current, setCurrent] = useState(null);
    const [found, setFound] = useState([]);
    const timer = useRef(0);

    // The saved product's name, before any search.
    useEffect(() => {
        if (!value || current?.value === String(value)) return undefined;
        let live = true;
        apiFetch({ path: `/wc/store/v1/products/${value}` })
            .then((product) => live && setCurrent(option(product)))
            .catch(() => live && setCurrent({ value: String(value), label: `Prodotto n. ${value} (non trovato)` }));
        return () => {
            live = false;
        };
    }, [value]);

    useEffect(() => () => clearTimeout(timer.current), []);

    const search = (term) => {
        clearTimeout(timer.current);
        const text = (term || '').trim();
        if (text.length < 2) return;
        timer.current = setTimeout(() => {
            apiFetch({ path: `/wc/store/v1/products?per_page=20&search=${encodeURIComponent(text)}` })
                .then((list) => setFound((Array.isArray(list) ? list : []).map(option)))
                .catch(() => setFound([]));
        }, 250);
    };

    const options = current && !found.some((item) => item.value === current.value) ? [current, ...found] : found;

    return (
        <ComboboxControl
            {...field}
            label="Prodotto"
            help="Scrivi almeno due lettere del nome."
            value={value ? String(value) : null}
            options={options}
            onFilterValueChange={search}
            onChange={(next) => {
                const picked = options.find((item) => item.value === next);
                if (picked) setCurrent(picked);
                onChange(next ? Number(next) : 0);
            }}
        />
    );
}

/** "Acquista il look": a click on the photo tags a product at that point; each tag's product and place below. */
function Tags({ image, onChange }) {
    const spots = Array.isArray(image.hotspots) ? image.hotspots : [];
    const update = (index, patch) => onChange(spots.map((spot, i) => (i === index ? { ...spot, ...patch } : spot)));
    const add = (event) => {
        const box = event.currentTarget.getBoundingClientRect();
        // From the keyboard there's no pointer: the middle of the photo.
        const at = (offset, size) => (event.detail === 0 || !size ? 50 : Math.max(0, Math.min(100, Math.round((offset / size) * 100))));
        onChange([...spots, { productId: 0, x: at(event.clientX - box.left, box.width), y: at(event.clientY - box.top, box.height) }]);
    };

    return (
        <div style={{ display: 'grid', gap: 8 }}>
            <button
                type="button"
                onClick={add}
                aria-label="Aggiungi un prodotto in questo punto della foto"
                style={{ position: 'relative', display: 'block', width: '100%', padding: 0, border: 0, background: 'none', cursor: 'crosshair', lineHeight: 0 }}
            >
                <img src={image.url} alt="" style={{ display: 'block', width: '100%', height: 'auto' }} />
                {spots.map((spot, index) => (
                    <span
                        key={index}
                        aria-hidden="true"
                        style={{
                            position: 'absolute',
                            left: `${spot.x}%`,
                            top: `${spot.y}%`,
                            width: 22,
                            height: 22,
                            transform: 'translate(-50%, -50%)',
                            border: '2px solid #fff',
                            borderRadius: '50%',
                            background: spot.productId ? 'var(--wp-admin-theme-color, #3858e9)' : '#757575',
                            boxShadow: '0 1px 4px rgba(0, 0, 0, 0.4)',
                            color: '#fff',
                            font: '600 11px/18px sans-serif',
                            textAlign: 'center',
                            pointerEvents: 'none',
                        }}
                    >
                        {index + 1}
                    </span>
                ))}
            </button>
            <p style={{ margin: 0, color: '#757575', fontSize: 12 }}>
                Acquista il look: {spots.length ? 'clicca di nuovo sulla foto per aggiungere un altro prodotto.' : 'clicca sulla foto dove si vede un prodotto.'}
            </p>
            {spots.map((spot, index) => (
                <div key={index} style={{ display: 'grid', gap: 8, padding: 8, borderRadius: 2, background: '#f6f7f7' }}>
                    <strong>Punto {index + 1}</strong>
                    <ProductPicker value={spot.productId} onChange={(productId) => update(index, { productId })} />
                    <RangeControl {...field} label="Da sinistra (%)" min={0} max={100} value={spot.x} onChange={(x) => update(index, { x: x ?? 50 })} />
                    <RangeControl {...field} label="Dall’alto (%)" min={0} max={100} value={spot.y} onChange={(y) => update(index, { y: y ?? 50 })} />
                    <Flex justify="flex-start">
                        <Button size="small" variant="secondary" isDestructive onClick={() => onChange(spots.filter((_, i) => i !== index))}>
                            Rimuovi il punto
                        </Button>
                    </Flex>
                </div>
            ))}
        </div>
    );
}

/** The photos: each with its picture, its text, its post and its tags; up, down, remove, add. */
function Photos({ images, onChange }) {
    const list = Array.isArray(images) ? images : [];
    const update = (index, patch) => onChange(list.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    const move = (index, delta) => {
        const next = list.slice();
        const [item] = next.splice(index, 1);
        next.splice(index + delta, 0, item);
        onChange(next);
    };

    return (
        <div>
            {list.map((image, index) => (
                <Card key={index} size="small" style={{ marginBottom: 12 }}>
                    <CardHeader>
                        <strong>Foto {index + 1}</strong>
                    </CardHeader>
                    <CardBody>
                        <div style={{ display: 'grid', gap: 12 }}>
                            {image.url && <Tags image={image} onChange={(hotspots) => update(index, { hotspots })} />}
                            <MediaUploadCheck>
                                <MediaUpload
                                    allowedTypes={['image']}
                                    value={image.urlId || undefined}
                                    onSelect={(media) => update(index, { url: media.url, urlId: media.id || 0, alt: media.alt || image.alt || '' })}
                                    render={({ open }) => (
                                        <Flex justify="flex-start" gap={2}>
                                            <Button size="small" variant="secondary" onClick={open}>
                                                {image.url ? 'Sostituisci la foto' : 'Scegli una foto'}
                                            </Button>
                                            {image.url && (
                                                <Button size="small" variant="secondary" isDestructive onClick={() => update(index, { url: '', urlId: 0, hotspots: [] })}>
                                                    Togli la foto
                                                </Button>
                                            )}
                                        </Flex>
                                    )}
                                />
                            </MediaUploadCheck>
                            <TextControl {...field} label="Testo alternativo" help="Cosa si vede nella foto." value={image.alt || ''} onChange={(alt) => update(index, { alt })} />
                            <TextControl
                                {...field}
                                type="url"
                                label="Link al post (facoltativo)"
                                help="Vuoto: il profilo Instagram."
                                value={image.link || ''}
                                onChange={(link) => update(index, { link })}
                            />
                            <Flex justify="flex-start" gap={2}>
                                <Button size="small" variant="secondary" disabled={index === 0} onClick={() => move(index, -1)}>
                                    Su
                                </Button>
                                <Button size="small" variant="secondary" disabled={index === list.length - 1} onClick={() => move(index, 1)}>
                                    Giù
                                </Button>
                                <Button size="small" variant="secondary" isDestructive onClick={() => onChange(list.filter((_, i) => i !== index))}>
                                    Rimuovi
                                </Button>
                            </Flex>
                        </div>
                    </CardBody>
                </Card>
            ))}
            <Button variant="secondary" onClick={() => onChange([...list, { url: '', urlId: 0, alt: '', link: '' }])}>
                Aggiungi una foto
            </Button>
        </div>
    );
}

function Edit({ attributes, setAttributes }) {
    const { title, subtitle, instagramUrl, buttonText, images, columns } = attributes;
    const set = (key) => (value) => setAttributes({ [key]: value });

    return (
        <>
            <InspectorControls>
                <PanelBody title="Intestazione">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={title} onChange={set('title')} />
                        <TextControl {...field} label="Sottotitolo" value={subtitle} onChange={set('subtitle')} />
                    </div>
                </PanelBody>
                <PanelBody title="Profilo" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} type="url" label="Link al profilo Instagram" help="Vuoto: nessun pulsante sotto le foto." value={instagramUrl} onChange={set('instagramUrl')} />
                        <TextControl {...field} label="Testo del pulsante" value={buttonText} onChange={set('buttonText')} />
                    </div>
                </PanelBody>
                <PanelBody title={`Foto (${Array.isArray(images) ? images.length : 0})`} initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <RangeControl {...field} label="Colonne" help="Sul telefono sono sempre due." min={2} max={6} value={columns} onChange={set('columns')} />
                        <Photos images={images} onChange={set('images')} />
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} httpMethod="POST" skipBlockSupportAttributes />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
