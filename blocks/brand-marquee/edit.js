/**
 * Brand in scorrimento — editor. The canvas shows the server render; the
 * sidebar picks the store's brands (as the product rail does) or lists
 * brands by hand, each with its logo and link, and sets the speed.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, Card, CardBody, CardHeader, Flex, PanelBody, Placeholder, RangeControl, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { ImageField } from '../../assets/editor/image-field.js';
import { TermsField } from '../../assets/editor/terms-field.js';
import { name } from './block.json';

const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const Empty = () => (
    <Placeholder icon="images-alt" label="Brand in scorrimento">
        Nessun brand da mostrare: scegli i brand del negozio, oppure aggiungili a mano con il loro logo.
    </Placeholder>
);

/** Brands typed by hand: name, link, logo; up, down, remove; add one at the end. */
function BrandList({ items, onChange }) {
    const list = Array.isArray(items) ? items : [];
    const update = (index, patch) => onChange(list.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    const move = (index, delta) => {
        const next = list.slice();
        const [item] = next.splice(index, 1);
        next.splice(index + delta, 0, item);
        onChange(next);
    };

    return (
        <div>
            {list.map((item, index) => (
                <Card key={index} size="small" style={{ marginBottom: 12 }}>
                    <CardHeader>
                        <strong>{item.name || `Brand ${index + 1}`}</strong>
                    </CardHeader>
                    <CardBody>
                        <div style={{ display: 'grid', gap: 12 }}>
                            <TextControl {...field} label="Nome" value={item.name || ''} onChange={(value) => update(index, { name: value })} />
                            <TextControl {...field} label="Link" value={item.url || ''} onChange={(value) => update(index, { url: value })} />
                            <ImageField label="Logo" help="Senza logo si vede il nome." value={item.logoId || 0} onChange={(value) => update(index, { logoId: value })} />
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
            <Button variant="secondary" onClick={() => onChange([...list, { name: '', url: '', logoId: 0 }])}>
                Aggiungi un brand
            </Button>
        </div>
    );
}

function Edit({ attributes, setAttributes }) {
    const set = (key) => (value) => setAttributes({ [key]: value });
    const typed = Array.isArray(attributes.brands) && attributes.brands.length > 0;

    return (
        <>
            <InspectorControls>
                <PanelBody title="Brand">
                    <div style={{ display: 'grid', gap: 16 }}>
                        <TextControl {...field} label="Titolo" value={attributes.title} onChange={set('title')} />
                        <TermsField
                            label="Brand del negozio"
                            help={
                                typed
                                    ? 'Non usati: ci sono brand scritti a mano qui sotto.'
                                    : 'In quest’ordine. Vuoto: tutti i brand con prodotti. Il logo è l’immagine del brand; si possono scrivere anche gli slug di altre tassonomie delle marche.'
                            }
                            taxonomy="product_brand"
                            value={attributes.brand}
                            onChange={set('brand')}
                        />
                    </div>
                </PanelBody>
                <PanelBody title="Brand scritti a mano" initialOpen={typed}>
                    <p>Se ce n’è almeno uno, il blocco mostra questi al posto dei brand del negozio.</p>
                    <BrandList items={attributes.brands} onChange={set('brands')} />
                </PanelBody>
                <PanelBody title="Movimento" initialOpen={false}>
                    <div style={{ display: 'grid', gap: 16 }}>
                        <RangeControl {...field} label="Velocità (pixel al secondo)" min={10} max={200} step={5} value={attributes.speed} onChange={set('speed')} />
                        <SelectControl
                            {...field}
                            label="Direzione"
                            value={attributes.direction}
                            options={[
                                { label: 'Verso sinistra', value: 'left' },
                                { label: 'Verso destra', value: 'right' },
                            ]}
                            onChange={set('direction')}
                        />
                        <p style={{ margin: 0 }}>Si ferma sotto il mouse; chi ha chiesto meno animazioni vede i loghi fermi.</p>
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender block={name} attributes={attributes} skipBlockSupportAttributes EmptyResponsePlaceholder={Empty} />
            </div>
        </>
    );
}

registerBlockType(name, { edit: Edit, save: () => null });
