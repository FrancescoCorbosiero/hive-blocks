/**
 * Media fields for the hero, carousel and call-to-action editors (bundled
 * into each edit.js): a picture or a video from the media library, with a
 * preview, "Sostituisci" and "Rimuovi"; and the focal points an image can
 * have (Core\Hero::POSITIONS).
 */
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { BaseControl, Button, Flex } from '@wordpress/components';

export const POSITIONS = [
    { label: 'Centro', value: 'center center' },
    { label: 'Sinistra', value: 'left center' },
    { label: 'Destra', value: 'right center' },
    { label: 'Alto', value: 'center top' },
    { label: 'Basso', value: 'center bottom' },
    { label: 'Alto a sinistra', value: 'left top' },
    { label: 'Alto a destra', value: 'right top' },
    { label: 'Basso a sinistra', value: 'left bottom' },
    { label: 'Basso a destra', value: 'right bottom' },
];

/**
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   [props.help]
 * @param {string}   props.url      The file's URL ('' for none).
 * @param {number}   [props.id]     Its media library id.
 * @param {Function} props.onChange Receives { id, url }; { id: 0, url: '' } to remove.
 * @param {string}   [props.type]   'image' or 'video'.
 */
export function MediaControl({ label, help, url, id = 0, onChange, type = 'image' }) {
    return (
        <div className="hive-media-control">
            <BaseControl.VisualLabel>{label}</BaseControl.VisualLabel>
            {url && type === 'image' && (
                <img src={url} alt="" style={{ display: 'block', width: '100%', maxHeight: 160, margin: '8px 0', objectFit: 'cover', borderRadius: 2 }} />
            )}
            {url && type === 'video' && <p style={{ margin: '8px 0', wordBreak: 'break-all', fontSize: 12 }}>{url}</p>}
            <MediaUploadCheck>
                <MediaUpload
                    allowedTypes={[type]}
                    value={id || undefined}
                    onSelect={(media) => onChange({ id: Number(media?.id) || 0, url: media?.url || '' })}
                    render={({ open }) => (
                        <Flex justify="flex-start" gap={2} style={{ marginTop: 8 }}>
                            <Button variant="secondary" size="compact" onClick={open}>
                                {url ? 'Sostituisci' : type === 'video' ? 'Scegli un video' : 'Scegli un’immagine'}
                            </Button>
                            {url && (
                                <Button variant="tertiary" size="compact" isDestructive onClick={() => onChange({ id: 0, url: '' })}>
                                    Rimuovi
                                </Button>
                            )}
                        </Flex>
                    )}
                />
            </MediaUploadCheck>
            {help && <p style={{ margin: '8px 0 0', color: '#757575', fontSize: 12 }}>{help}</p>}
        </div>
    );
}
