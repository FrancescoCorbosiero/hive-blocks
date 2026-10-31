/**
 * An image from the media library, kept as its attachment id (the blocks
 * print it with wp_get_attachment_image()): a preview, "Scegli" / "Cambia",
 * "Rimuovi".
 *
 * Imported by path by the blocks that need it, so it ships inside their
 * editor scripts. It belongs in @hive/editor (index.js) next to ListControl.
 */
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { BaseControl, Button, Flex } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   [props.help]
 * @param {number}   props.value    Attachment id, 0 for none.
 * @param {Function} props.onChange Receives the new id (0 when removed).
 */
export function ImageField({ label, help, value, onChange }) {
    const id = useInstanceId(ImageField, 'hive-image-field');
    const media = useSelect((select) => (value ? select(coreStore).getMedia(value, { context: 'view' }) : null), [value]);
    const preview = media?.media_details?.sizes?.medium?.source_url || media?.source_url || '';

    return (
        <BaseControl __nextHasNoMarginBottom id={id} label={label} help={help}>
            {preview ? (
                <img src={preview} alt="" style={{ display: 'block', width: '100%', maxHeight: 140, marginBottom: 8, objectFit: 'contain' }} />
            ) : null}
            <MediaUploadCheck>
                <Flex justify="flex-start" gap={2}>
                    <MediaUpload
                        allowedTypes={['image']}
                        value={value || undefined}
                        onSelect={(picked) => onChange(picked?.id ? Number(picked.id) : 0)}
                        render={({ open }) => (
                            <Button id={id} variant="secondary" onClick={open}>
                                {value ? 'Cambia' : 'Scegli'}
                            </Button>
                        )}
                    />
                    {value ? (
                        <Button variant="tertiary" isDestructive onClick={() => onChange(0)}>
                            Rimuovi
                        </Button>
                    ) : null}
                </Flex>
            </MediaUploadCheck>
        </BaseControl>
    );
}
