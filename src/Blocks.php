<?php
/**
 * Registers the blocks in blocks/ from blocks/blocks-manifest.php (every
 * block.json in one PHP array, written by the build), gets their
 * stylesheets ready (hive-base first, a content hash as the version), and
 * leaves out the ones hidden in the editor.
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\BlockScan;

defined('ABSPATH') || exit;

final class Blocks
{
    public static function register(): void
    {
        add_action('init', [self::class, 'register_blocks']);
        add_filter('block_categories_all', [self::class, 'category']);
        add_filter('pre_render_block', [self::class, 'skip_hidden'], 10, 2);
    }

    /**
     * A Hive block hidden in the editor isn't rendered at all. WordPress
     * (6.9+) renders a hidden block and then drops its HTML: by then the
     * block's CSS and script are on the page, and what it adds elsewhere
     * (the FAQ's structured data) stays. On 6.8, which doesn't hide blocks,
     * markup pasted from a newer site stays hidden too.
     *
     * @param mixed $pre   null, or the HTML another filter already chose.
     * @param mixed $block The parsed block.
     */
    public static function skip_hidden(mixed $pre, mixed $block): mixed
    {
        $name = is_array($block) ? (string) ($block['blockName'] ?? '') : '';
        return null === $pre && str_starts_with($name, 'hive/') && BlockScan::hidden($block) ? '' : $pre;
    }

    public static function register_blocks(): void
    {
        // WordPress 6.8+: the plugin header requires it, this keeps an old site from a fatal error.
        if (!function_exists('wp_register_block_types_from_metadata_collection')) {
            return;
        }
        wp_register_block_types_from_metadata_collection(Plugin::path('blocks'), Plugin::path('blocks/blocks-manifest.php'));
        self::prepare_styles();
    }

    /**
     * Every Hive block stylesheet depends on hive-base (tokens before the
     * rules that use them) and takes the build's content hash as its
     * version: block.json has no per-file version, and a shared one would
     * change every URL on every release.
     */
    private static function prepare_styles(): void
    {
        $styles = wp_styles();
        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
            if (!str_starts_with($name, 'hive/')) {
                continue;
            }
            foreach (array_merge($type->view_style_handles, $type->editor_style_handles) as $handle) {
                $style = $styles->registered[$handle] ?? null;
                $path  = $styles->get_data($handle, 'path');
                if (!$style instanceof \_WP_Dependency || !is_string($path)) {
                    continue;
                }
                if (!in_array('hive-base', $style->deps, true)) {
                    $style->deps[] = 'hive-base';
                }
                $style->ver = Assets::build_info($path)['version'];
            }
        }
    }

    /** "Hive" first in the inserter. */
    public static function category(mixed $categories): array
    {
        return array_merge([['slug' => 'hive', 'title' => 'Hive', 'icon' => null]], (array) $categories);
    }
}
