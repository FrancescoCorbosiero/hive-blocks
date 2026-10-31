<?php
/**
 * WhatsApp chats (wa.me): which number to write to, and the link that opens
 * the chat with a message already written. Pure: tested by
 * tests/php/WhatsAppTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class WhatsApp
{
    /**
     * The first of these that is a phone number, the way wa.me wants it:
     * digits only, country code first, no + or 00 ("+39 333 123 4567" →
     * '393331234567'). '' when none is.
     */
    public static function number(string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $digits = (string) preg_replace('/\D/', '', $candidate);
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }
            if (1 === preg_match('/^\d{6,15}$/', $digits)) {
                return $digits;
            }
        }
        return '';
    }

    /** The chat with $number, $message written in it (none when it's blank). */
    public static function link(string $number, string $message = ''): string
    {
        $message = trim($message);
        return 'https://wa.me/' . $number . ('' === $message ? '' : '?text=' . rawurlencode($message));
    }
}
