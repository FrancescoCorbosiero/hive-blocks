<?php
/**
 * Front-end and editor assets: the shared ones, and how block stylesheets
 * reach the page.
 *
 * A page loads only what its blocks need. Each block declares its own CSS
 * (viewStyle) and script module (viewScriptModule) in block.json, and
 * WordPress enqueues them when the block renders. Block themes render the
 * whole page before <head>, so the CSS lands there. Classic themes print
 * <head> first, and a stylesheet enqueued later would only come out in the
 * footer, after the block had already painted unstyled. So on classic themes:
 *
 *   1. enqueue_for_content() finds the Hive blocks in the page's own content
 *      before <head> is printed and enqueues their CSS there;
 *   2. print_late_styles() catches any block rendered after <head> anyway
 *      (a widget, a shortcode in a template) and prints its stylesheet
 *      right before it, the first time it appears.
 *
 * Shared assets, all versioned by content hash (see Assets::build_info()):
 *
 *   hive-base       assets/build/base.css: design tokens and shared rules,
 *                   a dependency of every other Hive stylesheet; the
 *                   settings follow it inline (Core\DesignTokens)
 *   hive-<name>     assets/build/<name>.css: shop.css (product cards, grid,
 *                   rails), dialog.css (dialogs, loaded by @hive/dialog on
 *                   first use)
 *   @hive/<name>    assets/build/modules/<name>.js: script modules
 *   hive-editor     assets/build/editor.js: shared editor components
 *                   (window.hive.editor, imported as '@hive/editor')
 *
 * Markup rendered outside a block's own render (a product card in
 * WooCommerce's lists) asks for its stylesheet with use_style(), which
 * works the same way: <head> when there's still time, right before the
 * markup otherwise.
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\BlockScan;
use Hive\Core\DesignTokens;

defined('ABSPATH') || exit;

final class Assets
{
    public static function register(): void
    {
        add_action('init', [self::class, 'register_shared'], 5);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_for_content'], 5);
        add_filter('render_block', [self::class, 'print_late_styles'], 10, 3);
        add_filter('script_module_data_@hive/dialog', [self::class, 'dialog_data']);
    }

    /**
     * A stylesheet some markup needs: enqueued while <head> is still to be
     * printed, otherwise returned as a tag to print right before the markup
     * (once per page). Print what it returns.
     */
    public static function use_style(string $handle): string
    {
        if (!did_action('wp_head') || doing_action('wp_head') || is_admin() || wp_doing_ajax() || wp_is_serving_rest_request()) {
            wp_enqueue_style($handle);
            return '';
        }
        if (wp_style_is($handle, 'done')) {
            return '';
        }
        ob_start();
        wp_styles()->do_items([$handle]);
        return (string) ob_get_clean();
    }

    /** A script module some markup needs (modules load deferred, wherever they're printed). */
    public static function use_module(string $id): void
    {
        wp_enqueue_script_module($id);
    }

    /** A registered stylesheet's URL with its version, for a script to load later. */
    public static function style_url(string $handle): string
    {
        $style = wp_styles()->registered[$handle] ?? null;
        return $style instanceof \_WP_Dependency && is_string($style->src) ? add_query_arg('ver', $style->ver, $style->src) : '';
    }

    /** @hive/dialog loads dialog.css itself, the first time a dialog opens. */
    public static function dialog_data(array $data): array
    {
        return ['style' => self::style_url('hive-dialog')] + $data;
    }

    /**
     * What the build recorded for one of its files, in the .asset.php next
     * to it: the version (a hash of the file's content, so its URL changes
     * only when the file does) and, for scripts, the dependencies.
     *
     * @return array{dependencies: array, version: string}
     */
    public static function build_info(string $file): array
    {
        $asset = preg_replace('/\.(js|css)$/', '.asset.php', $file);
        $info  = is_string($asset) && is_file($asset) ? include $asset : [];
        return [
            'dependencies' => is_array($info['dependencies'] ?? null) ? $info['dependencies'] : [],
            'version'      => is_string($info['version'] ?? null) ? $info['version'] : VERSION,
        ];
    }

    public static function register_shared(): void
    {
        foreach (glob(Plugin::path('assets/build/*.css')) ?: [] as $file) {
            $name   = basename($file, '.css');
            $handle = 'hive-' . $name;
            wp_register_style($handle, Plugin::url('assets/build/' . $name . '.css'), 'base' === $name ? [] : ['hive-base'], self::build_info($file)['version']);
            // With a path, WordPress may inline the file in <head> instead of linking it.
            wp_style_add_data($handle, 'path', $file);
        }
        wp_add_inline_style('hive-base', DesignTokens::css(Settings::all()));

        foreach (glob(Plugin::path('assets/build/modules/*.js')) ?: [] as $file) {
            $info = self::build_info($file);
            wp_register_script_module(
                '@hive/' . basename($file, '.js'),
                Plugin::url('assets/build/modules/' . basename($file)),
                $info['dependencies'],
                $info['version']
            );
        }

        $editor = Plugin::path('assets/build/editor.js');
        $info   = self::build_info($editor);
        wp_register_script('hive-editor', Plugin::url('assets/build/editor.js'), $info['dependencies'], $info['version'], ['in_footer' => true]);
    }

    /** Classic themes: the CSS of the Hive blocks in this page's content, enqueued before <head> is printed. */
    public static function enqueue_for_content(): void
    {
        if (wp_is_block_theme() || !is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || !has_blocks($post->post_content)) {
            return;
        }
        $registry = \WP_Block_Type_Registry::get_instance();
        foreach (BlockScan::names(parse_blocks($post->post_content), [self::class, 'synced_pattern']) as $name) {
            if (str_starts_with($name, 'hive/')) {
                foreach ($registry->get_registered($name)?->view_style_handles ?? [] as $handle) {
                    wp_enqueue_style($handle);
                }
            }
        }
    }

    /** The parsed blocks of a published synced pattern, for BlockScan. */
    public static function synced_pattern(int $ref): array
    {
        $post = get_post($ref);
        return $post instanceof \WP_Post && 'wp_block' === $post->post_type && 'publish' === $post->post_status
            ? parse_blocks($post->post_content)
            : [];
    }

    /**
     * A Hive block rendered after <head> whose CSS hasn't been printed yet:
     * print it right before the block. A stylesheet in <body> holds back the
     * content after it until it loads, so the block never paints unstyled.
     *
     * @param mixed $content The block's HTML (another plugin's filter may have broken it: then it's left alone).
     * @param mixed $block   The parsed block.
     */
    public static function print_late_styles(mixed $content, mixed $block, mixed $instance = null): mixed
    {
        $name = is_array($block) ? (string) ($block['blockName'] ?? '') : '';
        if (
            !is_string($content)
            || '' === $content
            || !str_starts_with($name, 'hive/')
            || !did_action('wp_head')
            || doing_action('wp_head')
            || is_admin()
            || wp_doing_ajax()
            || wp_is_serving_rest_request()
        ) {
            return $content;
        }

        $type    = $instance instanceof \WP_Block ? $instance->block_type : \WP_Block_Type_Registry::get_instance()->get_registered($name);
        $pending = array_values(
            array_filter(
                $type?->view_style_handles ?? [],
                static fn(string $handle): bool => !wp_style_is($handle, 'done')
            )
        );
        if (!$pending) {
            return $content;
        }

        ob_start();
        wp_styles()->do_items($pending);
        return ob_get_clean() . $content;
    }
}
