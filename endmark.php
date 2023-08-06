<?php
/*
Plugin Name: Endmark
Plugin URI: http://colintemple.com/
Description: Adds an end-of-article symbol to the end of posts and pages. Original code by Colin Temple, fixed by MENJ.
Version: 2.0
Author: Colin Temple
Author URI: http://colintemple.com/
*/

// Create the options to use in this plugin, if they don't exist already
if (false === get_option("endmark_type")) add_option("endmark_type", "symbol");
if (false === get_option("endmark_symbol")) add_option("endmark_symbol", "#");
if (false === get_option("endmark_image")) add_option("endmark_image");
if (false === get_option("endmark_where")) add_option("endmark_where", "posts");

// Hook for the administration page
add_action('admin_menu', 'endmark_conf');

function endmark_conf_page() { 
    // Check and save settings if form is submitted
    if (isset($_POST['endmark_nonce']) && wp_verify_nonce($_POST['endmark_nonce'], 'save_endmark_settings')) {
        update_option("endmark_type", $_POST["endmark_type"]);
        update_option("endmark_symbol", $_POST["endmark_symbol"]);
        update_option("endmark_image", $_POST["endmark_image"]);
        update_option("endmark_where", $_POST["endmark_where"]);
    }

    // Display the form
    ?>
    <div class="wrap">
        <h2>Endmark Options</h2>
        <form method="post">

            <!-- Type Option -->
            <p>
                <label for="endmark_type">Endmark Type:</label>
                <select id="endmark_type" name="endmark_type">
                    <option value="symbol" <?php selected(get_option("endmark_type"), "symbol"); ?>>Symbol</option>
                    <option value="image" <?php selected(get_option("endmark_type"), "image"); ?>>Image</option>
                </select>
            </p>

            <!-- Symbol Option -->
            <p>
                <label for="endmark_symbol">Endmark Symbol:</label>
                <input type="text" id="endmark_symbol" name="endmark_symbol" value="<?php echo esc_attr(get_option("endmark_symbol")); ?>" />
            </p>

            <!-- Image URL Option -->
            <p>
                <label for="endmark_image">Endmark Image URL:</label>
                <input type="text" id="endmark_image" name="endmark_image" value="<?php echo esc_attr(get_option("endmark_image")); ?>" />
            </p>

            <!-- Where Option -->
            <p>
                <label for="endmark_where">Show Endmark On:</label>
                <select id="endmark_where" name="endmark_where">
                    <option value="posts" <?php selected(get_option("endmark_where"), "posts"); ?>>Posts</option>
                    <option value="pages" <?php selected(get_option("endmark_where"), "pages"); ?>>Pages</option>
                    <option value="both" <?php selected(get_option("endmark_where"), "both"); ?>>Both</option>
                </select>
            </p>
            
            <!-- Nonce field for security -->
            <?php wp_nonce_field('save_endmark_settings', 'endmark_nonce'); ?>

            <p class="submit">
                <input type="submit" name="Submit" value="Save Changes" />
            </p>
        </form>
    </div>
    <?php
}

// Add the administration page for this plugin
function endmark_conf() {
    add_options_page('Endmark Options', 'Endmark', 'manage_options', 'endmark_options_slug', 'endmark_conf_page');
}

if (!function_exists('strripos')) {
    function strripos($haystack, $needle, $offset=0) {
        // ... rest of the function
    }
}

function add_endmark($content) {
    // Depending on the option chosen, add the endmark to the content
    $where = get_option("endmark_where");
    if (($where == "posts" && is_single()) || ($where == "pages" && is_page()) || ($where == "both")) {
        $type = get_option("endmark_type");
        if ($type == "symbol") {
            $content .= get_option("endmark_symbol");
        } elseif ($type == "image") {
            $content .= '<img src="' . get_option("endmark_image") . '" alt="Endmark" />';
        }
    }
    return $content;
}

add_filter('the_content', 'add_endmark', 50);

?>