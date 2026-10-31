<?php

use Hive\Core\SettingsSchema;

$defaults = ['accent' => '#721124', 'shape' => 'round', 'modules' => []];

check(SettingsSchema::defaults(), $defaults, 'the defaults');
check(SettingsSchema::sanitize(false), $defaults, 'nothing stored yet: the defaults');
check(
    SettingsSchema::sanitize(['accent' => '#ABC', 'shape' => 'sharp', 'extra' => 'dropped']),
    ['accent' => '#aabbcc', 'shape' => 'sharp', 'modules' => []],
    'valid values are kept and normalized, unknown keys dropped'
);
check(SettingsSchema::sanitize(['accent' => 'blue'])['accent'], '#721124', 'an invalid colour falls back to the default');
check(SettingsSchema::sanitize(['shape' => ['sharp']])['shape'], 'round', 'a choice must be one of the strings');
check(
    SettingsSchema::sanitize(['modules' => ['quick-add' => '1', 'gallery' => '0', 'Bad Key' => '1', 3 => '1']])['modules'],
    ['quick-add' => true, 'gallery' => false],
    'module switches become booleans; only clean keys are kept'
);
check(SettingsSchema::sanitize(['modules' => 'all'])['modules'], [], 'switches must be a list');

$once = SettingsSchema::sanitize(['accent' => '#123456', 'shape' => 'sharp', 'modules' => ['gallery' => true]]);
check(SettingsSchema::sanitize($once), $once, 'sanitizing twice changes nothing (WordPress does it twice when the option is new)');
