<?php
/**
 * The product page's buying part, in place of WooCommerce's add-to-cart
 * templates and CommerceKit's Attribute Swatches and Stock Meter:
 *
 *   variable products: one row of buttons per attribute, the size's with
 *   its price and a sale mark (Core\Swatches), sold-out sizes still a choice
 *   simple products: quantity and button
 *   the stock line ("Solo 2 disponibili", Core\StockMeter), for the product
 *   or the picked variation
 *   other types (grouped, external, plugins'): WooCommerce's own template
 *
 * Underneath, the variable form is WooCommerce's own variations form, with
 * its classes, data and script (wc-add-to-cart-variation): Hive's buttons
 * set its <select>s, and payment buttons, messages and other plugins that
 * follow WooCommerce's variation events keep working. Every WooCommerce
 * hook of the add-to-cart form is fired (Hooks: without the theme's).
 *
 * Adding to the cart posts the form as it is to wc-ajax=hive_add_to_cart:
 * WooCommerce's own form handler adds the product, exactly as for a page
 * load (validation, plugins' fields), and this answers with WooCommerce's
 * cart fragments, or why not. Without JavaScript the form posts normally.
 *
 * @package Hive
 */

namespace Hive\Shop;

use Hive\Core\Sizes as SizeRules;
use Hive\Core\StockMeter;
use Hive\Core\Swatches;
use Hive\Settings;

defined('ABSPATH') || exit;

final class BuyForm
{
    public const AJAX = 'hive_add_to_cart';

    /** Hive is drawing a product page: the stock line is Hive's (WooCommerce's variation data included). */
    private static bool $drawing = false;

    public static function register(): void
    {
        add_action('wc_ajax_' . self::AJAX, [self::class, 'added']);
        add_filter('woocommerce_get_stock_html', [self::class, 'stock_html'], 20, 2);
    }

    /**
     * @param string $guide The size guide's button, for the size attribute's row.
     */
    public static function render(\WC_Product $product, string $guide = ''): string
    {
        self::$drawing = true;
        try {
            if ($product instanceof \WC_Product_Variable && $product->is_type('variable')) {
                return self::variable($product, $guide);
            }
            if ($product->is_type('simple')) {
                return self::simple($product) . ('' !== $guide ? '<p class="hive-buy-form__guide">' . $guide . '</p>' : '');
            }
            // Grouped, external and other products: WooCommerce's own form for them.
            ob_start();
            woocommerce_template_single_add_to_cart();
            $html = (string) ob_get_clean();
            return '' !== trim($html) ? '<div class="hive-buy-form hive-buy-form--other">' . $html . '</div>' : '';
        } finally {
            self::$drawing = false;
        }
    }

    /** woocommerce_get_stock_html, while Hive draws: the stock line in Hive's words and markup. */
    public static function stock_html(mixed $html, mixed $product): mixed
    {
        if (!self::$drawing || !$product instanceof \WC_Product) {
            return $html;
        }
        return self::stock($product);
    }

    /** The stock line of a product or variation. */
    public static function stock(\WC_Product $product): string
    {
        $quantity = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
        $state    = StockMeter::state((string) $product->get_stock_status(), $quantity, (int) Settings::get('stock_low'));
        $html     = sprintf('<p class="hive-stock is-%s"><span class="hive-stock__text">%s</span>', esc_attr($state['level']), esc_html($state['text']));
        if ('low' === $state['level']) {
            $html .= sprintf('<span class="hive-stock__bar" aria-hidden="true"><span class="hive-stock__fill" style="--hive-stock:%d%%"></span></span>', $state['percent']);
        }
        return $html . '</p>';
    }

    private static function simple(\WC_Product $product): string
    {
        $html = self::stock($product);
        if (!$product->is_purchasable() || !$product->is_in_stock()) {
            return $html;
        }

        $html .= Hooks::run('woocommerce_before_add_to_cart_form');
        $html .= sprintf('<form class="cart hive-buy-form" action="%s" method="post" enctype="multipart/form-data" data-hive-buy>', esc_url(self::action($product)));
        $html .= Hooks::run('woocommerce_before_add_to_cart_button');
        $html .= '<div class="hive-buy-form__actions">';
        $html .= Hooks::run('woocommerce_before_add_to_cart_quantity');
        $html .= self::quantity($product);
        $html .= Hooks::run('woocommerce_after_add_to_cart_quantity');
        // name/value on the button, as WooCommerce's own template: payment plugins read the product id there.
        $html .= sprintf(
            '<button type="submit" name="add-to-cart" value="%d" class="single_add_to_cart_button hive-button hive-buy-form__add" data-hive-buy-add>%s</button>',
            $product->get_id(),
            esc_html($product->single_add_to_cart_text())
        );
        $html .= '</div>';
        $html .= Hooks::run('woocommerce_after_add_to_cart_button');
        $html .= '<p class="hive-buy-form__status" role="status" data-hive-buy-status></p>';
        $html .= '</form>';
        return $html . Hooks::run('woocommerce_after_add_to_cart_form');
    }

    private static function variable(\WC_Product_Variable $product, string $guide): string
    {
        wp_enqueue_script('wc-add-to-cart-variation');

        $attributes = $product->get_variation_attributes();
        $threshold  = (int) apply_filters('woocommerce_ajax_variation_threshold', 30, $product);
        // Over the threshold WooCommerce asks for each variation when it's picked (false here).
        $available = count($product->get_children()) <= $threshold ? $product->get_available_variations() : false;
        $json      = (string) wp_json_encode($available);

        $html  = Hooks::run('woocommerce_before_add_to_cart_form');
        $html .= sprintf(
            '<form class="variations_form cart hive-buy-form" action="%s" method="post" enctype="multipart/form-data" data-product_id="%d" data-product_variations="%s" data-hive-buy>',
            esc_url(self::action($product)),
            $product->get_id(),
            wc_esc_json($json)
        );
        $html .= Hooks::run('woocommerce_before_variations_form');

        if (is_array($available) && !$available) {
            // Nothing can be picked: every variation is hidden, or sold out with sold-out items hidden.
            $html .= self::stock_out();
        } else {
            $size  = Store::size_attribute();
            $html .= '<div class="variations hive-buy-form__choices">';
            foreach ($attributes as $name => $options) {
                $html .= self::swatches($product, (string) $name, (array) $options, $available, 1 === count($attributes), $name === $size ? $guide : '');
                if ($name === $size) {
                    $guide = '';
                }
            }
            $html .= '</div>';
            $html .= Hooks::run('woocommerce_after_variations_table');

            $html .= '<div class="single_variation_wrap">';
            $html .= Hooks::run('woocommerce_before_single_variation');
            $html .= '<div class="woocommerce-variation single_variation hive-buy-form__variation" role="alert" aria-relevant="additions"></div>';
            $html .= Hooks::run('woocommerce_single_variation', ['woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button']);
            $html .= '<div class="woocommerce-variation-add-to-cart variations_button hive-buy-form__actions">';
            $html .= Hooks::run('woocommerce_before_add_to_cart_button');
            $html .= Hooks::run('woocommerce_before_add_to_cart_quantity');
            $html .= self::quantity($product);
            $html .= Hooks::run('woocommerce_after_add_to_cart_quantity');
            $html .= sprintf('<button type="submit" class="single_add_to_cart_button hive-button hive-buy-form__add" data-hive-buy-add>%s</button>', esc_html($product->single_add_to_cart_text()));
            $html .= Hooks::run('woocommerce_after_add_to_cart_button');
            $html .= sprintf('<input type="hidden" name="add-to-cart" value="%1$d"><input type="hidden" name="product_id" value="%1$d"><input type="hidden" name="variation_id" class="variation_id" value="0">', $product->get_id());
            $html .= '</div>';
            $html .= Hooks::run('woocommerce_after_single_variation');
            $html .= '</div>';
        }

        $html .= '<p class="hive-buy-form__status" role="status" data-hive-buy-status></p>';
        $html .= Hooks::run('woocommerce_after_variations_form');
        $html .= '</form>';
        return $html . ('' !== $guide ? '<p class="hive-buy-form__guide">' . $guide . '</p>' : '') . Hooks::run('woocommerce_after_add_to_cart_form');
    }

    /**
     * One attribute's row: WooCommerce's <select> (what the form posts and
     * WooCommerce's script reads; shown only without JavaScript) and Hive's
     * buttons over it.
     *
     * @param list<string>     $options   The attribute's values (term slugs for taxonomy attributes).
     * @param array|false      $available WooCommerce's available variations, false over the threshold.
     */
    private static function swatches(\WC_Product_Variable $product, string $name, array $options, array|false $available, bool $only, string $guide): string
    {
        $field    = 'attribute_' . sanitize_title($name);
        $id       = sanitize_title($name);
        $label    = wc_attribute_label($name, $product);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the picked option from a link (?attribute_pa_taglia=42), as WooCommerce's own form reads it.
        $selected = isset($_REQUEST[$field]) && is_string($_REQUEST[$field]) ? sanitize_text_field(wp_unslash($_REQUEST[$field])) : $product->get_variation_default_attribute($name);
        $selected = (string) $selected;

        $list = [];
        if (taxonomy_exists($name)) {
            foreach (wc_get_product_terms($product->get_id(), $name, ['fields' => 'all']) as $term) {
                if (in_array($term->slug, $options, true)) {
                    $list[] = ['value' => $term->slug, 'label' => (string) apply_filters('woocommerce_variation_option_name', $term->name, $term, $name, $product)];
                }
            }
        } else {
            foreach ($options as $option) {
                $list[] = ['value' => (string) $option, 'label' => (string) apply_filters('woocommerce_variation_option_name', $option, null, $name, $product)];
            }
        }
        if ($name === Store::size_attribute()) {
            $list = SizeRules::sort($list, 'label');
        }
        $rows = is_array($available)
            ? Swatches::rows($field, $list, $available, $only)
            : array_map(static fn(array $option): array => $option + ['state' => 'in', 'price' => null, 'regular' => null, 'sale' => false], $list);

        $picked = '';
        foreach ($rows as $row) {
            if ($row['value'] === $selected) {
                $picked = $row['label'];
            }
        }

        $html  = sprintf(
            '<div class="hive-swatches" data-hive-swatches="%s" data-label="%s"%s>',
            esc_attr($field),
            esc_attr($label),
            $name === Store::size_attribute() ? ' data-size' : ''
        );
        $html .= '<div class="hive-swatches__head">';
        $html .= sprintf(
            '<label class="hive-swatches__label" id="%1$s-label" for="%1$s">%2$s<span class="hive-swatches__picked" data-hive-swatches-picked>%3$s</span></label>',
            esc_attr($id),
            esc_html($label),
            '' !== $picked ? esc_html(': ' . $picked) : ''
        );
        $html .= $guide . '</div>';

        $html .= sprintf(
            '<select id="%s" class="hive-swatches__select" name="%s" data-attribute_name="%s" data-show_option_none="yes">',
            esc_attr($id),
            esc_attr($field),
            esc_attr($field)
        );
        $html .= '<option value="">Scegli un’opzione</option>';
        foreach ($rows as $row) {
            $html .= sprintf('<option value="%s"%s>%s</option>', esc_attr($row['value']), selected($selected, $row['value'], false), esc_html($row['label']));
        }
        $html .= '</select>';

        $html .= sprintf('<div class="hive-swatches__options" role="group" aria-labelledby="%s-label">', esc_attr($id));
        foreach ($rows as $row) {
            $classes = ['hive-swatch', 'is-' . $row['state']];
            if ($row['sale']) {
                $classes[] = 'is-sale';
            }
            $note = '';
            if ('out' === $row['state']) {
                $note = 'Esaurito';
            } elseif (null !== $row['price']) {
                $note = self::money($row['price']);
            }
            $html .= sprintf(
                '<button type="button" class="%s" data-value="%s" aria-pressed="%s"%s><span class="hive-swatch__value">%s</span>%s%s</button>',
                esc_attr(implode(' ', $classes)),
                esc_attr($row['value']),
                $row['value'] === $selected ? 'true' : 'false',
                'none' === $row['state'] ? ' disabled' : '',
                esc_html($row['label']),
                '' !== $note ? '<span class="hive-swatch__note">' . esc_html($note) . '</span>' : '',
                $row['sale'] ? '<span class="hive-swatch__sale">In offerta</span>' : ''
            );
        }
        return $html . '</div></div>';
    }

    /** The quantity, Hive's stepper on WooCommerce's classes (its variation script sets the limits per size). */
    private static function quantity(\WC_Product $product): string
    {
        $args = (array) apply_filters(
            'woocommerce_quantity_input_args',
            [
                'input_name'  => 'quantity',
                'input_value' => $product->get_min_purchase_quantity(),
                'min_value'   => apply_filters('woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product),
                'max_value'   => apply_filters('woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product),
                'step'        => apply_filters('woocommerce_quantity_input_step', 1, $product),
            ],
            $product
        );
        $min   = max(0, (int) ($args['min_value'] ?? 1));
        $max   = (int) ($args['max_value'] ?? -1);
        $step  = max(1, (int) ($args['step'] ?? 1));
        $value = max($min, (int) ($args['input_value'] ?? $min));
        $name  = (string) ($args['input_name'] ?? 'quantity');

        if ($product->is_sold_individually() || ($max > 0 && $min === $max)) {
            return sprintf('<div class="quantity buttons_added hive-qty is-fixed"><input type="hidden" class="qty" name="%s" value="%d"></div>', esc_attr($name), $max > 0 && $min === $max ? $min : 1);
        }
        return sprintf(
            '<div class="quantity buttons_added hive-qty"><button type="button" class="hive-qty__step" data-hive-qty="-1" aria-label="Diminuisci la quantità">−</button><input type="number" class="qty hive-qty__input" name="%s" value="%d" min="%d"%s step="%d" inputmode="numeric" autocomplete="off" aria-label="Quantità"><button type="button" class="hive-qty__step" data-hive-qty="1" aria-label="Aumenta la quantità">+</button></div>',
            esc_attr($name),
            $value,
            $min,
            $max > 0 ? sprintf(' max="%d"', $max) : '',
            $step
        );
    }

    private static function stock_out(): string
    {
        $state = StockMeter::state('outofstock', 0, 0);
        return sprintf('<p class="hive-stock is-out"><span class="hive-stock__text">%s</span></p>', esc_html($state['text']));
    }

    private static function action(\WC_Product $product): string
    {
        return (string) apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink());
    }

    /** "150 €", "149,90 €": the price without decimals when it has none. */
    private static function money(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : wc_get_price_decimals();
        return html_entity_decode(wp_strip_all_tags(wc_price($amount, ['decimals' => $decimals])), ENT_QUOTES, 'UTF-8');
    }

    /**
     * wc-ajax=hive_add_to_cart: the product page's form, posted as it is.
     * WooCommerce's form handler has already handled it (wp_loaded, the
     * form's add-to-cart field); this reports what happened, with the cart
     * fragments, and clears the notices it left for the next page.
     */
    public static function added(): void
    {
        $errors = wc_get_notices('error');
        wc_clear_notices();

        $added = did_action('woocommerce_add_to_cart') > 0;
        if (!$added || $errors) {
            $messages = [];
            foreach ($errors as $notice) {
                $text = trim(html_entity_decode(wp_strip_all_tags((string) (is_array($notice) ? ($notice['notice'] ?? '') : $notice)), ENT_QUOTES, 'UTF-8'));
                if ('' !== $text) {
                    $messages[] = $text;
                }
            }
            wp_send_json(['error' => true, 'messages' => $messages ?: ['Non è stato possibile aggiungerlo al carrello.']]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce's form handler validated the request; this only names the product for analytics plugins, as WooCommerce's own AJAX add does.
        $product_id = isset($_POST['add-to-cart']) ? absint(wp_unslash($_POST['add-to-cart'])) : 0;
        do_action('woocommerce_ajax_added_to_cart', $product_id);

        ob_start();
        woocommerce_mini_cart();
        $mini_cart = (string) ob_get_clean();
        wp_send_json(
            [
                'fragments' => apply_filters('woocommerce_add_to_cart_fragments', ['div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>']),
                'cart_hash' => WC()->cart->get_cart_hash(),
            ]
        );
    }
}
