<?php
/**
 * What the contact blocks (Contatti, Vieni in negozio) share: their icons
 * and the WhatsApp link, on the block's own number or, when it has none,
 * the one in Hive → Impostazioni.
 *
 * @package Hive
 */

namespace Hive\Blocks;

use Hive\Core\ContactLinks;
use Hive\Settings;

defined('ABSPATH') || exit;

final class Contact
{
    private const STROKE = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    /** The shapes of each icon (24×24). */
    private const ICONS = [
        'address'    => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'vat'        => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'phone'      => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'email'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'directions' => '<path d="M3 11 22 2l-9 19-2-8-8-2z"/>',
        'map'        => '<path d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4z"/><path d="M8 2v16M16 6v16"/>',
    ];

    private const WHATSAPP = '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91A9.9 9.9 0 0 0 12.04 2Zm5.8 14.13c-.25.69-1.44 1.32-2 1.4-.51.08-1.15.11-1.86-.12-.43-.14-.98-.32-1.68-.62-2.96-1.28-4.89-4.26-5.04-4.46-.15-.2-1.2-1.6-1.2-3.05 0-1.45.76-2.17 1.03-2.46.27-.3.59-.37.79-.37h.57c.18 0 .43-.07.67.51.25.59.84 2.04.91 2.19.07.15.12.32.02.52-.1.2-.15.32-.3.49-.15.17-.31.38-.44.52-.15.15-.3.31-.13.6.17.3.77 1.27 1.65 2.06 1.13 1.01 2.09 1.32 2.38 1.47.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.49.22.57.35.07.12.07.71-.18 1.4Z"/>';

    /** An icon as static SVG markup, hidden from screen readers (the text next to it says it); '' for an unknown name. */
    public static function icon(string $name): string
    {
        if ('whatsapp' === $name) {
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . self::WHATSAPP . '</svg>';
        }
        return isset(self::ICONS[$name]) ? '<svg viewBox="0 0 24 24" ' . self::STROKE . ' aria-hidden="true" focusable="false">' . self::ICONS[$name] . '</svg>' : '';
    }

    /** The WhatsApp chat: the block's number, else the one in Hive → Impostazioni; '' with neither. */
    public static function whatsapp(string $number, string $text = ''): string
    {
        return ContactLinks::whatsapp(ContactLinks::whatsapp_number($number, (string) Settings::get('whatsapp')), $text);
    }
}
