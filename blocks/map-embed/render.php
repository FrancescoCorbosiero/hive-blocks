<?php
/**
 * Mappa — server render: Google Maps behind a cover, loaded when the
 * visitor asks (Hive\Blocks\Map, @hive/map). The height and the corners
 * reach the map as custom properties (assets/map.css).
 *
 * @var array $attributes
 *
 * @package Hive
 */

use Hive\Blocks\Map;

defined('ABSPATH') || exit;

$map_html = Map::facade((string) ($attributes['embedCode'] ?? ''), ['address' => (string) ($attributes['address'] ?? '')]);
if ('' === $map_html) {
    return;
}

$height = max(160, min(1200, (int) ($attributes['height'] ?? 450)));
$radius = max(0, min(48, (int) ($attributes['borderRadius'] ?? 12)));
$style  = sprintf('--hive-map-min:%dpx;--hive-map-radius:calc(%dpx * var(--hive-round))', $height, $radius);
?>
<div <?php echo get_block_wrapper_attributes(['class' => 'hive-block hive-map-embed', 'style' => $style]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress. ?>>
    <?php echo $map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Map::facade() escapes what it prints. ?>
</div>
