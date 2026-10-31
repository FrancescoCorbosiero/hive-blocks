<?php
/**
 * The links of the contact blocks (Contatti, Vieni in negozio): a phone
 * number as tel:, an email as mailto:, a WhatsApp chat on wa.me with its
 * message already written. Pure: tested by tests/php/ContactLinksTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class ContactLinks
{
    /** '+39 0523 123 456' → 'tel:+390523123456'; '' when there are fewer than 3 digits. */
    public static function tel(string $phone): string
    {
        $phone  = trim($phone);
        $digits = (string) preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 3) {
            return '';
        }
        return 'tel:' . (str_starts_with($phone, '+') ? '+' : '') . $digits;
    }

    /** 'info@example.com' → 'mailto:info@example.com'; '' when it isn't an email address. */
    public static function mailto(string $email): string
    {
        $email = trim($email);
        return false !== filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $email : '';
    }

    /**
     * The first usable WhatsApp number among the ones given (the block's,
     * then the setting's), as wa.me wants it: digits only, in international
     * format; '+39 333…' and '0039 333…' both become '39333…'. A number
     * needs 6 to 15 digits; '' when none has them.
     */
    public static function whatsapp_number(string ...$numbers): string
    {
        foreach ($numbers as $number) {
            $digits = (string) preg_replace('/\D/', '', $number);
            if (str_starts_with($digits, '00')) {
                $digits = substr($digits, 2);
            }
            if (1 === preg_match('/^\d{6,15}$/', $digits)) {
                return $digits;
            }
        }
        return '';
    }

    /** The chat: 'https://wa.me/393331234567?text=Ciao…'; '' without a usable number. */
    public static function whatsapp(string $number, string $text = ''): string
    {
        $digits = self::whatsapp_number($number);
        if ('' === $digits) {
            return '';
        }
        $text = trim($text);
        return 'https://wa.me/' . $digits . ('' === $text ? '' : '?text=' . rawurlencode($text));
    }
}
