<?php
/**
 * The settings of the hero, carousel and call-to-action blocks, read from
 * their attributes: every choice checked against its options, texts
 * trimmed, lists cleaned up, buttons only with both a label and a link.
 * Pure: tested by tests/php/HeroTest.php.
 *
 * @package Hive
 */

namespace Hive\Core;

defined('ABSPATH') || exit;

final class Hero
{
    /** hive/hero: Copertina, Diviso, Chi siamo. */
    public const LAYOUTS = ['cover', 'split', 'about'];

    /** hive/hero: as tall as its content, 70% of the screen, the whole screen. */
    public const HEIGHTS = ['auto', 'tall', 'full'];

    /** hive/cta: Banner, Immagine e testo. */
    public const CTA_LAYOUTS = ['banner', 'split'];

    /** Where an image's focal point can be (its CSS object-position). */
    public const POSITIONS = [
        'center center',
        'left center',
        'right center',
        'center top',
        'center bottom',
        'left top',
        'right top',
        'left bottom',
        'right bottom',
    ];

    /** The shortest and the longest pause between two slides, in ms (0 is no autoplay). */
    public const AUTOPLAY_MIN = 2000;
    public const AUTOPLAY_MAX = 20000;

    /** The value when it's one of the options, the default otherwise. */
    public static function choice(mixed $value, array $options, string $default): string
    {
        return is_string($value) && in_array($value, $options, true) ? $value : $default;
    }

    /** A text attribute, trimmed ('' for anything that isn't text). */
    public static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }

    /** A media library id (0 for none). */
    public static function id(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /** A switch: a boolean, or what a string or number says; the default when missing. */
    public static function flag(mixed $value, bool $default): bool
    {
        if (null === $value) {
            return $default;
        }
        return is_bool($value) ? $value : (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * An image's focal point: one of POSITIONS, in either word order; a
     * single word gets "center" for the other half ("left" → "left center",
     * as golden-hive-blocks read it). Anything else is the centre.
     */
    public static function position(mixed $value): string
    {
        $words = preg_split('/\s+/', strtolower(self::text($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (1 === count($words)) {
            $words = in_array($words[0], ['top', 'bottom'], true) ? ['center', $words[0]] : [$words[0], 'center'];
        }
        if (2 === count($words) && (in_array($words[0], ['top', 'bottom'], true) || in_array($words[1], ['left', 'right'], true))) {
            $words = [$words[1], $words[0]];
        }
        $position = implode(' ', $words);
        return in_array($position, self::POSITIONS, true) ? $position : 'center center';
    }

    /**
     * The title around the part shown in the accent colour, found ignoring
     * case and kept in the title's own spelling: [before, accent, after];
     * null when there's no accent or the title doesn't contain it.
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public static function accent(string $title, string $accent): ?array
    {
        $accent = trim($accent);
        if ('' === $accent || '' === $title) {
            return null;
        }
        if (function_exists('mb_stripos')) {
            $at = mb_stripos($title, $accent, 0, 'UTF-8');
            if (false === $at) {
                return null;
            }
            $length = mb_strlen($accent, 'UTF-8');
            return [
                mb_substr($title, 0, $at, 'UTF-8'),
                mb_substr($title, $at, $length, 'UTF-8'),
                mb_substr($title, $at + $length, null, 'UTF-8'),
            ];
        }
        $at = stripos($title, $accent);
        if (false === $at) {
            return null;
        }
        return [substr($title, 0, $at), substr($title, $at, strlen($accent)), substr($title, $at + strlen($accent))];
    }

    /**
     * A button, when it has both a label and a link.
     *
     * @return array{text: string, url: string}|null
     */
    public static function button(mixed $text, mixed $url): ?array
    {
        $text = self::text($text);
        $url  = self::text($url);
        return '' !== $text && '' !== $url ? ['text' => $text, 'url' => $url] : null;
    }

    /**
     * The "Chi siamo" values: those with a title, trimmed.
     *
     * @return list<array{title: string, text: string}>
     */
    public static function values(mixed $list): array
    {
        $values = [];
        foreach (is_array($list) ? $list : [] as $item) {
            $title = is_array($item) ? self::text($item['title'] ?? '') : '';
            if ('' !== $title) {
                $values[] = ['title' => $title, 'text' => self::text($item['text'] ?? '')];
            }
        }
        return $values;
    }

    /**
     * hive/hero's settings.
     *
     * The image is the background (cover), the side picture (split, about)
     * and, with a video, the video's poster. The foreground is the cover's
     * static layer (a cut-out product over the background). Buttons: the
     * main one, solid or outlined, then the outlined second one.
     *
     * @return array{
     *     layout: string, height: string, side: string, dark: bool, heading: string,
     *     eyebrow: string, badge: string, title: string, accent: array|null, subtitle: string,
     *     values: list<array{title: string, text: string}>,
     *     buttons: list<array{text: string, url: string, outline: bool}>,
     *     image: array{id: int, url: string}, position: string, video: string,
     *     foreground: array{id: int, url: string}, overlay: float, eager: bool, scroll: bool
     * }
     */
    public static function hero(array $attributes): array
    {
        $layout  = self::choice($attributes['layout'] ?? null, self::LAYOUTS, 'cover');
        $title   = self::text($attributes['title'] ?? '');
        $buttons = [];
        $main    = self::button($attributes['buttonText'] ?? '', $attributes['buttonUrl'] ?? '');
        if ($main) {
            $buttons[] = $main + ['outline' => 'outline' === ($attributes['buttonStyle'] ?? '')];
        }
        $second = self::button($attributes['secondaryButtonText'] ?? '', $attributes['secondaryButtonUrl'] ?? '');
        if ($second) {
            $buttons[] = $second + ['outline' => true];
        }
        $overlay = is_numeric($attributes['overlay'] ?? null) ? (float) $attributes['overlay'] : 50.0;

        return [
            'layout'     => $layout,
            'height'     => self::choice($attributes['height'] ?? null, self::HEIGHTS, 'tall'),
            'side'       => self::choice($attributes['mediaSide'] ?? null, ['left', 'right'], 'left'),
            'dark'       => 'cover' !== $layout && 'dark' === ($attributes['tone'] ?? ''),
            'heading'    => self::choice($attributes['headingLevel'] ?? null, ['h1', 'h2'], 'h2'),
            'eyebrow'    => self::text($attributes['eyebrow'] ?? ''),
            'badge'      => self::text($attributes['badge'] ?? ''),
            'title'      => $title,
            'accent'     => self::accent($title, self::text($attributes['titleAccent'] ?? '')),
            'subtitle'   => self::text($attributes['subtitle'] ?? ''),
            'values'     => self::values($attributes['values'] ?? []),
            'buttons'    => $buttons,
            'image'      => ['id' => self::id($attributes['imageId'] ?? 0), 'url' => self::text($attributes['imageUrl'] ?? '')],
            'position'   => self::position($attributes['imagePosition'] ?? ''),
            'video'      => self::text($attributes['videoUrl'] ?? ''),
            'foreground' => 'cover' === $layout
                ? ['id' => self::id($attributes['foregroundId'] ?? 0), 'url' => self::text($attributes['foregroundUrl'] ?? '')]
                : ['id' => 0, 'url' => ''],
            'overlay'    => round(max(0.0, min(90.0, $overlay)) / 100, 2),
            'eager'      => self::flag($attributes['aboveFold'] ?? null, true),
            'scroll'     => 'cover' === $layout && self::flag($attributes['showScrollIndicator'] ?? null, false),
        ];
    }

    /** The pause between slides in ms: 0 (no autoplay), or AUTOPLAY_MIN–AUTOPLAY_MAX. */
    public static function autoplay(mixed $value): int
    {
        $ms = is_numeric($value) ? (int) $value : 0;
        return $ms <= 0 ? 0 : max(self::AUTOPLAY_MIN, min(self::AUTOPLAY_MAX, $ms));
    }

    /**
     * hive/hero-carousel's settings. A slide with neither an image nor a
     * title is left out.
     *
     * @return array{
     *     slides: list<array{image: array{id: int, url: string}, position: string, eyebrow: string, title: string, subtitle: string, button: array|null}>,
     *     autoplay: int, centered: bool, dots: bool, arrows: bool
     * }
     */
    public static function carousel(array $attributes): array
    {
        $slides = [];
        foreach (is_array($attributes['slides'] ?? null) ? $attributes['slides'] : [] as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image = ['id' => self::id($slide['imageId'] ?? 0), 'url' => self::text($slide['image'] ?? '')];
            $title = self::text($slide['title'] ?? '');
            if ('' === $title && !$image['id'] && '' === $image['url']) {
                continue;
            }
            $slides[] = [
                'image'    => $image,
                'position' => self::position($slide['objectPosition'] ?? ''),
                'eyebrow'  => self::text($slide['eyebrow'] ?? ''),
                'title'    => $title,
                'subtitle' => self::text($slide['subtitle'] ?? ''),
                'button'   => self::button($slide['buttonText'] ?? '', $slide['buttonUrl'] ?? ''),
            ];
        }

        return [
            'slides'   => $slides,
            'autoplay' => self::autoplay($attributes['autoplay'] ?? 6000),
            'centered' => 'centered' === ($attributes['layout'] ?? ''),
            'dots'     => self::flag($attributes['showDots'] ?? null, true),
            'arrows'   => self::flag($attributes['showArrows'] ?? null, true),
        ];
    }

    /**
     * hive/cta's settings.
     *
     * @return array{
     *     layout: string, eyebrow: string, title: string, text: string, button: array|null,
     *     image: array{id: int, url: string}, reverse: bool, glow: bool
     * }
     */
    public static function cta(array $attributes): array
    {
        $layout = self::choice($attributes['layout'] ?? null, self::CTA_LAYOUTS, 'banner');
        return [
            'layout'  => $layout,
            'eyebrow' => self::text($attributes['eyebrow'] ?? ''),
            'title'   => self::text($attributes['title'] ?? ''),
            'text'    => self::text($attributes['text'] ?? ''),
            'button'  => self::button($attributes['buttonText'] ?? '', $attributes['buttonUrl'] ?? ''),
            'image'   => ['id' => self::id($attributes['imageId'] ?? 0), 'url' => self::text($attributes['imageUrl'] ?? '')],
            'reverse' => 'split' === $layout && self::flag($attributes['reverse'] ?? null, false),
            'glow'    => 'banner' === $layout && self::flag($attributes['showGlow'] ?? null, true),
        ];
    }
}
