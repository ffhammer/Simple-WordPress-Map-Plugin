<?php
/**
 * Plugin Name: Custom Map Plugin
 * Description: Embed your Leaflet map via `[custom_map]`
 * Version:     1.0.0
 * Author:      You
 */

function cmp_enqueue_assets() {
  wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
  wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

  wp_enqueue_style('extra-markers-css', plugin_dir_url(__FILE__) . 'static/css/leaflet.extra-markers.min.css');
  wp_enqueue_style('cmp-main-css', plugin_dir_url(__FILE__) . 'static/css/main.css');
  wp_enqueue_script('extra-markers-js', plugin_dir_url(__FILE__) . 'static/js/leaflet.extra-markers.min.js', ['leaflet-js'], null, true);
  wp_enqueue_script('cmp-main-js', plugin_dir_url(__FILE__) . 'src/main.js', ['leaflet-js'], null, true);

  wp_localize_script('cmp-main-js', 'CMP', [
    'base_url' => get_site_url()
  ]);
}
add_action('wp_enqueue_scripts', 'cmp_enqueue_assets');

function cmp_render_map(): string {
  return '<h1>Testing Map</h1>
    <div class="container">
      <div id="filter-controls">
        <div id="category-filters"></div>
        <div style="margin-top:10px">
          <input type="checkbox" id="filter-all" checked />
          <label for="filter-all" style="display:inline">Show All</label>
        </div>
      </div>
      <div id="map"></div>
    </div>';
}
add_shortcode('custom_map', 'cmp_render_map');
