<?php

use Hive\Core\SettingsSchema;

$defaults = [
    'accent'         => '#721124',
    'button'         => '#0a0a0a',
    'shape'          => 'round',
    'size_attribute' => 'pa_taglia',
    'whatsapp'       => '',
    'sold_out_last'  => true,
    'filters_layout' => 'sidebar',
    'gallery'        => 'slider',
    'sticky_bar'     => true,
    'stock_low'      => 5,
    'modules'        => [],
];

check(SettingsSchema::defaults(), $defaults, 'the defaults');
check(SettingsSchema::sanitize(false), $defaults, 'nothing stored yet: the defaults');
check(
    SettingsSchema::sanitize(['accent' => '#ABC', 'shape' => 'sharp', 'extra' => 'dropped']),
    array_merge($defaults, ['accent' => '#aabbcc', 'shape' => 'sharp']),
    'valid values are kept and normalized, unknown keys dropped'
);
check(SettingsSchema::sanitize(['accent' => 'blue'])['accent'], '#721124', 'an invalid colour falls back to the default');
check(SettingsSchema::sanitize(['shape' => ['sharp']])['shape'], 'round', 'a choice must be one of the strings');

check(SettingsSchema::sanitize(['size_attribute' => 'pa_size'])['size_attribute'], 'pa_size', 'an attribute name');
check(SettingsSchema::sanitize(['size_attribute' => 'pa taglia; drop'])['size_attribute'], 'pa_taglia', 'not a name: the default');

check(SettingsSchema::sanitize(['whatsapp' => '+39 351 760 3869'])['whatsapp'], '393517603869', 'a phone number keeps its digits');
check(SettingsSchema::sanitize(['whatsapp' => ''])['whatsapp'], '', 'no number: WhatsApp off');
check(SettingsSchema::sanitize(['whatsapp' => '123'])['whatsapp'], '', 'too short to be a number');

check(SettingsSchema::sanitize(['sold_out_last' => '0'])['sold_out_last'], false, 'a switch turned off (the form posts 0)');
check(SettingsSchema::sanitize(['sold_out_last' => '1'])['sold_out_last'], true, 'a switch turned on');
check(SettingsSchema::sanitize([])['sold_out_last'], true, 'a switch never saved: its default');
check(SettingsSchema::sanitize(['filters_layout' => 'drawer'])['filters_layout'], 'drawer', 'the filters as a panel');

check(SettingsSchema::sanitize(['stock_low' => '3'])['stock_low'], 3, 'a number from the form');
check(SettingsSchema::sanitize(['stock_low' => '0'])['stock_low'], 0, 'zero turns "few left" off');
check(SettingsSchema::sanitize(['stock_low' => '500'])['stock_low'], 50, 'a number over the maximum is capped');
check(SettingsSchema::sanitize(['stock_low' => '-2'])['stock_low'], 5, 'not a whole number: the default');
check(SettingsSchema::sanitize(['stock_low' => 'tre'])['stock_low'], 5, 'not a number: the default');
check(SettingsSchema::sanitize(['gallery' => 'grid'])['gallery'], 'grid', 'the gallery as a grid');

check(
    SettingsSchema::sanitize(['modules' => ['quick-add' => '1', 'gallery' => '0', 'Bad Key' => '1', 3 => '1']])['modules'],
    ['quick-add' => true, 'gallery' => false],
    'module switches become booleans; only clean keys are kept'
);
check(SettingsSchema::sanitize(['modules' => 'all'])['modules'], [], 'switches must be a list');

$once = SettingsSchema::sanitize(['accent' => '#123456', 'shape' => 'sharp', 'whatsapp' => '393331234567', 'modules' => ['gallery' => true]]);
check(SettingsSchema::sanitize($once), $once, 'sanitizing twice changes nothing (WordPress does it twice when the option is new)');
