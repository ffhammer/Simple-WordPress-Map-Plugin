<?php
/**
 * Plugin Name: Custom Map Plugin
 * Description: Embed Leaflet map via `[custom_map]` with editable ACF‐field mappings, categories/colors, custom CSS, and custom pin HTML.
 * Version:     1.4.0
 * Author:      You
 */

// 1) Register Producer CPT
add_action('init', fn() => register_post_type('producer', [
  'label'        => 'Producers',
  'public'       => true,
  'show_in_rest' => true,
  'menu_icon'    => 'dashicons-store',
  'supports'     => ['title','editor','thumbnail','custom-fields'],
]));


// 2) Add Settings page
add_action('admin_menu', fn() =>
  add_menu_page('Map Settings','Map Settings','manage_options','cmp-settings','cmp_render_settings_page')
);

// 3) Register settings
add_action('admin_init', function() {
  // ACF field mappings
  register_setting('cmp_settings_group','cmp_requiredAcfFields', [
    'type'              => 'array',
    'sanitize_callback' => function($val) {
      $defaults = [
        'profile_img'=>'profile_img',
        'latitude'=>'latitude',
        'longitude'=>'longitude',
        'category'=>'category',
      ];
      if (!is_array($val)) {
        return $defaults;
      }
      // only keep our keys, fallback to defaults
      return array_merge($defaults, array_intersect_key($val, $defaults));
    }
  ]);

  // Categories/colors
  register_setting('cmp_settings_group','cmp_categories', [
    'type'=>'array',
    'sanitize_callback'=>fn($v)=>is_array($v)
      ? array_values(array_filter($v, fn($c)=> trim($c['name'])!==''))
      : []
  ]);

  // Custom CSS
  register_setting('cmp_settings_group','cmp_custom_css', [
    'type'=>'string',
    'sanitize_callback'=>'wp_strip_all_tags'
  ]);

  // Pin HTML template
  register_setting('cmp_settings_group','cmp_pin_template', [
    'type'=>'string',
    'sanitize_callback'=>fn($s)=>wp_kses_post($s)
  ]);
});

// 4) Enqueue admin assets
add_action('admin_enqueue_scripts', function($hook) {
  if ($hook!=='toplevel_page_cmp-settings') return;
  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('cmp-admin-js', plugin_dir_url(__FILE__).'admin.js', ['jquery','wp-color-picker'], null, true);
});

// 5) Render Settings page
function cmp_render_settings_page() {
  $cats      = get_option('cmp_categories', []);
  $acfFields = get_option('cmp_requiredAcfFields', []);
  $css_file  = plugin_dir_path(__FILE__).'static/css/main.css';
  $default_css = file_exists($css_file) ? file_get_contents($css_file) : '';
  $saved_css   = get_option('cmp_custom_css','');
  $css         = trim($saved_css) ? $saved_css : $default_css;
  $tpl_file    = plugin_dir_path(__FILE__).'static/pin-template.html';
  $default_tpl = file_exists($tpl_file) ? file_get_contents($tpl_file) : '<b>${marker.title}</b><br>${marker.profile_img_url?`<img src=\"${marker.profile_img_url}\" width=\"200\"><br>`:""}<a href=\"${marker.page_url}\">View</a>';
  $saved_tpl   = get_option('cmp_pin_template','');
  $tpl         = trim($saved_tpl) ? $saved_tpl : $default_tpl;
  ?>
  <div class="wrap"><h1>Map Settings</h1>
  <form method="post" action="options.php" id="cmp-settings-form">
    <?php settings_fields('cmp_settings_group'); ?>

    <h2>ACF Field Mappings</h2>
    <table class="form-table">
      <?php foreach(['profile_img','latitude','longitude','category'] as $key): ?>
      <tr>
        <th><label for="acf_<?php echo $key; ?>"><?php echo ucfirst(str_replace('_',' ',$key)); ?></label></th>
        <td>
          <input type="text" id="acf_<?php echo $key; ?>"
                 name="cmp_requiredAcfFields[<?php echo $key; ?>]"
                 value="<?php echo esc_attr($acfFields[$key] ?? $key); ?>"
                 class="regular-text" />
          <p class="description">ACF key for <code><?php echo $key; ?></code></p>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <h2>Categories &amp; Colors</h2>
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
    <textarea name="cmp_custom_css" id="cmp-custom-css" class="large-text code" rows="12"><?php echo esc_textarea($css); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-css">Reset CSS to Default</button></p>

    <h2>Pin Popup Template (HTML)</h2>
    <textarea name="cmp_pin_template" id="cmp-pin-template" class="large-text code" rows="8"><?php echo esc_textarea($tpl); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-template">Reset Template to Default</button></p>

    <?php submit_button(); ?>
  </form>

  <script>
  document.getElementById('cmp-reset-css').onclick = () =>
    document.getElementById('cmp-custom-css').value = <?php echo json_encode($default_css); ?>;
  document.getElementById('cmp-reset-template').onclick = () =>
    document.getElementById('cmp-pin-template').value = <?php echo json_encode($default_tpl); ?>;
  </script>
  </div>
  <?php
}

// 6) Front-end enqueue & localize
add_action('wp_enqueue_scripts', function() {
  wp_enqueue_style('leaflet-css','https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
  wp_enqueue_script('leaflet-js','https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',[],null,true);
  wp_enqueue_style('extra-markers-css',plugin_dir_url(__FILE__).'static/css/leaflet.extra-markers.min.css');
  wp_enqueue_style('cmp-main-css',plugin_dir_url(__FILE__).'static/css/main.css');
  wp_enqueue_script('extra-markers-js',plugin_dir_url(__FILE__).'static/js/leaflet.extra-markers.min.js',['leaflet-js'],null,true);
  wp_enqueue_script('cmp-main-js',plugin_dir_url(__FILE__).'src/main.js',['leaflet-js'],null,true);

  wp_localize_script('cmp-main-js','CMP',[
    'base_url'     => get_site_url(),
    'requiredAcfFields'   => get_option('cmp_requiredAcfFields', []),
    'categories'   => get_option('cmp_categories', []),
    'pin_template' => get_option('cmp_pin_template', '')
  ]);

  $custom_css = get_option('cmp_custom_css','');
  if ($custom_css) wp_add_inline_style('cmp-main-css',$custom_css);
});

// 7) Shortcode
add_shortcode('custom_map', fn() => '
  <h1>Testing Map</h1>
  <div class="container">
    <div id="filter-controls">
      <div id="category-filters"></div>
      <div style="margin-top:10px">
        <input type="checkbox" id="filter-all" checked />
        <label for="filter-all" style="display:inline">Show All</label>
      </div>
    </div>
    <div id="map"></div>
  </div>
');
