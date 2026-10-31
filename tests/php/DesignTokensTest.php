<?php

use Hive\Core\DesignTokens;

check(
    DesignTokens::css([]),
    ':root{--hive-accent:#721124;--hive-on-accent:#ffffff;--hive-round:1}',
    'nothing set: the store burgundy, rounded'
);
check(
    DesignTokens::css(['accent' => '#F5D300', 'shape' => 'sharp']),
    ':root{--hive-accent:#f5d300;--hive-on-accent:#0a0a0a;--hive-round:0}',
    'a light accent gets dark text; sharp corners'
);
check(
    DesignTokens::css(['accent' => '#000;}body{display:none}', 'shape' => 'blob']),
    ':root{--hive-accent:#721124;--hive-on-accent:#ffffff;--hive-round:1}',
    'invalid values fall back, so nothing but a colour reaches the CSS'
);
