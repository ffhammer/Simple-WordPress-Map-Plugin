<?php
/**
 * Plugin Name: Custom Map Plugin
 * Description: Embed Leaflet map via `[custom_map]` with editable categories/colors.
 * Version:     1.1.0
 * Author:      You
 */

add_action('init', function() {
  register_post_type('producer', [
    'label' => 'Producers',
    'public' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-store',
    'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
  ]);
});

add_action('admin_menu', function() {
  add_menu_page('Map Settings', 'Map Settings', 'manage_options', 'cmp-settings', 'cmp_render_settings_page');
});

add_action('admin_init', function() {
  register_setting('cmp_settings_group', 'cmp_categories', [
    'type' => 'array',
    'sanitize_callback' => function($val) {
      if (!is_array($val)) return [];
      return array_values(array_filter($val, fn($c) => !empty($c['name'])));
    }
  ]);
});

add_action('admin_enqueue_scripts', function($hook) {
  if ($hook !== 'toplevel_page_cmp-settings') return;
  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('cmp-admin-js', plugin_dir_url(__FILE__) . 'src/admin.js', ['jquery', 'wp-color-picker'], null, true);
});

function cmp_render_settings_page() {
  $cats = get_option('cmp_categories', []);
  ?>
  <div class="wrap"><h1>Map Categories</h1>
  <form method="post" action="options.php">
    <?php settings_fields('cmp_settings_group'); ?>
    <table id="cmp-cats-table" class="form-table">
      <thead><tr><th>Category Name</th><th>Color</th><th></th></tr></thead>
      <tbody>
      <?php foreach($cats as $i=>$c): ?>
        <tr>
          <td><input name="cmp_categories[<?php echo $i; ?>][name]" value="<?php echo esc_attr($c['name']); ?>" /></td>
          <td><input name="cmp_categories[<?php echo $i; ?>][color]" class="cmp-color-field" value="<?php echo esc_attr($c['color']); ?>" /></td>
          <td><button class="button cmp-remove-row">Remove</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><button id="cmp-add-row" class="button">Add Category</button></p>
    <?php submit_button(); ?>
  </form>
  </div>
  <?php
}

function cmp_enqueue_assets() {
  wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
  wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
  wp_enqueue_style('extra-markers-css', plugin_dir_url(__FILE__) . 'static/css/leaflet.extra-markers.min.css');
  wp_enqueue_style('cmp-main-css', plugin_dir_url(__FILE__) . 'static/css/main.css');
  wp_enqueue_script('extra-markers-js', plugin_dir_url(__FILE__) . 'static/js/leaflet.extra-markers.min.js', ['leaflet-js'], null, true);
  wp_enqueue_script('cmp-main-js', plugin_dir_url(__FILE__) . 'src/main.js', ['leaflet-js'], null, true);
  wp_localize_script('cmp-main-js', 'CMP', [
    'base_url' => get_site_url(),
    'categories' => get_option('cmp_categories', [])
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
