/**
 * Hive — editor components shared by the blocks (script hive-editor,
 * window.hive.editor). Block editor scripts import them from '@hive/editor';
 * the build turns that import into this global and a dependency on the
 * script, so the code ships once instead of in every block.
 */
import { Button, Card, CardBody, CardHeader, Flex, TextControl } from '@wordpress/components';

/**
 * A list of items edited in place, e.g. the days of the opening hours:
 * each item's text fields, then move up, move down, remove; a button adds
 * one at the end.
 *
 * @param {Object}   props
 * @param {Array}    props.items     The list (an attribute).
 * @param {Function} props.onChange  Receives the new list.
 * @param {Array}    props.fields    [{ key, label, help? }] for each item.
 * @param {Function} props.itemLabel (item, index) → the item's title.
 * @param {Function} props.newItem   () → a blank item.
 * @param {string}   props.addLabel  The add button's text.
 */
export function ListControl({ items, onChange, fields, itemLabel, newItem, addLabel = 'Aggiungi' }) {
    const list = Array.isArray(items) ? items : [];
    const update = (index, key, value) => onChange(list.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
    const remove = (index) => onChange(list.filter((_, i) => i !== index));
    const move = (index, delta) => {
        const next = list.slice();
        const [item] = next.splice(index, 1);
        next.splice(index + delta, 0, item);
        onChange(next);
    };

    return (
        <div className="hive-list-control">
            {list.map((item, index) => (
                <Card key={index} size="small" style={{ marginBottom: 12 }}>
                    <CardHeader>
                        <strong>{itemLabel(item, index)}</strong>
                    </CardHeader>
                    <CardBody>
                        <div style={{ display: 'grid', gap: 12 }}>
                            {fields.map((field) => (
                                <TextControl
                                    key={field.key}
                                    __next40pxDefaultSize
                                    __nextHasNoMarginBottom
                                    label={field.label}
                                    help={field.help}
                                    value={item?.[field.key] ?? ''}
                                    onChange={(value) => update(index, field.key, value)}
                                />
                            ))}
                            <Flex justify="flex-start" gap={2}>
                                <Button size="small" variant="secondary" disabled={index === 0} onClick={() => move(index, -1)}>
                                    Su
                                </Button>
                                <Button size="small" variant="secondary" disabled={index === list.length - 1} onClick={() => move(index, 1)}>
                                    Giù
                                </Button>
                                <Button size="small" variant="secondary" isDestructive onClick={() => remove(index)}>
                                    Rimuovi
                                </Button>
                            </Flex>
                        </div>
                    </CardBody>
                </Card>
            ))}
            <Button variant="secondary" onClick={() => onChange([...list, newItem()])}>
                {addLabel}
            </Button>
        </div>
    );
}

window.hive = window.hive || {};
window.hive.editor = { ListControl };
