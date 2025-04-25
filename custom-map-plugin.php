<?php
/**
 * Plugin Name: Custom Map Plugin
 * Description: Embed Leaflet map via `[custom_map]` with editable categories/colors, custom CSS, and custom pin HTML.
 * Version:     1.3.0
 * Author:      You
 */

// Register Producer CPT
add_action('init', fn() => register_post_type('producer', [
  'label'        => 'Producers',
  'public'       => true,
  'show_in_rest' => true,
  'menu_icon'    => 'dashicons-store',
  'supports'     => ['title','editor','thumbnail','custom-fields'],
]));

// Settings page
add_action('admin_menu', fn() => add_menu_page(
  'Map Settings', 'Map Settings', 'manage_options', 'cmp-settings', 'cmp_render_settings_page'
));

// Register settings
add_action('admin_init', function() {
  register_setting('cmp_settings_group','cmp_categories', [
    'type'              => 'array',
    'sanitize_callback' => fn($val) => is_array($val)
      ? array_values(array_filter($val, fn($c)=> trim($c['name'])!==''))
      : []
  ]);
  register_setting('cmp_settings_group','cmp_custom_css', [
    'type' => 'string',
    'sanitize_callback' => 'wp_strip_all_tags'
  ]);
  register_setting('cmp_settings_group','cmp_pin_template', [
    'type' => 'string',
    'sanitize_callback' => function($tpl) { return wp_kses_post($tpl); } // Allow limited HTML
  ]);
});

// Enqueue admin scripts
add_action('admin_enqueue_scripts', function($hook) {
  if ($hook !== 'toplevel_page_cmp-settings') return;
  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('cmp-admin-js', plugin_dir_url(__FILE__).'admin.js', ['jquery','wp-color-picker'], null, true);
});

// Settings Page Renderer
function cmp_render_settings_page() {
  $cats = get_option('cmp_categories', []);

  // Load default CSS
  $css_file    = plugin_dir_path(__FILE__) . 'static/css/main.css';
  $default_css = file_exists($css_file) ? file_get_contents($css_file) : '';

  // Load default Pin Template
  $tpl_file    = plugin_dir_path(__FILE__) . 'static/pin-template.html';
  $default_tpl = file_exists($tpl_file) ? file_get_contents($tpl_file) : 
    '<b>${marker.title}</b><br>${ marker.profile_img_url ? `<img src="${marker.profile_img_url}" width="200"><br>` : "" }<a href="${marker.page_url}">View</a>';

  $saved_css = get_option('cmp_custom_css', '');
  $css       = trim($saved_css) !== '' ? $saved_css : $default_css;

  $saved_tpl = get_option('cmp_pin_template', '');
  $tpl       = trim($saved_tpl) !== '' ? $saved_tpl : $default_tpl;
  ?>
  <div class="wrap"><h1>Map Settings</h1>
  <form method="post" action="options.php" id="cmp-settings-form">
    <?php settings_fields('cmp_settings_group'); ?>

    <table id="cmp-cats-table" class="form-table">
      <thead><tr><th>Name</th><th>Color</th><th></th></tr></thead>
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

    <h2>Custom CSS</h2>
    <textarea name="cmp_custom_css" id="cmp-custom-css" class="large-text code" rows="14"><?php echo esc_textarea($css); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-css">Reset CSS to Default</button></p>

    <h2>Pin Popup Template (HTML)</h2>
    <textarea name="cmp_pin_template" id="cmp-pin-template" class="large-text code" rows="8"><?php echo esc_textarea($tpl); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-template">Reset Template to Default</button></p>

    <?php submit_button(); ?>
  </form>

  <script>
    document.getElementById('cmp-reset-css').addEventListener('click', function() {
      const defaultCss = <?php echo json_encode($default_css); ?>;
      document.getElementById('cmp-custom-css').value = defaultCss;
    });
    document.getElementById('cmp-reset-template').addEventListener('click', function() {
      const defaultTpl = <?php echo json_encode($default_tpl); ?>;
      document.getElementById('cmp-pin-template').value = defaultTpl;
    });
  </script>
  </div>
  <?php
}

// Front-end assets & inline CSS + data
add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
  wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
  wp_enqueue_style('extra-markers-css', plugin_dir_url(__FILE__).'static/css/leaflet.extra-markers.min.css');
  wp_enqueue_style('cmp-main-css', plugin_dir_url(__FILE__) . 'static/css/main.css');
  wp_enqueue_script('extra-markers-js', plugin_dir_url(__FILE__).'static/js/leaflet.extra-markers.min.js', ['leaflet-js'], null, true);
  wp_enqueue_script('cmp-main-js', plugin_dir_url(__FILE__) . 'src/main.js', ['leaflet-js'], null, true);

  // Pass dynamic settings
  wp_localize_script('cmp-main-js','CMP',[
    'base_url'     => get_site_url(),
    'categories'   => get_option('cmp_categories', []),
    'pin_template' => get_option('cmp_pin_template', '')
  ]);

  $custom_css = get_option('cmp_custom_css', '');
  if ($custom_css) {
    wp_add_inline_style('cmp-main-css', $custom_css);
  }
});

// Shortcode
add_shortcode('custom_map', fn() => '<h1>Testing Map</h1>
  <div class="container">
    <div id="filter-controls">
      <div id="category-filters"></div>
      <div style="margin-top:10px">
        <input type="checkbox" id="filter-all" checked />
        <label for="filter-all" style="display:inline">Show All</label>
      </div>
    </div>
    <div id="map"></div>
  </div>');