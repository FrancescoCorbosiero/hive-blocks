<?php

// The Store Hub bridge's pure core (Core\HubNames, HubRail, HubFields, HubPage): the cases of
// golden-hive-blocks' tests/hub-rails-test.php, on the store's real homepage as the switch-over
// leaves it (fixtures/hub-homepage.html: its golden-hive/* blocks as hive/*, [gh_product_rail]
// as [hive_product_rail]). The ordering cases of that file are in RailOrderTest.php. The block
// parser is WordPress core's, verbatim (wp/, GPL-2.0-or-later), so paths are proven on the
// parse WordPress makes.

use Hive\Core\HubFields;
use Hive\Core\HubNames;
use Hive\Core\HubPage;
use Hive\Core\HubRail;
use Hive\Core\RailOrder;

require_once __DIR__ . '/wp/class-wp-block-parser.php';

(static function (): void {
    $parse = static fn(string $content): array => (new WP_Block_Parser())->parse($content);

    // WordPress core's serialize_block_attributes() (wp-includes/blocks.php), json_encode for wp_json_encode.
    $serialize = static fn(array $attrs): string => strtr(
        (string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        [
            '\\\\' => '\\u005c',
            '--'   => '\\u002d\\u002d',
            '<'    => '\\u003c',
            '>'    => '\\u003e',
            '&'    => '\\u0026',
            '\\"'  => '\\u0022',
        ]
    );
    $delimiter = static fn(array $block): string => HubPage::block_delimiter($block['blockName'], $block['attrs'], $serialize);

    $html   = (string) file_get_contents(__DIR__ . '/fixtures/hub-homepage.html');
    $blocks = $parse($html);

    /* Reading the page. */

    $rails = HubPage::rails($blocks);
    check(
        array_keys($rails),
        [
            'category:featured-sneakers-originali-streetwear#0',
            'category:saldi-sneakers-outlet#0',
            'category:saldi-sneakers-in-offerta#0',
            'category:new-nuove-release#0',
            'brand:nike-off-white#0',
            'brand:nike-air-force-1#0',
            'brand:adidas#0',
            'brand:new-balance#0',
            'brand:asics#0',
        ],
        'the homepage has its nine sections, in page order'
    );
    check($rails['category:saldi-sneakers-outlet#0']['path'], '8.1', 'SALDI sits inside the first group (inner blocks carry no whitespace entries)');
    check($rails['brand:asics#0']['path'], '12.2', 'ASICS sits inside the second group');

    $saldi = $rails['category:saldi-sneakers-outlet#0']['rail'];
    check($saldi['atts']['limit'], '18', 'rail attributes are read');
    check($saldi['fallback'], 'menu_order', 'a category rail falls back to the custom order');
    check($saldi['pin'], [], 'no pins yet');

    $leaves = HubPage::leaf_blocks($blocks);
    $names  = array_map(static fn($leaf) => $leaf[1]['blockName'], $leaves);
    check(count($leaves), 19, 'every leaf block is listed');
    check($names[0], 'core/html', 'the style block comes first');
    check(in_array('core/group', $names, true), false, 'groups are walked, not listed');

    $hero = HubPage::block_summary($leaves[1][1]);
    check($hero['items'], 5, 'the hero summary counts its slides');
    check($hero['labels'][0], 'AP x SWATCH', 'the hero summary names its slides');
    check(HubPage::block_summary($leaves[3][1])['title'], '[wd_hustle id="3" type="embedded"/]', 'a shortcode block is summarised by its shortcode');

    check(HubPage::block_at_path($blocks, '8.1')['attrs']['title'], 'SALDI', 'paths address parse_blocks output');
    check(HubPage::block_at_path($blocks, '99'), null, 'a missing path is null');
    check(HubPage::block_at_path($blocks, '8.x'), null, 'a malformed path is null');

    /* Shortcodes. */

    foreach ($rails as $key => $entry) {
        $parsed = HubRail::parse_shortcode($entry['rail']['shortcode']);
        check(HubRail::build_shortcode($parsed['tag'], $parsed['atts']), $entry['rail']['shortcode'], "shortcode round-trips unchanged ({$key})");
    }
    check(
        HubRail::parse_shortcode('[hive_product_rail brand=\'adidas\' limit=15 "x"]'),
        ['tag' => 'hive_product_rail', 'atts' => ['brand' => 'adidas', 'limit' => '15', 0 => 'x']],
        'single-quoted, bare and positional attributes are read like WordPress reads them'
    );
    check(HubRail::parse_shortcode('text [hive_product_rail]'), null, 'text around a shortcode is refused');
    check(HubRail::parse_shortcode('[a][b]'), null, 'two shortcodes are refused');
    check(HubRail::build_shortcode('hive_product_rail', ['title' => 'say "hi"']), null, 'a value with a quote cannot be written');

    $with_pins = HubRail::set_atts($saldi['shortcode'], ['pin' => [1201, 877, '1543', 'x', 877], 'exclude' => [990], 'fallback' => 'popularity']);
    check(
        $with_pins,
        '[hive_product_rail category="saldi-sneakers-outlet" limit="18" columns="4" columns_tablet="3" pin="1201,877,1543" exclude="990" fallback="popularity"]',
        'pins, exclusions and the fallback are appended, everything else untouched'
    );
    check(HubRail::set_atts($with_pins, ['pin' => [], 'exclude' => [], 'fallback' => 'menu_order']), $saldi['shortcode'], 'clearing everything gives back the original shortcode');
    check(
        HubRail::set_atts($with_pins, ['pin' => [877, 1201], 'exclude' => [990], 'fallback' => 'popularity']),
        '[hive_product_rail category="saldi-sneakers-outlet" limit="18" columns="4" columns_tablet="3" pin="877,1201" exclude="990" fallback="popularity"]',
        'rewriting existing values keeps their place'
    );
    check(HubRail::set_atts($saldi['shortcode'], ['pin' => [], 'exclude' => [], 'fallback' => 'nonsense']), $saldi['shortcode'], 'an unknown fallback means the default');
    check(HubRail::set_atts('[wd_hustle id="3"]', []), null, 'only hive_product_rail is rewritten');
    check(HubRail::set_atts('[gh_product_rail category="saldi"]', ['pin' => [1]]), null, 'not golden-hive-blocks\' rail either: Hive doesn\'t render it');

    /* Writing one block back. */

    // Identity: every block's delimiter is found exactly once as serialized,
    // the precondition for touching any of them.
    foreach ($rails as $key => $entry) {
        $result = HubPage::replace_block_attrs($html, $entry['block']['blockName'], $entry['block']['attrs'], $entry['block']['attrs'], $serialize);
        check($result['ok'], true, "rail block located exactly once ({$key})");
        check($result['content'] ?? null, $html, "an identity rewrite changes nothing ({$key})");
    }

    $saldi_block            = $rails['category:saldi-sneakers-outlet#0']['block'];
    $saldi_path             = $rails['category:saldi-sneakers-outlet#0']['path'];
    $new_attrs              = $saldi_block['attrs'];
    $new_attrs['shortcode'] = $with_pins;
    $written                = HubPage::replace_block_attrs($html, $saldi_block['blockName'], $saldi_block['attrs'], $new_attrs, $serialize);
    check($written['ok'], true, 'the SALDI rail can be rewritten');

    $after_blocks = $parse($written['content']);
    $after_rails  = HubPage::rails($after_blocks);
    check($after_rails['category:saldi-sneakers-outlet#0']['rail']['pin'], [1201, 877, 1543], 'the new pins read back');
    check($after_rails['category:saldi-sneakers-outlet#0']['rail']['fallback'], 'popularity', 'the new fallback reads back');

    // Nothing else moved: every other leaf block is the same parse.
    $before_leaves = HubPage::leaf_blocks($blocks);
    $after_leaves  = HubPage::leaf_blocks($after_blocks);
    check(count($after_leaves), count($before_leaves), 'the page keeps its blocks');
    foreach ($before_leaves as $i => $leaf) {
        if ($saldi_path !== $leaf[0]) {
            check($after_leaves[$i], $leaf, 'untouched block ' . $leaf[0] . ' is unchanged');
        }
    }
    check(
        strlen($written['content']) - strlen($html),
        strlen($serialize($new_attrs)) - strlen($serialize($saldi_block['attrs'])),
        'the content grew by exactly the serialized attribute change'
    );

    // The same rail twice on a page: refusing beats guessing.
    $doubled   = $html . "\n\n" . '<!-- wp:hive/shortcode-wrapper ' . $serialize($saldi_block['attrs']) . ' /-->';
    $ambiguous = HubPage::replace_block_attrs($doubled, $saldi_block['blockName'], $saldi_block['attrs'], $new_attrs, $serialize);
    check($ambiguous['ok'], false, 'a block present twice is not rewritten');
    check($ambiguous['error'], 'ambiguous', 'and the refusal says why');
    check(HubPage::replace_block_attrs($html, $saldi_block['blockName'], ['shortcode' => 'gone'], $new_attrs, $serialize)['error'], 'not_found', 'a block that is not there is not rewritten');

    check(HubPage::attrs_hash($saldi_block['attrs']), HubPage::attrs_hash($parse($html)[8]['innerBlocks'][1]['attrs']), 'the attribute hash is stable across parses');
    check(HubPage::attrs_hash($saldi_block['attrs']) !== HubPage::attrs_hash($new_attrs), true, 'the attribute hash sees a change');

    /* Rail size and editable fields. */

    $saldi_shortcode = $saldi_block['attrs']['shortcode'];
    $nothing         = ['pin' => [], 'exclude' => [], 'fallback' => ''];
    $resized         = (string) HubRail::set_atts($saldi_shortcode, $nothing + ['limit' => 24]);
    check(HubRail::parse_shortcode($resized)['atts']['limit'], '24', 'limit is written when asked');
    $kept_before = HubRail::parse_shortcode($saldi_shortcode)['atts'];
    $kept_after  = HubRail::parse_shortcode($resized)['atts'];
    unset($kept_before['limit'], $kept_after['limit']);
    check($kept_after, $kept_before, 'resizing keeps every other rail attribute');
    check(HubRail::set_atts($saldi_shortcode, $nothing), $saldi_shortcode, 'without limit the rail is untouched');

    // Stand-in for WordPress's sanitizers (Hub\Fields::clean() passes the real ones).
    $clean = static function (string $type, string $value): string {
        if ('url' === $type) {
            $value = trim($value);
            return ('' === $value || preg_match('#^(https?://|/)#', $value)) ? $value : '';
        }
        return 'enum' === $type ? trim($value) : trim(strip_tags($value));
    };
    $wrap = HubFields::SPECS['hive/shortcode-wrapper'];
    $defs = ['eyebrow' => '', 'title' => '', 'backgroundColor' => 'white', 'buttonText' => '', 'buttonUrl' => ''];

    $read = HubFields::read($saldi_block['attrs'], $wrap, $defs);
    check($read['title'], 'SALDI', 'fields read the saved title');
    check($read['eyebrow'], 'Saldi primaverili', 'and the saved eyebrow');
    check($read['buttonUrl'], $saldi_block['attrs']['buttonUrl'] ?? '', 'an unsaved field reads as its default');
    check($read['backgroundColor'], 'white', 'a default that is not empty too');
    check(isset($wrap['shortcode']), false, 'the shortcode itself is never an editable field');

    $applied = HubFields::apply($saldi_block['attrs'], ['title' => '  Saldi <b>d\'autunno</b> ', 'eyebrow' => 'Fino al -50%'], $wrap, $defs, $clean);
    check($applied['ok'], true, 'text fields apply');
    check($applied['attrs']['title'], "Saldi d'autunno", 'text is cleaned before it is written');
    check($applied['changed'], ['title', 'eyebrow'], 'the changed fields are reported');
    check($applied['attrs']['shortcode'], $saldi_shortcode, 'editing texts leaves the rail alone');

    $refused = [
        'not_editable'  => ['shortcode' => '[hive_product_rail ids="1"]'],
        'not_text'      => ['title' => ['x']],
        'not_an_option' => ['backgroundColor' => 'pink'],
        'bad_url'       => ['buttonUrl' => 'javascript:alert(1)'],
        'too_long'      => ['title' => str_repeat('x', 121)],
    ];
    foreach ($refused as $reason => $change) {
        $result = HubFields::apply($saldi_block['attrs'], $change, $wrap, $defs, $clean);
        check($result['ok'] ? 'ok' : $result['error'], $reason, "refused: {$reason}");
    }
    check(HubFields::apply($saldi_block['attrs'], ['title' => str_repeat('è', 120)], $wrap, $defs, $clean)['ok'], true, 'the length counts letters, not bytes');

    $reset = HubFields::apply(['backgroundColor' => 'gray'], ['backgroundColor' => 'white'], $wrap, $defs, $clean);
    check(array_key_exists('backgroundColor', $reset['attrs']), false, 'a field set back to its default is removed, not written');
    $noop = HubFields::apply($saldi_block['attrs'], ['title' => 'SALDI'], $wrap, $defs, $clean);
    check($noop['changed'], [], 'writing the current value changes nothing');
    check($noop['attrs'], $saldi_block['attrs'], 'and leaves the attributes identical');

    // On the real page: new texts for SALDI rewrite that block and nothing else.
    $texts    = HubFields::apply($saldi_block['attrs'], ['title' => 'SALDI AUTUNNO', 'buttonText' => 'Vedi tutti', 'buttonUrl' => '/saldi'], $wrap, $defs, $clean);
    $retitled = HubPage::replace_block_attrs($html, $saldi_block['blockName'], $saldi_block['attrs'], $texts['attrs'], $serialize);
    check($retitled['ok'], true, 'field edits are written into the page');
    $reread = HubPage::block_at_path($parse($retitled['content']), $saldi_path);
    check($reread['attrs']['title'], 'SALDI AUTUNNO', 'the page now carries the new title');
    check($reread['attrs']['buttonUrl'], '/saldi', 'and the new button link');
    check($reread['attrs']['shortcode'], $saldi_shortcode, 'and the same rail');
    foreach (HubPage::leaf_blocks($parse($retitled['content'])) as $i => $leaf) {
        if ($leaf[0] !== $saldi_path) {
            check($leaf, $before_leaves[$i], 'untouched by the retitle: ' . $leaf[0]);
        }
    }

    /* The section's switch. */

    $defs_hidden = $defs + ['hidden' => ''];
    $off         = HubFields::apply($saldi_block['attrs'], ['hidden' => 'yes'], $wrap, $defs_hidden, $clean);
    check($off['attrs']['hidden'] ?? null, 'yes', 'a section can be switched off');
    check($off['attrs']['shortcode'], $saldi_shortcode, 'switching off keeps the rail as it is');
    $on = HubFields::apply($off['attrs'], ['hidden' => ''], $wrap, $defs_hidden, $clean);
    check(array_key_exists('hidden', $on['attrs']), false, 'switched on again, the attribute goes (it is the default)');
    check($on['attrs'], $saldi_block['attrs'], 'and the block is as it was');
    check(HubFields::apply($saldi_block['attrs'], ['hidden' => 'no'], $wrap, $defs_hidden, $clean)['error'] ?? null, 'not_an_option', 'the switch takes "yes" or nothing');

    /* What a section shows. */

    check(HubRail::parse_source(['taxonomy' => 'category', 'slug' => 'saldi']), ['kind' => 'term', 'taxonomy' => 'category', 'slug' => 'saldi'], 'a category source is read');
    check(HubRail::parse_source(['taxonomy' => 'brand', 'slug' => 'nike-dunk']), ['kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'nike-dunk'], 'a brand source is read');
    check(HubRail::parse_source(['type' => 'Best_Selling ']), ['kind' => 'type', 'type' => 'best_selling'], 'a shop-wide list is read, any case');
    check(HubRail::parse_source(['taxonomy' => 'category', 'slug' => 'caff%c3%a8']), ['kind' => 'term', 'taxonomy' => 'category', 'slug' => 'caff%c3%a8'], 'a slug with an accented letter, as WordPress escapes it, is read');
    $malformed = [
        'no source'          => null,
        'unknown taxonomy'   => ['taxonomy' => 'product_cat', 'slug' => 'saldi'],
        'empty slug'         => ['taxonomy' => 'category', 'slug' => ''],
        'a quote'            => ['taxonomy' => 'category', 'slug' => 'a"b'],
        'a bracket'          => ['taxonomy' => 'brand', 'slug' => 'nike]'],
        'a space'            => ['taxonomy' => 'tag', 'slug' => 'two words'],
        'unknown list'       => ['type' => 'random'],
        'a list that is not' => ['type' => ['recent']],
    ];
    foreach ($malformed as $what => $raw) {
        check(HubRail::parse_source($raw), null, "a source is refused: {$what}");
    }

    $offerta = $rails['category:saldi-sneakers-in-offerta#0']['rail'];
    $as_dunk = (string) HubRail::set_source((string) $with_pins, ['kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'nike-dunk']);
    check($as_dunk, '[hive_product_rail brand="nike-dunk" limit="18" columns="4" columns_tablet="3"]', 'a new source takes the old one\'s place; pins, exclusions and fallback go with the old products');
    check(
        HubRail::set_source($offerta['shortcode'], ['kind' => 'type', 'type' => 'best_selling']),
        '[hive_product_rail type="best_selling" limit="18" columns="4" columns_tablet="3"]',
        'a section can show a shop-wide list'
    );
    check(
        HubRail::set_source('[hive_product_rail type="sale" category="saldi" limit="9"]', ['kind' => 'term', 'taxonomy' => 'tag', 'slug' => 'estate']),
        '[hive_product_rail tag="estate" limit="9"]',
        'every attribute that said what the rail showed goes, a type alongside a category too'
    );
    check(
        HubRail::set_source('[hive_product_rail limit="9"]', ['kind' => 'term', 'taxonomy' => 'category', 'slug' => 'saldi']),
        '[hive_product_rail category="saldi" limit="9"]',
        'a rail that named no source gets one, first'
    );
    check(HubRail::set_source('[gh_size_rail limit="9"]', ['kind' => 'type', 'type' => 'recent']), null, 'only hive_product_rail is pointed elsewhere');
    check(
        HubRail::set_atts($as_dunk, ['pin' => [5, 6], 'exclude' => [], 'fallback' => 'date']),
        '[hive_product_rail brand="nike-dunk" limit="18" columns="4" columns_tablet="3" pin="5,6" fallback="date"]',
        'the new products\' pins and fallback are written over the new source'
    );
    check(
        RailOrder::default_fallback(HubRail::parse_shortcode((string) HubRail::set_source($saldi['shortcode'], ['kind' => 'type', 'type' => 'recent']))['atts']),
        'date',
        'a shop-wide list of the newest falls back to the newest'
    );

    /* A new section. */

    check(HubRail::clean_layout_value('columns', '5'), '5', 'columns 1-8 are kept');
    check(HubRail::clean_layout_value('columns', '9'), null, 'columns past 8 are refused');
    check(HubRail::clean_layout_value('ratio', '3/4'), '3/4', 'a ratio is kept');
    check(HubRail::clean_layout_value('ratio', '3/4"]'), null, 'a ratio that breaks out is refused');
    check(HubRail::clean_layout_value('fit', 'Cover'), 'cover', 'a fit is kept');
    check(HubRail::clean_layout_value('columns', ['5']), null, 'a value that is not one is refused');

    $new_shortcode = (string) HubRail::new_shortcode(['kind' => 'term', 'taxonomy' => 'brand', 'slug' => 'jordan'], 12, $saldi['atts'], [], []);
    check($new_shortcode, '[hive_product_rail brand="jordan" limit="12" columns="4" columns_tablet="3"]', 'a new rail takes its neighbour\'s layout');
    check(
        HubRail::new_shortcode(['kind' => 'type', 'type' => 'sale'], 500, $saldi['atts'], ['columns' => '5', 'fit' => 'cover', 'ratio' => 'x'], ['pin' => [3, 1], 'fallback' => 'popularity']),
        '[hive_product_rail type="sale" limit="100" columns="5" columns_tablet="3" fit="cover" pin="3,1" fallback="popularity"]',
        'its own layout wins over its neighbour\'s, a bad value is left out, the size is capped, its order is written'
    );

    $texts_new = HubFields::apply([], ['title' => 'JORDAN', 'eyebrow' => 'Air Jordan 1, 4, 11', 'backgroundColor' => 'gray'], $wrap, $defs_hidden, $clean);
    $jordan    = ['blockName' => HubRail::BLOCK, 'attrs' => HubRail::new_attrs($new_shortcode, $texts_new['attrs'])];
    check(array_keys($jordan['attrs']), ['eyebrow', 'title', 'shortcode', 'backgroundColor'], 'a new rail block lists its attributes as the editor does');

    $inserted = HubPage::insert_block($html, $saldi_block, 'after', $jordan, $serialize);
    check($inserted['ok'], true, 'a section is added after SALDI');
    $inserted_blocks = $parse($inserted['content']);
    $inserted_rails  = HubPage::rails($inserted_blocks);
    check(count($inserted_rails), 10, 'the page has one more rail');
    check($inserted_rails['brand:jordan#0']['path'], '8.2', 'right after SALDI, inside the same group');
    check($inserted_rails['brand:jordan#0']['block']['attrs'], $jordan['attrs'], 'with exactly the attributes written');
    check($inserted_rails['category:saldi-sneakers-in-offerta#0']['path'], '8.3', 'the next section moved one place down');
    check(count(HubPage::leaf_blocks($inserted_blocks)), count($before_leaves) + 1, 'every other block is still there');
    check(
        $inserted['content'],
        str_replace($delimiter($saldi_block), $delimiter($saldi_block) . "\n\n" . $delimiter($jordan), $html),
        'and nothing but the new block and its blank line was added'
    );

    $tendenza_block = $rails['category:featured-sneakers-originali-streetwear#0']['block'];
    $first          = HubPage::rails($parse(HubPage::insert_block($html, $tendenza_block, 'before', $jordan, $serialize)['content']));
    check($first['brand:jordan#0']['path'], '8.0', 'a section can go first');
    check(array_key_first($first), 'brand:jordan#0', 'and is the first rail of the page');

    $asics_block = $rails['brand:asics#0']['block'];
    $last        = HubPage::insert_block($html, $asics_block, 'after', $jordan, $serialize);
    check(HubPage::rails($parse($last['content']))['brand:jordan#0']['path'], '12.3', 'or last, still inside the group');
    check(str_contains($last['content'], $delimiter($jordan) . '</div>'), true, 'closing the group right after it');

    check(HubPage::insert_block($html, $tendenza_block, 'after', $saldi_block, $serialize)['error'] ?? null, 'duplicate', 'a section identical to one on the page is refused');
    check(HubPage::insert_block($html, ['blockName' => HubRail::BLOCK, 'attrs' => ['shortcode' => 'gone']], 'after', $jordan, $serialize)['error'] ?? null, 'not_found', 'next to a block that is not there: refused');
    check(HubPage::insert_block($doubled, $saldi_block, 'after', $jordan, $serialize)['error'] ?? null, 'ambiguous', 'next to a block present twice: refused');

    /* Taking a section away. */

    $removed = HubPage::remove_block($html, $saldi_block, $serialize);
    check($removed['ok'], true, 'SALDI can be taken off the page');
    $removed_rails = HubPage::rails($parse($removed['content']));
    check(isset($removed_rails['category:saldi-sneakers-outlet#0']), false, 'SALDI is gone');
    check(count($removed_rails), 8, 'the other eight rails stay');
    check($removed_rails['category:saldi-sneakers-in-offerta#0']['path'], '8.1', 'the next section moved up into its place');
    check(strlen($html) - strlen($removed['content']), strlen($delimiter($saldi_block)) + 2, 'exactly the block and one blank line went');
    check(HubPage::insert_block($removed['content'], $tendenza_block, 'after', $saldi_block, $serialize)['content'] ?? null, $html, 'put back after its old neighbour, the page is byte for byte as before');

    $no_af1 = HubPage::remove_block($html, $rails['brand:nike-air-force-1#0']['block'], $serialize);
    check(str_contains($no_af1['content'], $delimiter($rails['brand:nike-off-white#0']['block']) . '</div>'), true, 'the last section of a group goes with the blank line before it');
    check(count(HubPage::rails($parse($no_af1['content']))), 8, 'and the page parses with eight rails');
    check(HubPage::remove_block($doubled, $saldi_block, $serialize)['error'] ?? null, 'ambiguous', 'a block present twice is not taken away');

    /* The sections in another order. */

    $slots    = array_values(array_map(static fn($entry) => $entry['block'], $rails));
    $keys     = array_keys($rails);
    $paths    = array_values(array_map(static fn($entry) => $entry['path'], $rails));
    $order    = [3, 0, 1, 2, 4, 5, 6, 7, 8]; // NUOVI ARRIVI first, the rest as they were.
    $arranged = HubPage::arrange_blocks($html, $slots, $order, $serialize);
    check($arranged['ok'], true, 'the sections can be put in another order');
    $arranged_blocks = $parse($arranged['content']);
    $arranged_rails  = HubPage::rails($arranged_blocks);
    check(array_keys($arranged_rails), array_map(static fn($i) => $keys[$i], $order), 'they read back in the new order');
    check(array_values(array_map(static fn($entry) => $entry['path'], $arranged_rails)), $paths, 'in the same places of the page: no section left its group');
    check(HubPage::block_at_path($arranged_blocks, '8.3')['blockName'], 'hive/category-slider', 'the category slider did not move');
    foreach (HubPage::leaf_blocks($arranged_blocks) as $i => $leaf) {
        if (null === HubRail::from_block($leaf[1])) {
            check($leaf, $before_leaves[$i], 'not a section, not moved: ' . $leaf[0]);
        }
    }
    check(strlen($arranged['content']), strlen($html), 'the page is as long as before');
    $back = HubPage::arrange_blocks($arranged['content'], array_map(static fn($i) => $slots[$i], $order), [1, 2, 3, 0, 4, 5, 6, 7, 8], $serialize);
    check($back['content'] ?? null, $html, 'arranged back, the page is byte for byte as before');
    $reversed = HubPage::arrange_blocks($html, $slots, array_reverse(range(0, 8)), $serialize);
    check(array_keys(HubPage::rails($parse($reversed['content']))), array_reverse($keys), 'any order works, across groups too');
    check(HubPage::arrange_blocks($html, $slots, [0, 0, 1, 2, 3, 4, 5, 6, 7], $serialize)['error'], 'not_a_permutation', 'a rail twice is refused');
    check(HubPage::arrange_blocks($html, $slots, [0, 1], $serialize)['error'], 'not_a_permutation', 'every rail must be placed');
    check(HubPage::arrange_blocks($doubled, $slots, range(0, 8), $serialize)['error'], 'ambiguous', 'a rail present twice is not moved');

    /* What is a section and what isn't. */

    $wrapper = static fn(mixed $shortcode): array => ['blockName' => HubRail::BLOCK, 'attrs' => ['shortcode' => $shortcode], 'innerBlocks' => []];
    check(HubRail::from_block($wrapper('[hive_product_rail category="saldi"]'))['key_base'] ?? null, 'category:saldi', 'a wrapper holding the rail is a section');
    check(HubRail::from_block($wrapper('[hive_product_rail]'))['key_base'] ?? null, 'type:recent', 'one that names nothing shows the newest');
    check(HubRail::from_block($wrapper('[hive_product_rail ids="4,2"]'))['key_base'] ?? null, 'ids', 'one with a fixed list is a section too');
    check(HubRail::from_block($wrapper('[hive_bank_details]')), null, 'a wrapper holding another shortcode is not a section');
    check(HubRail::from_block($wrapper('[gh_product_rail category="saldi"]')), null, 'nor one still holding golden-hive-blocks\' rail, which Hive doesn\'t render');
    check(HubRail::from_block($wrapper(['[hive_product_rail]'])), null, 'nor one whose shortcode is not text');
    check(HubRail::from_block(['blockName' => 'hive/product-rail', 'attrs' => ['category' => 'saldi']]), null, 'the Rail di prodotti block is not a section');

    // The page's revisions from before the switch-over hold golden-hive-blocks' names: the history
    // finds the same sections there, under the same keys.
    $legacy_html = str_replace(['wp:hive/', '[hive_product_rail'], ['wp:golden-hive/', '[gh_product_rail'], $html);
    check(HubPage::rails($parse($legacy_html)), [], 'on the live page, golden-hive-blocks\' sections are not sections');
    $legacy = HubPage::rails($parse($legacy_html), true);
    check(array_keys($legacy), $keys, 'in an old revision, the same sections under the same keys');
    check([$legacy['category:saldi-sneakers-outlet#0']['rail']['fallback'], $legacy['category:saldi-sneakers-outlet#0']['rail']['pin']], ['menu_order', []], 'read the same way');
    check(array_keys(HubPage::rails($blocks, true)), $keys, 'and Hive\'s own count there too');

    /* The names the app knows. */

    check(HubNames::block_to_app('hive/shortcode-wrapper'), 'golden-hive/shortcode-wrapper', 'a section is reported by its golden-hive-blocks name');
    check(
        array_map([HubNames::class, 'block_to_app'], array_keys(HubFields::SPECS)),
        ['golden-hive/shortcode-wrapper', 'golden-hive/category-slider', 'golden-hive/brand-marquee', 'golden-hive/faq-schema', 'golden-hive/social-proof', 'golden-hive/whatsapp-button'],
        'the editable blocks, as the app knows them'
    );
    check(HubNames::block_to_app('hive/hero-carousel'), 'golden-hive/hero-carousel', 'every Hive block, editable or not');
    check(HubNames::block_to_app('core/group'), 'core/group', 'core blocks keep their names');
    check(HubNames::block_to_app('golden-hive/faq-schema'), 'golden-hive/faq-schema', 'a name that is already the app\'s stays');
    check(HubNames::block_from_app('golden-hive/category-slider'), 'hive/category-slider', 'a write naming the old block reaches Hive\'s');
    check(HubNames::block_from_app('hive/category-slider'), 'hive/category-slider', 'a write naming Hive\'s block too');
    check(HubNames::block_from_app('core/html'), 'core/html', 'and a core block stays itself');
    foreach (array_keys(HubFields::SPECS) as $name) {
        check(HubNames::block_from_app(HubNames::block_to_app($name)), $name, "the map goes both ways ({$name})");
    }
    check(
        HubNames::shortcode_to_app((string) $with_pins),
        '[gh_product_rail category="saldi-sneakers-outlet" limit="18" columns="4" columns_tablet="3" pin="1201,877,1543" exclude="990" fallback="popularity"]',
        'a rail\'s shortcode is reported with the tag the app knows, nothing else changed'
    );
    check(HubNames::shortcode_to_app(' [hive_product_rail]'), ' [gh_product_rail]', 'one without attributes too');
    check(HubNames::shortcode_to_app('[hive_product_rails category="x"]'), '[hive_product_rails category="x"]', 'a tag that only starts the same is left alone');
    check(HubNames::shortcode_to_app('[wd_hustle id="3"]'), '[wd_hustle id="3"]', 'and so is any other shortcode');
    check(HubNames::shortcode_to_app('Novità [hive_product_rail]'), 'Novità [hive_product_rail]', 'only a shortcode the text starts with is renamed');
})();
