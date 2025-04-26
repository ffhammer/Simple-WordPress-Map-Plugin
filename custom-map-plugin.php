<?php
/**
 * Plugin Name: Custom Map Plugin
 * Description: Embed Leaflet map via `[custom_map]` with editable ACF‐field mappings, categories/colors, custom CSS, and custom pin HTML.
 * Version:     1.5.0
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

  register_setting('cmp_settings_group','cmp_post_type_name', [
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field'
  ]);
  // **** NEW: Map Container HTML Structure ****
  register_setting('cmp_settings_group', 'cmp_map_html_structure', [
    'type' => 'string',
    // Standard sanitization. Might be too strict for complex structures. Change with caution!
    'sanitize_callback' => 'wp_kses_post'
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
  // **** NEW: Map HTML Structure Defaults/Saved ****
  $fallback_html_file = plugin_dir_path(__FILE__) . 'static/fall_back_index.html';
  $default_map_html = file_exists($fallback_html_file) ? file_get_contents($fallback_html_file) : '<div id="map"></div>';
  $saved_map_html = get_option('cmp_map_html_structure', '');
  $map_html = trim($saved_map_html) ? $saved_map_html : $default_map_html;
  ?>
  <div class="wrap"><h1>Map Settings</h1>
  <form method="post" action="options.php" id="cmp-settings-form">
    <?php settings_fields('cmp_settings_group'); ?>

    <h2>ACF Field Mappings</h2>
<div style="background: #fff; padding: 1.5rem; margin-bottom: 2rem; border-left: 5px solid #0073aa;">
  <h2>Plugin Overview</h2>
  <p>This plugin allows you to create a fully customizable, filterable Leaflet map based on any WordPress post type. It is ideal for showcasing producers, locations, stores, or any content with geolocation data.</p>

  <h3>Key Features:</h3>
  <ul>
    <li><strong>Shortcode:</strong> Use <code>[custom_map]</code> to embed the map anywhere.</li>
    <li><strong>Post Type:</strong> Select which post type to display (default is <code>producer</code>).</li>
    <li><strong>ACF Field Mapping:</strong> Configure which ACF fields provide latitude, longitude, image, and category data.</li>
    <li><strong>Category Filtering:</strong> Filter markers dynamically based on category, each with customizable colors.</li>
    <li><strong>Customizable Pin Popup HTML:</strong> Define your own popup layout using dynamic variables like <code>${marker.title}</code>, <code>${marker.description}</code>, etc.</li>
    <li><strong>Custom Map CSS:</strong> Adjust styling by overriding the default map CSS directly from the settings panel.</li>
    <li><strong>Additional ACF Fields:</strong> Besides the required ones, any other ACF field can be included in the popup HTML using dynamic variables.</li>
  </ul>

  <h3>Minimum Required ACF Fields:</h3>
  <ul>
    <li><strong>profile_img</strong> — (Media ID) for the profile image of the marker.</li>
    <li><strong>latitude</strong> — (Decimal) for the marker’s latitude.</li>
    <li><strong>longitude</strong> — (Decimal) for the marker’s longitude.</li>
    <li><strong>category</strong> — (Text) used for filtering and setting marker colors.</li>
  </ul>

  <p>All field names are configurable — you can adapt them to your ACF setup without code changes.</p>
</div>

    <?php
    $pt = get_option('cmp_post_type_name','producer');
    ?>
    <h2>Post Type</h2>
    <input type="text"
      name="cmp_post_type_name"
      value="<?php echo esc_attr($pt);?>"
      class="regular-text"/>
    <p class="description">Slug of the CPT to fetch (default <code>producer</code>).</p>

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
  <?php foreach($cats as $i => $c): ?>
    <tr>
      <td>
        <input name="cmp_categories[<?php echo $i; ?>][name]"
               value="<?php echo esc_attr($c['name']); ?>"
               class="regular-text" />
      </td>
      <td>
        <input name="cmp_categories[<?php echo $i; ?>][color]"
               value="<?php echo esc_attr($c['color']); ?>"
               class="cmp-color-field" 
               type="text" />
      </td>
      <td><button type="button" class="button cmp-remove-row">Remove</button></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p><button type="button" id="cmp-add-row" class="button">Add Category</button></p>

<script>
jQuery(document).ready(function($){
  $('.cmp-color-field').wpColorPicker();

  $('#cmp-add-row').on('click', function(e) {
    e.preventDefault();
    const idx = $('#cmp-cats-table tbody tr').length;
    $('#cmp-cats-table tbody').append(`
      <tr>
        <td><input name="cmp_categories[${idx}][name]" class="regular-text" /></td>
        <td><input name="cmp_categories[${idx}][color]" type="text" class="cmp-color-field" /></td>
        <td><button type="button" class="button cmp-remove-row">Remove</button></td>
      </tr>
    `);
    $('#cmp-cats-table tbody tr:last .cmp-color-field').wpColorPicker();
  });

  $(document).on('click', '.cmp-remove-row', function(e){
    e.preventDefault();
    $(this).closest('tr').remove();
  });
});
</script>


    <h2>Custom CSS</h2>
    <textarea name="cmp_custom_css" id="cmp-custom-css" class="large-text code" rows="12"><?php echo esc_textarea($css); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-css">Reset CSS to Default</button></p>

    <h2>Pin Popup Template (HTML)</h2>
    <textarea name="cmp_pin_template" id="cmp-pin-template" class="large-text code" rows="8"><?php echo esc_textarea($tpl); ?></textarea>
    <p><button type="button" class="button" id="cmp-reset-template">Reset Template to Default</button></p>

    <h2>Map Container HTML Structure</h2>
    <p><strong>Advanced:</strong> Directly edit the HTML structure that wraps the map and filters. <strong style="color:red;">Warning:</strong> Ensure essential element IDs like <code>#map</code> and <code>#category-filters</code> are present in your custom HTML, otherwise the map or filtering may break.</p>
    <textarea name="cmp_map_html_structure" id="cmp-map-html-structure" class="large-text code" rows="10"><?php echo esc_textarea($map_html); ?></textarea>
    <p class="description">The default structure is loaded from <code><?php echo esc_html(plugin_dir_path(__FILE__) . 'static/fall_back_index.html'); ?></code>.</p>
    <p><button type="button" class="button" id="cmp-reset-map-html">Reset Map HTML to Default</button></p>
    <?php submit_button(); ?>
  </form>
    
  <script>
  document.getElementById('cmp-reset-css').onclick = () =>
    document.getElementById('cmp-custom-css').value = <?php echo json_encode($default_css); ?>;
  document.getElementById('cmp-reset-template').onclick = () =>
    document.getElementById('cmp-pin-template').value = <?php echo json_encode($default_tpl); ?>;
    document.getElementById('cmp-reset-map-html').onclick = () =>
    document.getElementById('cmp-map-html-structure').value = <?php echo json_encode($default_map_html); ?>;
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
    'base_url'         => get_site_url(),
    'post_type_name'   => get_option('cmp_post_type_name','producer'),
    'requiredAcfFields'=> get_option('cmp_requiredAcfFields', []),
    'categories'       => get_option('cmp_categories', []),
    'pin_template'     => get_option('cmp_pin_template',''),
  ]);

  $custom_css = get_option('cmp_custom_css','');
  if ($custom_css) wp_add_inline_style('cmp-main-css',$custom_css);
});

// 7) Shortcode
add_shortcode('custom_map', function() {
  // Get the saved custom HTML structure
  $saved_map_html = get_option('cmp_map_html_structure', '');

  // Get the fallback HTML structure from file
  $fallback_html_file = plugin_dir_path(__FILE__) . 'static/fall_back_index.html';
  $fallback_map_html = '';
  if (file_exists($fallback_html_file)) {
      $fallback_map_html = file_get_contents($fallback_html_file);
  } else {
      // Define a minimal structure if the file is missing
      $fallback_map_html = '
<div class="container">
  <p style="color:red;">Error: Map fallback HTML file not found at <code>' . esc_html($fallback_html_file) . '</code></p>
  <div id="filter-controls" style="margin-bottom: 15px;">
    <p>Filters:</p>
    <div id="category-filters"><em>Category filters will load here.</em></div>
    <div style="margin-top:10px">
      <input type="checkbox" id="filter-all" checked />
      <label for="filter-all" style="display:inline">Show All</label>
    </div>
  </div>
  <div id="map" style="height: 400px; background: #eee;"><em>Map container</em></div>
</div>';
  }

  // Use the saved HTML if it's not empty, otherwise use the fallback
  $output_html = trim($saved_map_html) ? $saved_map_html : $fallback_map_html;

  // Output the HTML structure directly.
  // It's crucial that the admin saved valid, safe HTML here.
  // The sanitization happened during save (wp_kses_post by default).
  return $output_html;
});
