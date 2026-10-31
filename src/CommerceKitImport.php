<?php
/**
 * What can be brought over from CommerceKit, part of Hive → Migrazione.
 * Its storage isn't documented, so this looks at what's in the database
 * (Core\Migrate\CommerceKit reads it) and reports everything it finds:
 *
 *   waitlist     a table with an email and a product column: each request
 *                not answered yet becomes a Hive wait (Hive → Iscritti),
 *                active at once, as CommerceKit's were (no confirmation
 *                asked of people who asked to be told). Addresses that
 *                switched Hive's alerts off are left alone.
 *   size guides  posts of a size-guide type: copied as drafts of Hive's
 *                size guides, to give categories and brands and publish.
 *   order bumps  the products in CommerceKit's settings: drafts of Hive's
 *                checkout offers, to check and publish.
 *   the rest     swatches, gallery, sticky bar, stock meter are settings:
 *                Hive has its own (Hive → Impostazioni); listed, not moved.
 *
 * Running it twice brings nothing twice.
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\Alerts as AlertRules;
use Hive\Core\Migrate\CommerceKit as Rules;
use Hive\Core\Sizes as SizeRules;
use Hive\Shop\Alerts;
use Hive\Shop\OrderBumps;
use Hive\Shop\SizeGuides;
use Hive\Shop\Store;

defined('ABSPATH') || exit;

final class CommerceKitImport
{
    /** Where an imported post came from, so it isn't imported twice. */
    private const FROM = '_hive_imported_from';

    /** @return list<string> */
    public static function run(bool $dry): array
    {
        $tables = self::tables();
        $lines  = [];
        foreach ($tables as $table => $columns) {
            $map = Rules::waitlist_columns($columns);
            if (null !== $map && str_contains(strtolower($table), 'wait')) {
                $lines[] = self::waitlist($table, $map, $dry);
                unset($tables[$table]);
            }
        }
        array_push($lines, ...self::guides($dry), ...self::bumps($dry));
        // What it can't bring is said in the dry run, where it is read.
        if ($tables && $dry) {
            $lines[] = 'CommerceKit, tabelle non lette (da controllare a mano): ' . implode(', ', array_map(static fn($table, $columns) => $table . ' (' . implode(', ', $columns) . ')', array_keys($tables), $tables));
        }
        $meta = $dry ? self::meta_keys() : [];
        if ($meta) {
            $lines[] = 'CommerceKit, dati sui prodotti non portati (Hive disegna swatch, galleria, barra e scorte da sé, vedi Hive → Impostazioni): ' . implode(', ', array_map(static fn($key, $n) => $key . ' × ' . $n, array_keys($meta), $meta));
        }
        return array_values(array_filter($lines));
    }

    /**
     * CommerceKit's tables and their columns.
     *
     * @return array<string, list<string>>
     */
    private static function tables(): array
    {
        global $wpdb;
        $found = [];
        foreach (['commercekit', 'ckit', 'cgkit'] as $part) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-time look at the schema.
            foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', '%' . $wpdb->esc_like($part) . '%')) as $table) {
                $table = (string) $table;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !Rules::is_commercekit($table)) {
                    continue;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a table name SHOW TABLES gave, checked above.
                $found[$table] = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM `{$table}`"));
            }
        }
        return $found;
    }

    /** @param array{email: string, product: string, parent: ?string, sent: ?string} $map */
    private static function waitlist(string $table, array $map, bool $dry): string
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a checked table name; one read.
        $rows = (array) $wpdb->get_results("SELECT * FROM `{$table}` LIMIT 50000", ARRAY_A);

        $by_email = [];
        $sent     = 0;
        $gone     = 0;
        foreach ($rows as $row) {
            if (null !== $map['sent'] && Rules::already_sent($row[$map['sent']] ?? null, $map['sent'])) {
                $sent++;
                continue;
            }
            $email = strtolower(trim((string) ($row[$map['email']] ?? '')));
            $entry = self::wait_entry((int) ($row[$map['product']] ?? 0), null !== $map['parent'] ? (int) ($row[$map['parent']] ?? 0) : 0);
            if (!is_email($email) || '' === $entry) {
                $gone++;
                continue;
            }
            $by_email[$email][] = $entry;
        }

        $added  = 0;
        $people = 0;
        $off    = 0;
        foreach ($by_email as $email => $entries) {
            $id = Alerts::subscriber($email);
            if ($id && 'off' === get_post_meta($id, '_hive_alerts', true)) {
                $off++;
                continue;
            }
            $current = $id ? array_map('strval', (array) get_post_meta($id, '_hive_wait', true)) : [];
            $new     = array_values(array_diff(array_unique($entries), $current));
            if (!$new) {
                continue;
            }
            $added += count($new);
            $people++;
            if ($dry) {
                continue;
            }
            $id = $id ?: Alerts::subscriber($email, true);
            if (!$id) {
                continue;
            }
            update_post_meta($id, '_hive_wait', AlertRules::waits($current, $new));
            if ('on' !== get_post_meta($id, '_hive_alerts', true)) {
                // Sizes waiting for a confirmation stay waiting: only what they asked CommerceKit for is active.
                update_post_meta($id, '_hive_alerts', 'on');
                update_post_meta($id, '_hive_confirmed_at', time());
            }
            add_post_meta($id, self::FROM, 'commercekit-waitlist', true);
        }

        if (!$added && !$off) {
            return '';
        }
        $notes = [];
        if ($sent) {
            $notes[] = $sent . ' già avvisate';
        }
        if ($gone) {
            $notes[] = $gone . ' su prodotti non più in negozio o senza email valida';
        }
        if ($off) {
            $notes[] = $off . ' persone con gli avvisi disattivati, lasciate così';
        }
        return sprintf(
            'CommerceKit, lista d’attesa (%s): %s %d richieste di %d persone%s',
            $table,
            $dry ? 'porterebbe' : 'portate',
            $added,
            $people,
            $notes ? ' (saltate: ' . implode('; ', $notes) . ')' : ''
        );
    }

    /** "812|42" for a variation in size 42 of product 812, "815|*" for a product without sizes; '' when it's gone. */
    private static function wait_entry(int $product_id, int $parent_id): string
    {
        $product = $product_id ? wc_get_product($product_id) : null;
        if (!$product instanceof \WC_Product && $parent_id) {
            $product = wc_get_product($parent_id);
        }
        if (!$product instanceof \WC_Product || 'trash' === $product->get_status()) {
            return '';
        }
        $key = AlertRules::ANY;
        if ($product->is_type('variation')) {
            $size    = (string) $product->get_attribute(Store::size_attribute());
            $key     = '' !== $size ? SizeRules::key($size) : AlertRules::ANY;
            $product = wc_get_product($product->get_parent_id());
            if (!$product instanceof \WC_Product) {
                return '';
            }
        }
        $entry = $product->get_id() . '|' . ('' !== $key ? $key : AlertRules::ANY);
        return AlertRules::is_wait($entry) ? $entry : '';
    }

    /** @return list<string> */
    private static function guides(bool $dry): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-time look at which post types exist.
        $types = Rules::guide_types(array_map('strval', (array) $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts}")));
        $lines = [];
        foreach ($types as $type) {
            $posts = get_posts(['post_type' => $type, 'post_status' => ['publish', 'draft', 'private', 'pending'], 'numberposts' => -1]);
            $new   = 0;
            $keys  = [];
            foreach ($posts as $post) {
                foreach (array_keys((array) get_post_meta($post->ID)) as $key) {
                    if (!str_starts_with((string) $key, '_edit_') && !str_starts_with((string) $key, '_wp_')) {
                        $keys[(string) $key] = true;
                    }
                }
                if (self::imported(SizeGuides::TYPE, $type . ':' . $post->ID)) {
                    continue;
                }
                $new++;
                if (!$dry) {
                    wp_insert_post(
                        [
                            'post_type'    => SizeGuides::TYPE,
                            'post_status'  => 'draft',
                            'post_title'   => $post->post_title,
                            'post_content' => wp_slash($post->post_content),
                            'meta_input'   => [self::FROM => $type . ':' . $post->ID],
                        ]
                    );
                }
            }
            if ($new) {
                $lines[] = sprintf(
                    'Guide alle taglie (%s): %s %d come bozze di Hive → Guide alle taglie: scegli categorie e marche, poi pubblica%s',
                    $type,
                    $dry ? 'porterebbe' : 'portate',
                    $new,
                    $keys ? '. Le regole di prima erano in: ' . implode(', ', array_keys($keys)) : ''
                );
            }
        }
        return $lines;
    }

    /** @return list<string> */
    private static function bumps(bool $dry): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-time look at CommerceKit's options.
        $names = array_map('strval', (array) $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 50", $wpdb->esc_like('commercekit') . '%')));
        $bumps = [];
        foreach ($names as $name) {
            $option = get_option($name);
            foreach (is_array($option) ? Rules::bumps($option) : [] as $bump) {
                $bumps[$bump['product']] ??= $bump;
            }
        }
        $new = 0;
        foreach ($bumps as $product => $bump) {
            if (!wc_get_product($product) || self::imported(OrderBumps::TYPE, 'commercekit:' . $product)) {
                continue;
            }
            $new++;
            if (!$dry) {
                wp_insert_post(
                    [
                        'post_type'   => OrderBumps::TYPE,
                        'post_status' => 'draft',
                        'post_title'  => '' !== $bump['headline'] ? $bump['headline'] : get_the_title($product),
                        'meta_input'  => [
                            self::FROM            => 'commercekit:' . $product,
                            '_hive_bump_product'  => $product,
                            '_hive_bump_cats'     => [],
                            '_hive_bump_headline' => $bump['headline'],
                            '_hive_bump_text'     => $bump['text'],
                            '_hive_bump_button'   => $bump['button'],
                        ],
                    ]
                );
            }
        }
        $lines = [];
        if ($new) {
            $lines[] = sprintf('CommerceKit, offerte al checkout: %s %d come bozze di Hive → Offerte al checkout: scegli le categorie, poi pubblica', $dry ? 'porterebbe' : 'portate', $new);
        }
        if ($names && !$bumps && $dry) {
            $lines[] = 'CommerceKit, impostazioni trovate (' . implode(', ', $names) . '): nessuna offerta al checkout leggibile, le altre impostazioni hanno il loro posto in Hive → Impostazioni';
        }
        return $lines;
    }

    private static function imported(string $type, string $from): bool
    {
        return (bool) get_posts(['post_type' => $type, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => self::FROM, 'meta_value' => $from]); // phpcs:ignore WordPress.DB.SlowDBQuery
    }

    /**
     * CommerceKit's product meta, with how many products have it.
     *
     * @return array<string, int>
     */
    private static function meta_keys(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a one-time count.
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT meta_key, COUNT(DISTINCT post_id) AS n FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s GROUP BY meta_key ORDER BY n DESC LIMIT 30", '%' . $wpdb->esc_like('commercekit') . '%', '%' . $wpdb->esc_like('ckit_') . '%'), ARRAY_A);
        $keys = [];
        foreach ($rows as $row) {
            if (Rules::is_commercekit((string) $row['meta_key'])) {
                $keys[(string) $row['meta_key']] = (int) $row['n'];
            }
        }
        return $keys;
    }
}
