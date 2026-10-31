<?php
/**
 * A shipping city as the social proof shows it ("Reggio nell'Emilia",
 * "Sant'Angelo Lodigiano"): trimmed, at most 40 characters, words
 * capitalized, particles lowercase inside a name; nothing for an entry with
 * digits (a postcode typed in the city field).
 *
 * Pure logic: no WordPress calls. Tested in tests/php/CityTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class City
{
    private const PARTICLES = ['di', 'del', 'della', 'dei', 'delle', 'in', 'sul', 'sulla', 'al', 'alla', 'da', 'e'];

    public static function name(string $raw): string
    {
        $city = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if ('' === $city || 1 === preg_match('/\d/', $city)) {
            return '';
        }

        $words = explode(' ', mb_strtolower(mb_substr($city, 0, 40, 'UTF-8'), 'UTF-8'));
        foreach ($words as $i => $word) {
            if ($i > 0 && in_array($word, self::PARTICLES, true)) {
                continue;
            }
            // Each part of "sant'angelo" or "monte-carlo" capitalized…
            $word = (string) preg_replace_callback(
                "/(^|['’\\-])(\\p{Ll})/u",
                static fn(array $m): string => $m[1] . mb_strtoupper($m[2], 'UTF-8'),
                $word
            );
            // …but a leading particle inside a name stays lowercase: "nell'Emilia".
            if ($i > 0 && 1 === preg_match("/^(Nell|Dell|Sull|All|D)(['’])/u", $word)) {
                $word = mb_strtolower(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8');
            }
            $words[$i] = $word;
        }
        return implode(' ', $words);
    }
}
