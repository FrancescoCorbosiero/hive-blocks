/**
 * Terms of a taxonomy picked by name and kept as slugs, comma separated, in
 * the order picked: the same field as the product rail's categories and
 * brands (blocks/product-rail/edit.js).
 *
 * Imported by path by the blocks that need it, so it ships inside their
 * editor scripts. It belongs in @hive/editor (index.js) next to ListControl.
 */
import { FormTokenField } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

const list = (value) => (value ? value.split(',').filter(Boolean) : []);

/** Terms of a taxonomy, as slug → name (empty while loading or when the taxonomy isn't in the REST API). */
export function useTerms(taxonomy) {
    return useSelect(
        (select) => {
            const terms = select(coreStore).getEntityRecords('taxonomy', taxonomy, { per_page: 100, hide_empty: false, _fields: 'id,slug,name' });
            return Object.fromEntries((terms || []).map((term) => [term.slug, term.name]));
        },
        [taxonomy]
    );
}

/**
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   [props.help]
 * @param {string}   props.taxonomy
 * @param {string}   props.value    Slugs, comma separated.
 * @param {Function} props.onChange Receives the new slugs, comma separated.
 */
export function TermsField({ label, help, taxonomy, value, onChange }) {
    const terms = useTerms(taxonomy);
    return (
        <FormTokenField
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={label}
            value={list(value)}
            suggestions={Object.keys(terms)}
            displayTransform={(slug) => terms[slug] || slug}
            onChange={(tokens) => onChange(tokens.map(String).join(','))}
            __experimentalExpandOnFocus
            __experimentalShowHowTo={false}
            help={help}
        />
    );
}
