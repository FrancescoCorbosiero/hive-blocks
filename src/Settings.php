<?php
/**
 * Settings: one option (hive_settings) and its screen, "Hive" in the admin
 * menu. What each setting holds and how it's cleaned is Core\SettingsSchema;
 * this file adds the labels and the form.
 *
 * @package Hive
 */

namespace Hive;

use Hive\Core\SettingsSchema;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'hive_settings';
    public const PAGE   = 'hive';

    /** @var array<string, mixed>|null This request's copy of the option. */
    private static ?array $all = null;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'fields']);
        add_filter('plugin_action_links_' . plugin_basename(FILE), [self::class, 'action_link']);
        foreach (['add_option_', 'update_option_', 'delete_option_'] as $hook) {
            add_action($hook . self::OPTION, [self::class, 'forget'], 1);
        }
    }

    /** @return array<string, mixed> Every setting, defaults filled in. */
    public static function all(): array
    {
        return self::$all ??= SettingsSchema::sanitize(get_option(self::OPTION, []));
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** Drops this request's copy when the option changes. */
    public static function forget(): void
    {
        self::$all = null;
    }

    public static function menu(): void
    {
        add_menu_page('Hive', 'Hive', 'manage_options', self::PAGE, [self::class, 'page'], self::icon(), 59);
    }

    public static function fields(): void
    {
        register_setting(
            self::PAGE,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [SettingsSchema::class, 'sanitize'],
                'default'           => SettingsSchema::defaults(),
                'show_in_rest'      => false,
            ]
        );

        add_settings_section('look', 'Aspetto', [self::class, 'look_intro'], self::PAGE);
        add_settings_field('hive-accent', 'Colore principale', [self::class, 'accent_field'], self::PAGE, 'look', ['label_for' => 'hive-accent']);
        add_settings_field('hive-button', 'Colore dei pulsanti', [self::class, 'button_field'], self::PAGE, 'look', ['label_for' => 'hive-button']);
        add_settings_field('hive-shape', 'Forma degli elementi', [self::class, 'shape_field'], self::PAGE, 'look');

        add_settings_section('store', 'Negozio', [self::class, 'store_intro'], self::PAGE);
        add_settings_field('hive-size-attribute', 'Attributo della taglia', [self::class, 'size_attribute_field'], self::PAGE, 'store', ['label_for' => 'hive-size-attribute']);
        add_settings_field('hive-whatsapp', 'Numero WhatsApp', [self::class, 'whatsapp_field'], self::PAGE, 'store', ['label_for' => 'hive-whatsapp']);
        add_settings_field('hive-sold-out-last', 'Prodotti esauriti', [self::class, 'sold_out_last_field'], self::PAGE, 'store', ['label_for' => 'hive-sold-out-last']);
        add_settings_field('hive-filters-layout', 'Filtri su desktop', [self::class, 'filters_layout_field'], self::PAGE, 'store');

        $modules = Modules::all();
        if ($modules) {
            add_settings_section('modules', 'Moduli', [self::class, 'modules_intro'], self::PAGE);
            foreach ($modules as $id => $class) {
                add_settings_field(
                    'hive-module-' . $id,
                    esc_html($class::label()),
                    [self::class, 'module_field'],
                    self::PAGE,
                    'modules',
                    ['id' => $id, 'label_for' => 'hive-module-' . $id]
                );
            }
        }
    }

    public static function page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>Hive</h1>';
        settings_errors();
        echo '<form action="options.php" method="post">';
        settings_fields(self::PAGE);
        do_settings_sections(self::PAGE);
        submit_button('Salva le modifiche');
        echo '</form></div>';
    }

    public static function look_intro(): void
    {
        echo '<p>Valgono per tutti i blocchi e le funzioni di Hive, con qualsiasi tema. Caratteri e colore del testo restano quelli del tema.</p>';
    }

    public static function accent_field(): void
    {
        printf(
            '<input type="color" id="hive-accent" name="%s[accent]" value="%s"><p class="description">%s</p>',
            esc_attr(self::OPTION),
            esc_attr((string) self::get('accent')),
            'Pulsanti, badge e dettagli in evidenza. Il testo sopra questo colore diventa bianco o nero da solo, per restare leggibile.'
        );
    }

    public static function button_field(): void
    {
        printf(
            '<input type="color" id="hive-button" name="%s[button]" value="%s"><p class="description">%s</p>',
            esc_attr(self::OPTION),
            esc_attr((string) self::get('button')),
            '«Aggiungi al carrello» e gli altri pulsanti principali. Anche qui il testo si adatta da solo.'
        );
    }

    public static function store_intro(): void
    {
        echo '<p>I dati del negozio che le funzioni di Hive usano.</p>';
    }

    public static function size_attribute_field(): void
    {
        $current    = (string) self::get('size_attribute');
        $attributes = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [];
        if (!$attributes) {
            printf('<input type="text" id="hive-size-attribute" name="%s[size_attribute]" value="%s" class="regular-text">', esc_attr(self::OPTION), esc_attr($current));
            return;
        }
        printf('<select id="hive-size-attribute" name="%s[size_attribute]">', esc_attr(self::OPTION));
        foreach ($attributes as $attribute) {
            $name = 'pa_' . $attribute->attribute_name;
            printf('<option value="%s"%s>%s</option>', esc_attr($name), selected($current, $name, false), esc_html($attribute->attribute_label . ' (' . $name . ')'));
        }
        echo '</select><p class="description">Le taglie scelte nelle schede, nell’anteprima rapida e nei filtri.</p>';
    }

    public static function whatsapp_field(): void
    {
        printf(
            '<input type="text" id="hive-whatsapp" name="%s[whatsapp]" value="%s" class="regular-text" inputmode="tel" placeholder="393331234567"><p class="description">%s</p>',
            esc_attr(self::OPTION),
            esc_attr((string) self::get('whatsapp')),
            'Con il prefisso internazionale, es. 393331234567. Vuoto toglie «Chiedi su WhatsApp».'
        );
    }

    public static function sold_out_last_field(): void
    {
        printf(
            '<input type="hidden" name="%1$s[sold_out_last]" value="0"><label><input type="checkbox" id="hive-sold-out-last" name="%1$s[sold_out_last]" value="1"%2$s> %3$s</label>',
            esc_attr(self::OPTION),
            checked((bool) self::get('sold_out_last'), true, false),
            'In fondo al negozio e alle collezioni, dopo quelli disponibili, con qualsiasi ordinamento.'
        );
    }

    public static function filters_layout_field(): void
    {
        $choices = [
            'sidebar' => 'Barra laterale sempre aperta',
            'drawer'  => 'Pulsante «Filtri» e pannello, con i prodotti a tutta larghezza',
        ];
        echo '<fieldset><legend class="screen-reader-text">Filtri su desktop</legend>';
        foreach ($choices as $value => $label) {
            printf(
                '<label><input type="radio" name="%s[filters_layout]" value="%s"%s> %s</label><br>',
                esc_attr(self::OPTION),
                esc_attr($value),
                checked(self::get('filters_layout'), $value, false),
                esc_html($label)
            );
        }
        echo '<p class="description">Sul telefono i filtri si aprono sempre dal pulsante.</p></fieldset>';
    }

    public static function shape_field(): void
    {
        $choices = [
            'round' => 'Creativo — angoli arrotondati',
            'sharp' => 'Elegante — spigoli vivi',
        ];
        echo '<fieldset><legend class="screen-reader-text">Forma degli elementi</legend>';
        foreach ($choices as $value => $label) {
            printf(
                '<label><input type="radio" name="%s[shape]" value="%s"%s> %s</label><br>',
                esc_attr(self::OPTION),
                esc_attr($value),
                checked(self::get('shape'), $value, false),
                esc_html($label)
            );
        }
        echo '<p class="description">Pulsanti, schede, campi e blocchi cambiano forma insieme.</p></fieldset>';
    }

    public static function modules_intro(): void
    {
        echo '<p>Le funzioni del negozio, una per una: spegnerne una la toglie da tutte le pagine.</p>';
    }

    /** @param array{id: string} $args */
    public static function module_field(array $args): void
    {
        $class = Modules::all()[$args['id']] ?? null;
        if (null === $class) {
            return;
        }
        // The hidden 0 posts the module when its box is unticked, so turning it off is saved.
        printf(
            '<input type="hidden" name="%1$s[modules][%2$s]" value="0"><label><input type="checkbox" id="hive-module-%2$s" name="%1$s[modules][%2$s]" value="1"%3$s> %4$s</label>',
            esc_attr(self::OPTION),
            esc_attr($args['id']),
            checked(Modules::enabled($args['id']), true, false),
            esc_html($class::description())
        );
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public static function action_link(array $links): array
    {
        $settings = sprintf('<a href="%s">Impostazioni</a>', esc_url(admin_url('admin.php?page=' . self::PAGE)));
        return ['hive-settings' => $settings] + $links;
    }

    /** A hexagon, which WordPress paints in the admin colour scheme. */
    private static function icon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5l7.4 4.25v8.5L10 18.5l-7.4-4.25v-8.5z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
