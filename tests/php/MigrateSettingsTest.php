<?php

// The migration's settings: golden-hive-blocks' options as Hive's.

use Hive\Core\Migrate\Settings;

check(
    Settings::from(['ghb_whatsapp_number' => '393517603869', 'ghb_design_shape' => 'sharp', 'ghb_pp_band' => 'no', 'ghb_pp_recent' => 'yes', 'ghb_delivery_days_min' => '1', 'ghb_delivery_estimate' => 'yes']),
    ['whatsapp' => '393517603869', 'shape' => 'sharp', 'band' => false, 'recent' => true, 'delivery' => true, 'delivery_min' => 1],
    'what the store set, in Hive’s terms'
);
check(Settings::from([]), [], 'nothing set: nothing carried (Hive’s defaults stay)');
check(Settings::from(['ghb_whatsapp_number' => false]), [], 'an option that isn’t there');
check(
    Settings::from([], ['preload_urls' => "https://a.test/hero.jpg\n", 'preload_homepage_only' => 0, 'prerender_urls' => '', 'prefetch_urls' => '/drop/']),
    ['preload' => 'https://a.test/hero.jpg', 'prefetch' => '/drop/', 'preload_home' => false],
    'the preload settings'
);
