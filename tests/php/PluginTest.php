<?php

// Release bookkeeping and the build, checked from the files themselves.

$plugin = dirname(__DIR__, 2);
$main   = (string) file_get_contents($plugin . '/hive-blocks.php');
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $main, $header);
preg_match("/^const VERSION = '([^']+)';/m", $main, $constant);
check($header[1] ?? 'missing', $constant[1] ?? 'missing constant', 'the Version header and Hive\VERSION match');

// Every block folder is in the manifest WordPress registers from, as its block.json says.
$manifest = require $plugin . '/blocks/blocks-manifest.php';
$folders  = array_map('basename', glob($plugin . '/blocks/*', GLOB_ONLYDIR) ?: []);
check(array_keys($manifest), $folders, 'blocks-manifest.php lists every block folder (run npm run build)');

foreach ($folders as $folder) {
    $json = json_decode((string) file_get_contents("$plugin/blocks/$folder/block.json"), true);
    check($manifest[$folder] ?? null, $json, "blocks-manifest.php matches $folder/block.json (run npm run build)");

    // Every file block.json points at exists.
    foreach (['editorScript', 'editorStyle', 'viewStyle', 'viewScriptModule', 'render'] as $field) {
        foreach ((array) ($json[$field] ?? []) as $ref) {
            if (str_starts_with($ref, 'file:')) {
                $path = "$plugin/blocks/$folder/" . substr($ref, strlen('file:./'));
                check(is_file($path), true, "$folder: $field $ref exists");
            }
        }
    }

    // Never "style": classic themes load every registered block's "style" on every page.
    check(isset($json['style']), false, "$folder: CSS goes in viewStyle and editorStyle, not style");
    check(str_starts_with((string) $json['name'], 'hive/'), true, "$folder: the block is in the hive/ namespace");
}
