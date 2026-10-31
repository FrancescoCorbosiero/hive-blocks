/*
 * A stand-in for WooCommerce's variation script (wc-add-to-cart-variation)
 * for the browser tests: the events and classes @hive/product relies on,
 * as WooCommerce fires and sets them. The e2e tests run the real one.
 */
jQuery(function ($) {
    $('.variations_form').each(function () {
        var $form = $(this);
        var variations = $form.data('product_variations');
        var $button = $form.find('.single_add_to_cart_button');

        function check() {
            var chosen = {};
            var all = true;
            $form.find('.variations select').each(function () {
                chosen[this.name] = this.value;
                if (!this.value) all = false;
            });
            $form.trigger('woocommerce_update_variation_values');
            if (!all) {
                $form.find('input.variation_id').val('');
                $form.find('.single_variation').empty();
                $button.addClass('disabled wc-variation-selection-needed');
                $form.trigger('reset_data');
                return;
            }
            var match = variations.filter(function (variation) {
                return Object.keys(variation.attributes).every(function (key) {
                    return !variation.attributes[key] || variation.attributes[key] === chosen[key];
                });
            })[0];
            if (!match) {
                $form.trigger('reset_data');
                return;
            }
            $form.find('input.variation_id').val(match.variation_id);
            $form.trigger('found_variation', [match]);
            // WooCommerce shows the variation and enables the button 300 ms later.
            setTimeout(function () {
                $form.find('.single_variation').html('<div class="woocommerce-variation-price">' + match.price_html + '</div><div class="woocommerce-variation-availability">' + match.availability_html + '</div>');
                var buyable = match.is_in_stock && match.is_purchasable;
                $button.removeClass('wc-variation-selection-needed').toggleClass('disabled wc-variation-is-unavailable', !buyable);
                $form.trigger('show_variation', [match, buyable]);
            }, 300);
        }

        $form.on('change', '.variations select', function () {
            $form.trigger('woocommerce_variation_has_changed');
            check();
        });
        $form.on('click', '.single_add_to_cart_button', function (event) {
            if ($(this).is('.disabled')) {
                event.preventDefault();
                window.alert('WooCommerce: please select some product options');
            }
        });
        $button.addClass('disabled wc-variation-selection-needed');
        setTimeout(check, 100);
    });
});
