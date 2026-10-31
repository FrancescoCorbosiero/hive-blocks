<?php
/**
 * A feature that can be switched off from Hive → Moduli (Quick Add, the
 * product gallery, the filters…). Blocks are not modules: they show up only
 * where someone places them.
 *
 * @package Hive
 */

namespace Hive;

defined('ABSPATH') || exit;

interface Module
{
    /** Stable key: the setting, the hive_module_enabled filter and the admin list use it. */
    public static function id(): string;

    /** Its name in the admin list. */
    public static function label(): string;

    /** One line on what it does on the site. */
    public static function description(): string;

    /** On for a fresh install? */
    public static function enabled_by_default(): bool;

    /** Hooks the module into WordPress. Called on plugins_loaded, only while the module is on. */
    public static function boot(): void;
}
