<?php
/*
Plugin Name: Endmark
Plugin URI: https://menj.net
Description: Adds an end-of-article symbol to posts/pages. Originally developed by Colin Temple, now maintained by MENJ.
Version: 2.1
Author: MENJ (Original code by Colin Temple)
Author URI: https://menj.net
Donate link: https://www.paypal.com/paypalme/menj
Text Domain: endmark
*/

defined('ABSPATH') || exit;

// Initialize plugin options
add_action('admin_init', 'endmark_register_settings');
function endmark_register_settings() {
    register_setting('endmark_settings', 'endmark_type', array(
        'type' => 'string',
        'sanitize_callback' => 'endmark_sanitize_type',
        'default' => 'symbol'
    ));

    register_setting('endmark_settings', 'endmark_symbol', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '#'
    ));

    register_setting('endmark_settings', 'endmark_image', array(
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => ''
    ));

    register_setting('endmark_settings', 'endmark_where', array(
        'type' => 'string',
        'sanitize_callback' => 'endmark_sanitize_where',
        'default' => 'posts'
    ));
}

// Sanitization callbacks
function endmark_sanitize_type($input) {
    return in_array($input, array('symbol', 'image')) ? $input : 'symbol';
}

function endmark_sanitize_where($input) {
    return in_array($input, array('posts', 'pages', 'both')) ? $input : 'posts';
}

// Admin interface
add_action('admin_menu', 'endmark_admin_menu');
function endmark_admin_menu() {
    add_options_page(
        __('Endmark Settings', 'endmark'),
        __('Endmark', 'endmark'),
        'manage_options',
        'endmark-settings',
        'endmark_settings_page'
    );
}

function endmark_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Endmark Settings', 'endmark'); ?></h1>
        
        <div class="credit-notice" style="margin: 20px 0; padding: 15px; background: #f7f7f7;">
            <p>
                <?php
                printf(
                    __('Originally developed by %sColin Temple%s', 'endmark'),
                    '<a href="http://colintemple.com/" target="_blank" rel="noopener">',
                    '</a>'
                );
                ?>
            </p>
        </div>

        <?php if (isset($_GET['settings-updated'])) : ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('Settings saved successfully.', 'endmark'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php 
            settings_fields('endmark_settings');
            do_settings_sections('endmark_settings');
            $type = get_option('endmark_type');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Display Location', 'endmark'); ?></th>
                    <td>
                        <select name="endmark_where">
                            <?php foreach (array('posts', 'pages', 'both') as $option) : ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected(get_option('endmark_where'), $option); ?>>
                                    <?php echo esc_html(ucfirst($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Endmark Type', 'endmark'); ?></th>
                    <td>
                        <select name="endmark_type" id="endmark_type">
                            <option value="symbol" <?php selected($type, 'symbol'); ?>>
                                <?php esc_html_e('Symbol', 'endmark'); ?>
                            </option>
                            <option value="image" <?php selected($type, 'image'); ?>>
                                <?php esc_html_e('Image', 'endmark'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr class="symbol-option" style="<?php echo ($type === 'image') ? 'display:none' : ''; ?>">
                    <th scope="row"><?php esc_html_e('Symbol', 'endmark'); ?></th>
                    <td>
                        <input type="text" name="endmark_symbol" value="<?php echo esc_attr(get_option('endmark_symbol')); ?>">
                    </td>
                </tr>

                <tr class="image-option" style="<?php echo ($type === 'symbol') ? 'display:none' : ''; ?>">
                    <th scope="row"><?php esc_html_e('Image URL', 'endmark'); ?></th>
                    <td>
                        <input type="url" name="endmark_image" value="<?php echo esc_url(get_option('endmark_image')); ?>">
                        <button type="button" class="button endmark-media-upload">
                            <?php esc_html_e('Select Image', 'endmark'); ?>
                        </button>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($){
        $('#endmark_type').change(function(){
            $('.symbol-option, .image-option').toggle();
        });
        
        $('.endmark-media-upload').click(function(e){
            e.preventDefault();
            var button = $(this);
            var customUploader = wp.media({
                title: '<?php esc_html_e('Select Endmark Image', 'endmark'); ?>',
                button: { text: '<?php esc_html_e('Use Image', 'endmark'); ?>' },
                multiple: false
            }).on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                button.siblings('input').val(attachment.url);
            }).open();
        });
    });
    </script>
    <?php
}

/**
 * Adds endmark to content - original concept by Colin Temple
 */
function endmark_add_content($content) {
    if (is_admin()) return $content;
    
    $where = get_option('endmark_where');
    $show_on = array(
        'posts' => is_single(),
        'pages' => is_page(),
        'both' => is_singular()
    );

    if ($show_on[$where] ?? false) {
        $type = get_option('endmark_type');
        $endmark = ($type === 'image') 
            ? '<img src="' . esc_url(get_option('endmark_image')) . '" alt="' . esc_attr__('Article endmark', 'endmark') . '" class="endmark-image">'
            : '<span class="endmark-symbol">' . esc_html(get_option('endmark_symbol')) . '</span>';
        
        $content .= '<div class="endmark-container">' . $endmark . '</div>';
    }
    
    return $content;
}
add_filter('the_content', 'endmark_add_content', 50);

// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'endmark_admin_scripts');
function endmark_admin_scripts($hook) {
    if ($hook !== 'settings_page_endmark-settings') return;
    
    wp_enqueue_media();
    wp_enqueue_style(
        'endmark-admin',
        plugins_url('admin.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'admin.css')
    );
}

// Frontend styles
add_action('wp_enqueue_scripts', 'endmark_frontend_styles');
function endmark_frontend_styles() {
    wp_enqueue_style(
        'endmark-styles',
        plugins_url('endmark.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'endmark.css')
    );
}
