<?php

use Hive\Core\DesignTokens;

check(
    DesignTokens::css([]),
    ':root{--hive-accent:#721124;--hive-on-accent:#ffffff;--hive-button:#0a0a0a;--hive-on-button:#ffffff;--hive-round:1}',
    'nothing set: the store burgundy, black buttons, rounded'
);
check(
    DesignTokens::css(['accent' => '#F5D300', 'button' => '#ffffff', 'shape' => 'sharp']),
    ':root{--hive-accent:#f5d300;--hive-on-accent:#0a0a0a;--hive-button:#ffffff;--hive-on-button:#0a0a0a;--hive-round:0}',
    'light colours get dark text; sharp corners'
);
check(
    DesignTokens::css(['accent' => '#000;}body{display:none}', 'button' => 'red', 'shape' => 'blob']),
    ':root{--hive-accent:#721124;--hive-on-accent:#ffffff;--hive-button:#0a0a0a;--hive-on-button:#ffffff;--hive-round:1}',
    'invalid values fall back, so nothing but a colour reaches the CSS'
);
