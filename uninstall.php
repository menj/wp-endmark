<?php
/**
 * Endmark Plugin Uninstall
 * 
 * @package Endmark
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all plugin options for a single site
 */
function endmark_uninstall_cleanup() {
    // Current options (v4.0+)
    delete_option('endmark_settings');
    delete_option('endmark_network_settings');
    delete_option('endmark_presets');
    delete_option('endmark_version');

    // Legacy options (v3.x and earlier)
    delete_option('endmark_type');
    delete_option('endmark_symbol');
    delete_option('endmark_image');
    delete_option('endmark_where');
}

/**
 * Run cleanup for multisite or single site
 */
if (is_multisite()) {
    // Get all site IDs
    $site_ids = get_sites(array('fields' => 'ids'));
    
    foreach ($site_ids as $blog_id) {
        switch_to_blog($blog_id);
        endmark_uninstall_cleanup();
        restore_current_blog();
    }
    
    // Also clean network-level options
    delete_site_option('endmark_network_settings');
} else {
    endmark_uninstall_cleanup();
}
