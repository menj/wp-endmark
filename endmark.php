<?php
/**
 * Plugin Name: Endmark
 * Plugin URI: https://github.com/menj/endmark
 * Description: Adds an end-of-article symbol to the end of posts and pages.
 * Version: 4.2
 * Author: MENJ
 * Author URI: https://menj.org
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: endmark
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Endmark Plugin Class
 */
class Endmark_Plugin {

    /**
     * Plugin version
     */
    const VERSION = '4.2';

    /**
     * Option names
     */
    const OPTION_SITE = 'endmark_settings';
    const OPTION_NETWORK = 'endmark_network_settings';
    const OPTION_PRESETS = 'endmark_presets';
    const OPTION_VERSION = 'endmark_version';

    /**
     * Detection constants
     */
    const ANTI_DUP_COMMENT = '<!-- endmark:inserted -->';
    const WRAPPER_CLASS = 'endmark-wrap';
    const WRAPPER_CLASS_TOKEN = 'class="endmark-wrap"';
    const SHORTCODE_TAG = 'endmark';
    const BLOCK_NAME = 'endmark/endmark';

    /**
     * Protected schema types that endmark must not interfere with
     */
    const PROTECTED_SCHEMA_TYPES = array(
        'FAQPage',
        'HowTo',
        'HowToStep',
        'QAPage',
        'Question',
        'Answer',
        'Recipe',
        'Review',
        'Product',
    );

    /**
     * Allowed schema types for endmark output
     */
    const ALLOWED_SCHEMA_TYPES = array('WebPageElement', 'CreativeWork');

    /**
     * Forbidden schema types that endmark must never output
     */
    const FORBIDDEN_SCHEMA_TYPES = array(
        'Article',
        'BlogPosting',
        'FAQPage',
        'HowTo',
        'BreadcrumbList',
        'Product',
        'Review',
    );

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Site-level defaults
     */
    private $site_defaults = array(
        'enabled'                  => true,
        'where'                    => 'post',
        'type'                     => 'symbol',
        'symbol'                   => '∎',
        'image_url'                => '',
        'image_id'                 => 0,
        'image_size_px'            => 16,
        'placement_mode'           => 'last_paragraph',
        'placement_selector'       => '',
        'min_word_count'           => 0,
        'exclude_categories'       => array(),
        'exclude_tags'             => array(),
        'exclude_post_ids'         => array(),
        'hide_on_mobile'           => false,
        'disable_on_amp'           => true,
        'use_css_variables'        => true,
        'margin_top'               => '0',
        'margin_left'              => '0.25em',
        'typography_scale_enabled' => false,
        'typography_scale_unit'    => 'px',
        'fast_mode'                => false,
        'allow_svg'                => false,
        'schema_enabled'           => false,
        'schema_mode'              => 'WebPageElement',
    );

    /**
     * Allowlists for sanitization
     */
    private $allowlists = array(
        'where'                 => array('post', 'page', 'both'),
        'type'                  => array('symbol', 'image'),
        'placement_mode'        => array('last_paragraph', 'before_footnotes', 'after_footnotes', 'append', 'selector'),
        'typography_scale_unit' => array('px', 'em', 'rem'),
        'schema_mode'           => array('WebPageElement', 'CreativeWork'),
    );

    /**
     * Unsafe HTML containers
     */
    private $unsafe_containers = array('table', 'ul', 'ol', 'blockquote', 'figure', 'pre', 'code');

    /**
     * Footnote detection patterns
     */
    private $footnote_patterns = array(
        '<ol class="footnotes"',
        '<div class="footnotes"',
        '<div id="footnotes"',
        '<section class="footnotes"',
        '<div class="simple-footnotes"',
        '<div id="notes"',
        '<h2 class="wp-block-heading" id="notes"',
        '<h3 class="wp-block-heading" id="notes"',
        '>Notes</h2>',
        '>Notes</h3>',
        '>Notes</',
    );

    /**
     * Protected microdata itemtype patterns (regex)
     */
    private $protected_microdata_patterns = array(
        '/itemtype=["\']https?:\/\/schema\.org\/FAQPage["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/HowTo["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/HowToStep["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/QAPage["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/Question["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/Answer["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/Recipe["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/Review["\']/i',
        '/itemtype=["\']https?:\/\/schema\.org\/Product["\']/i',
    );

    /**
     * Protected Gutenberg comment ranges
     */
    private $protected_gutenberg_ranges = array(
        array('start' => '<!-- wp:yoast/faq-block', 'end' => '<!-- /wp:yoast/faq-block -->'),
        array('start' => '<!-- wp:rank-math/faq-block', 'end' => '<!-- /wp:rank-math/faq-block -->'),
    );

    /**
     * Flag to track if endmark was rendered (for schema output)
     */
    private $endmark_rendered = false;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        add_filter('the_content', array($this, 'insert_endmark'), 9999);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('admin_init', array($this, 'maybe_migrate_options'));
        add_action('init', array($this, 'register_shortcode'));
        add_action('init', array($this, 'register_block'));
        add_action('wp_head', array($this, 'output_schema_json_ld'), 99);
    }

    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'endmark',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Migrate options from older versions
     */
    public function maybe_migrate_options() {
        $current_version = get_option(self::OPTION_VERSION, '0');

        // Migrate from v3.x or earlier
        if (version_compare($current_version, '4.0', '<')) {
            $old_settings = get_option('endmark_settings', array());

            if (!empty($old_settings)) {
                $new_settings = $this->site_defaults;

                // Map old settings to new
                if (isset($old_settings['type'])) {
                    $new_settings['type'] = $old_settings['type'];
                }
                if (isset($old_settings['symbol'])) {
                    $new_settings['symbol'] = $old_settings['symbol'];
                }
                if (isset($old_settings['image'])) {
                    $new_settings['image_url'] = $old_settings['image'];
                }
                if (isset($old_settings['image_id'])) {
                    $new_settings['image_id'] = $old_settings['image_id'];
                }
                if (isset($old_settings['image_size'])) {
                    $new_settings['image_size_px'] = $old_settings['image_size'];
                }
                if (isset($old_settings['where'])) {
                    // Map old values to new
                    $where_map = array(
                        'posts' => 'post',
                        'pages' => 'page',
                        'both'  => 'both',
                    );
                    $new_settings['where'] = isset($where_map[$old_settings['where']]) 
                        ? $where_map[$old_settings['where']] 
                        : 'post';
                }

                update_option(self::OPTION_SITE, $new_settings);
            }

            // Also check for very old individual options
            if (false !== get_option('endmark_type')) {
                $legacy_settings = array(
                    'type'       => get_option('endmark_type', 'symbol'),
                    'symbol'     => get_option('endmark_symbol', '∎'),
                    'image_url'  => get_option('endmark_image', ''),
                    'image_id'   => 0,
                    'where'      => get_option('endmark_where', 'post'),
                );

                $new_settings = wp_parse_args($legacy_settings, $this->site_defaults);
                update_option(self::OPTION_SITE, $new_settings);

                // Clean up old options
                delete_option('endmark_type');
                delete_option('endmark_symbol');
                delete_option('endmark_image');
                delete_option('endmark_where');
            }

            update_option(self::OPTION_VERSION, self::VERSION);
        }
    }

    /**
     * Get settings with defaults
     */
    public function get_settings() {
        $settings = get_option(self::OPTION_SITE, array());
        return wp_parse_args($settings, $this->site_defaults);
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=endmark'),
            esc_html__('Settings', 'endmark')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Endmark Settings', 'endmark'),
            __('Endmark', 'endmark'),
            'manage_options',
            'endmark',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_endmark' !== $hook) {
            return;
        }

        wp_enqueue_media();

        // Enqueue admin CSS
        wp_enqueue_style(
            'endmark-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            self::VERSION
        );

        // Enqueue admin JavaScript
        wp_enqueue_script(
            'endmark-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            self::VERSION,
            true
        );

        // Localize script with data needed by JavaScript
        wp_localize_script('endmark-admin', 'endmarkAdmin', array(
            'optionName' => self::OPTION_SITE,
            'i18n' => array(
                'selectImage' => __('Select Endmark Image', 'endmark'),
                'useImage'    => __('Use this image', 'endmark'),
            ),
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'endmark_settings_group',
            self::OPTION_SITE,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => $this->site_defaults,
            )
        );
    }

    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Boolean fields
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['hide_on_mobile'] = !empty($input['hide_on_mobile']);
        $sanitized['disable_on_amp'] = !empty($input['disable_on_amp']);
        $sanitized['use_css_variables'] = !empty($input['use_css_variables']);
        $sanitized['typography_scale_enabled'] = !empty($input['typography_scale_enabled']);
        $sanitized['fast_mode'] = !empty($input['fast_mode']);
        $sanitized['allow_svg'] = !empty($input['allow_svg']);
        $sanitized['schema_enabled'] = !empty($input['schema_enabled']);

        // Allowlist validated fields
        $sanitized['where'] = isset($input['where']) && in_array($input['where'], $this->allowlists['where'], true) 
            ? $input['where'] 
            : 'post';

        $sanitized['type'] = isset($input['type']) && in_array($input['type'], $this->allowlists['type'], true) 
            ? $input['type'] 
            : 'symbol';

        $sanitized['placement_mode'] = isset($input['placement_mode']) && in_array($input['placement_mode'], $this->allowlists['placement_mode'], true) 
            ? $input['placement_mode'] 
            : 'last_paragraph';

        $sanitized['typography_scale_unit'] = isset($input['typography_scale_unit']) && in_array($input['typography_scale_unit'], $this->allowlists['typography_scale_unit'], true) 
            ? $input['typography_scale_unit'] 
            : 'px';

        $sanitized['schema_mode'] = isset($input['schema_mode']) && in_array($input['schema_mode'], $this->allowlists['schema_mode'], true) 
            ? $input['schema_mode'] 
            : 'WebPageElement';

        // Symbol - sanitize and limit to 12 characters
        $symbol = isset($input['symbol']) ? sanitize_text_field($input['symbol']) : '∎';
        $sanitized['symbol'] = mb_substr($symbol, 0, 12);

        // Image URL
        $sanitized['image_url'] = isset($input['image_url']) ? esc_url_raw($input['image_url']) : '';

        // Image ID
        $sanitized['image_id'] = isset($input['image_id']) ? absint($input['image_id']) : 0;

        // Image size - clamp between 12 and 32
        $image_size = isset($input['image_size_px']) ? absint($input['image_size_px']) : 16;
        $sanitized['image_size_px'] = max(12, min(32, $image_size));

        // Placement selector - validate format
        $selector = isset($input['placement_selector']) ? sanitize_text_field($input['placement_selector']) : '';
        $selector = mb_substr($selector, 0, 200);
        // Validate selector format: must start with . or # followed by alphanumeric, _, -
        if (!empty($selector) && !preg_match('/^(\.[A-Za-z0-9_-]+|\#[A-Za-z0-9_-]+)$/', $selector)) {
            $selector = '';
        }
        $sanitized['placement_selector'] = $selector;

        // Min word count - clamp between 0 and 50000
        $min_words = isset($input['min_word_count']) ? absint($input['min_word_count']) : 0;
        $sanitized['min_word_count'] = max(0, min(50000, $min_words));

        // Exclude arrays
        $sanitized['exclude_categories'] = $this->sanitize_id_array($input, 'exclude_categories');
        $sanitized['exclude_tags'] = $this->sanitize_id_array($input, 'exclude_tags');
        $sanitized['exclude_post_ids'] = $this->sanitize_id_array($input, 'exclude_post_ids');

        // Margin values (CSS)
        $sanitized['margin_top'] = isset($input['margin_top']) ? sanitize_text_field($input['margin_top']) : '0';
        $sanitized['margin_left'] = isset($input['margin_left']) ? sanitize_text_field($input['margin_left']) : '0.25em';

        return $sanitized;
    }

    /**
     * Sanitize array of IDs
     */
    private function sanitize_id_array($input, $key) {
        if (!isset($input[$key])) {
            return array();
        }

        $values = $input[$key];
        if (is_string($values)) {
            $values = array_map('trim', explode(',', $values));
        }

        return array_values(array_filter(array_map('absint', (array) $values)));
    }

    /**
     * Register shortcode
     */
    public function register_shortcode() {
        add_shortcode(self::SHORTCODE_TAG, array($this, 'render_shortcode'));
    }

    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $settings = $this->get_settings();
        return $this->render_endmark_html($settings);
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, array(
            'render_callback' => array($this, 'render_block'),
            'attributes'      => array(
                'useGlobalSettings' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
            ),
        ));
    }

    /**
     * Render block
     */
    public function render_block($attributes) {
        $settings = $this->get_settings();
        return $this->render_endmark_html($settings);
    }

    /**
     * Check if current request is AMP
     */
    private function is_amp_request() {
        return function_exists('amp_is_request') && amp_is_request();
    }

    /**
     * Check if current request is mobile
     */
    private function is_mobile_request() {
        return wp_is_mobile();
    }

    /**
     * Check if post is excluded by category
     */
    private function is_excluded_by_category($post_id, $excluded_categories) {
        if (empty($excluded_categories)) {
            return false;
        }

        $post_categories = wp_get_post_categories($post_id, array('fields' => 'ids'));
        return !empty(array_intersect($post_categories, $excluded_categories));
    }

    /**
     * Check if post is excluded by tag
     */
    private function is_excluded_by_tag($post_id, $excluded_tags) {
        if (empty($excluded_tags)) {
            return false;
        }

        $post_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
        return !empty(array_intersect($post_tags, $excluded_tags));
    }

    /**
     * Count words in content
     */
    private function count_words($content) {
        $text = wp_strip_all_tags($content);
        return str_word_count($text);
    }

    /**
     * Check eligibility for endmark insertion
     */
    private function check_eligibility($content, $settings) {
        // Must be singular view
        if (!is_singular()) {
            return false;
        }

        // Must be in the loop
        if (!in_the_loop()) {
            return false;
        }

        // Must be main query
        if (!is_main_query()) {
            return false;
        }

        // Not in feeds
        if (is_feed()) {
            return false;
        }

        // Check if enabled
        if (empty($settings['enabled'])) {
            return false;
        }

        // Check where setting
        $where = $settings['where'];
        $is_post = is_singular('post');
        $is_page = is_page();

        if ('post' === $where && !$is_post) {
            return false;
        }
        if ('page' === $where && !$is_page) {
            return false;
        }
        if ('both' === $where && !$is_post && !$is_page) {
            return false;
        }

        $post_id = get_the_ID();

        // Check excluded post IDs
        if (!empty($settings['exclude_post_ids']) && in_array($post_id, $settings['exclude_post_ids'], true)) {
            return false;
        }

        // Check min word count
        if ($settings['min_word_count'] > 0 && $this->count_words($content) < $settings['min_word_count']) {
            return false;
        }

        // Check excluded categories
        if ($this->is_excluded_by_category($post_id, $settings['exclude_categories'])) {
            return false;
        }

        // Check excluded tags
        if ($this->is_excluded_by_tag($post_id, $settings['exclude_tags'])) {
            return false;
        }

        // Check AMP
        if ($settings['disable_on_amp'] && $this->is_amp_request()) {
            return false;
        }

        // Check mobile
        if ($settings['hide_on_mobile'] && $this->is_mobile_request()) {
            return false;
        }

        return true;
    }

    /**
     * Check for manual placement (shortcode, block, or already inserted)
     */
    private function has_manual_placement($content) {
        // Check for shortcode
        if (has_shortcode($content, self::SHORTCODE_TAG)) {
            return true;
        }

        // Check for block
        if (has_block(self::BLOCK_NAME)) {
            return true;
        }

        // Check for wrapper class token
        if (false !== strpos($content, self::WRAPPER_CLASS_TOKEN)) {
            return true;
        }

        // Check for anti-duplication comment
        if (false !== strpos($content, self::ANTI_DUP_COMMENT)) {
            return true;
        }

        return false;
    }

    /**
     * Hygiene prepass - clean up content
     */
    private function hygiene_prepass($content) {
        // Trim trailing whitespace
        $content = rtrim($content);

        // Remove trailing empty paragraphs
        $content = preg_replace('/(<p[^>]*>\s*(&nbsp;|\s)*<\/p>\s*)+$/i', '', $content);

        // Collapse trailing repeated br tags
        $content = preg_replace('/(<br\s*\/?>\s*){2,}$/i', '', $content);

        return $content;
    }

    /**
     * Find all protected microdata ranges in content
     * 
     * @param string $content The content to search
     * @return array Array of protected ranges with 'start' and 'end' positions
     */
    private function find_protected_microdata_ranges($content) {
        $ranges = array();

        foreach ($this->protected_microdata_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $start_pos = $match[1];
                    
                    // Find the opening tag that contains this itemtype
                    $tag_start = strrpos(substr($content, 0, $start_pos + 1), '<');
                    if ($tag_start === false) {
                        continue;
                    }

                    // Extract tag name
                    preg_match('/<([a-z0-9]+)/i', substr($content, $tag_start, 50), $tag_match);
                    if (empty($tag_match[1])) {
                        continue;
                    }
                    $tag_name = $tag_match[1];

                    // Find the closing tag
                    $end_pos = $this->find_closing_tag_position($content, $tag_name, $tag_start);
                    if ($end_pos !== false) {
                        $ranges[] = array(
                            'start' => $tag_start,
                            'end'   => $end_pos,
                            'type'  => 'microdata',
                        );
                    }
                }
            }
        }

        return $ranges;
    }

    /**
     * Find all protected Gutenberg block ranges in content
     * 
     * @param string $content The content to search
     * @return array Array of protected ranges with 'start' and 'end' positions
     */
    private function find_protected_gutenberg_ranges($content) {
        $ranges = array();

        foreach ($this->protected_gutenberg_ranges as $range_def) {
            $search_pos = 0;
            while (($start_pos = strpos($content, $range_def['start'], $search_pos)) !== false) {
                $end_pos = strpos($content, $range_def['end'], $start_pos);
                if ($end_pos !== false) {
                    $ranges[] = array(
                        'start' => $start_pos,
                        'end'   => $end_pos + strlen($range_def['end']),
                        'type'  => 'gutenberg',
                    );
                    $search_pos = $end_pos + strlen($range_def['end']);
                } else {
                    break;
                }
            }
        }

        return $ranges;
    }

    /**
     * Find all protected ranges in content (combined microdata and Gutenberg)
     * 
     * @param string $content The content to search
     * @return array Array of all protected ranges, sorted by start position
     */
    private function find_all_protected_ranges($content) {
        $microdata_ranges = $this->find_protected_microdata_ranges($content);
        $gutenberg_ranges = $this->find_protected_gutenberg_ranges($content);
        
        $all_ranges = array_merge($microdata_ranges, $gutenberg_ranges);
        
        // Sort by start position
        usort($all_ranges, function($a, $b) {
            return $a['start'] - $b['start'];
        });

        // Allow filtering of protected patterns
        return apply_filters('endmark_protected_patterns', $all_ranges, $content);
    }

    /**
     * Find closing tag position for a given tag
     * 
     * @param string $content The content
     * @param string $tag_name The tag name to find closing for
     * @param int $start_pos Position of opening tag
     * @return int|false Position after closing tag or false if not found
     */
    private function find_closing_tag_position($content, $tag_name, $start_pos) {
        $depth = 0;
        $pos = $start_pos;
        $len = strlen($content);
        $tag_lower = strtolower($tag_name);

        while ($pos < $len) {
            $next_open = stripos($content, '<' . $tag_name, $pos + 1);
            $next_close = stripos($content, '</' . $tag_name, $pos + 1);

            if ($next_close === false) {
                return false;
            }

            if ($next_open !== false && $next_open < $next_close) {
                // Check if it's actually an opening tag (not self-closing)
                $tag_end = strpos($content, '>', $next_open);
                if ($tag_end !== false && $content[$tag_end - 1] !== '/') {
                    $depth++;
                }
                $pos = $next_open;
            } else {
                if ($depth === 0) {
                    // Find the end of this closing tag
                    $close_end = strpos($content, '>', $next_close);
                    return $close_end !== false ? $close_end + 1 : false;
                }
                $depth--;
                $pos = $next_close;
            }
        }

        return false;
    }

    /**
     * Check if a position falls within any protected range
     * 
     * @param int $position The position to check
     * @param array $protected_ranges Array of protected ranges
     * @return bool True if position is within a protected range
     */
    private function is_position_in_protected_range($position, $protected_ranges) {
        foreach ($protected_ranges as $range) {
            if ($position >= $range['start'] && $position < $range['end']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find the end of the protected range that contains a position
     * 
     * @param int $position The position to check
     * @param array $protected_ranges Array of protected ranges
     * @return int|false End position of the containing range or false
     */
    private function find_protected_range_end($position, $protected_ranges) {
        foreach ($protected_ranges as $range) {
            if ($position >= $range['start'] && $position < $range['end']) {
                return $range['end'];
            }
        }
        return false;
    }

    /**
     * Adjust insertion point to avoid protected ranges
     * Uses "move_after_protected_range_or_append" strategy
     * 
     * @param string $content The content
     * @param int $insertion_point The original insertion point
     * @param array $protected_ranges Array of protected ranges
     * @return int Adjusted insertion point
     */
    private function adjust_insertion_for_protected_ranges($content, $insertion_point, $protected_ranges) {
        if (empty($protected_ranges)) {
            return $insertion_point;
        }

        // Check if insertion point is in a protected range
        if ($this->is_position_in_protected_range($insertion_point, $protected_ranges)) {
            // Move to after the protected range
            $range_end = $this->find_protected_range_end($insertion_point, $protected_ranges);
            if ($range_end !== false) {
                return $range_end;
            }
            // Fallback to end of content
            return strlen($content);
        }

        return $insertion_point;
    }

    /**
     * Find footnotes position in content
     */
    private function find_footnotes_position($content) {
        foreach ($this->footnote_patterns as $pattern) {
            $pos = stripos($content, $pattern);
            if (false !== $pos) {
                return $pos;
            }
        }
        return false;
    }

    /**
     * Check if position is inside an unsafe container
     */
    private function is_inside_unsafe_container($content, $position) {
        foreach ($this->unsafe_containers as $tag) {
            // Find all opening and closing tags before position
            $open_pattern = '/<' . $tag . '\b[^>]*>/i';
            $close_pattern = '/<\/' . $tag . '>/i';

            preg_match_all($open_pattern, substr($content, 0, $position), $opens, PREG_OFFSET_CAPTURE);
            preg_match_all($close_pattern, substr($content, 0, $position), $closes, PREG_OFFSET_CAPTURE);

            $open_count = count($opens[0]);
            $close_count = count($closes[0]);

            // If we have more opens than closes, we're inside
            if ($open_count > $close_count) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find insertion point based on selector mode
     */
    private function find_selector_insertion_point($content, $selector) {
        // Convert CSS selector to regex pattern
        $selector_type = substr($selector, 0, 1);
        $selector_value = substr($selector, 1);

        if ($selector_type === '.') {
            // Class selector
            $pattern = '/<[^>]*\bclass\s*=\s*["\'][^"\']*\b' . preg_quote($selector_value, '/') . '\b[^"\']*["\'][^>]*>/i';
        } elseif ($selector_type === '#') {
            // ID selector
            $pattern = '/<[^>]*\bid\s*=\s*["\']' . preg_quote($selector_value, '/') . '["\'][^>]*>/i';
        } else {
            return false;
        }

        if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            // Insert before the matched element
            return $match[0][1];
        }

        return false;
    }

    /**
     * Find last valid paragraph for insertion
     */
    private function find_last_paragraph_insertion_point($content, $footnotes_pos = false) {
        if (!preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $last_valid_index = -1;

        foreach ($matches[0] as $i => $match) {
            $p_start = $match[1];
            $p_html = $match[0];
            $p_end = $p_start + strlen($p_html);

            // If footnotes exist, only consider paragraphs that end before footnotes start
            if (false !== $footnotes_pos && $p_end > $footnotes_pos) {
                continue;
            }

            // Check if inside unsafe container
            if ($this->is_inside_unsafe_container($content, $p_start)) {
                continue;
            }

            // Check if paragraph has real content
            $inner_html = $matches[1][$i][0];
            $text = trim(wp_strip_all_tags(str_replace('&nbsp;', '', preg_replace('/<br\s*\/?>/i', '', $inner_html))));

            if ($text !== '') {
                $last_valid_index = $i;
            }
        }

        if ($last_valid_index === -1) {
            return false;
        }

        // Return position just before closing </p> tag
        $p_html = $matches[0][$last_valid_index][0];
        $p_start = $matches[0][$last_valid_index][1];
        $close_tag_pos = strripos($p_html, '</p>');

        return $p_start + $close_tag_pos;
    }

    /**
     * Insert endmark into content
     */
    public function insert_endmark($content) {
        $settings = $this->get_settings();

        // Check eligibility
        if (!$this->check_eligibility($content, $settings)) {
            return $content;
        }

        // Check for manual placement
        if ($this->has_manual_placement($content)) {
            return $content;
        }

        // Get endmark HTML
        $endmark = $this->render_endmark_html($settings);
        if (empty($endmark)) {
            return $content;
        }

        // Hygiene prepass
        $content = $this->hygiene_prepass($content);

        // Find protected ranges (microdata and Gutenberg blocks)
        $protected_ranges = $this->find_all_protected_ranges($content);

        // Find footnotes position
        $footnotes_pos = $this->find_footnotes_position($content);

        // Determine insertion point based on placement mode
        $placement_mode = $settings['placement_mode'];
        $insertion_point = false;

        // fail_safe: insertion_failure = return_original_content
        try {
            switch ($placement_mode) {
                case 'selector':
                    if (!empty($settings['placement_selector'])) {
                        $insertion_point = $this->find_selector_insertion_point($content, $settings['placement_selector']);
                    }
                    // Fallback to append
                    if (false === $insertion_point) {
                        $this->endmark_rendered = true;
                        return $content . $endmark;
                    }
                    // Adjust for protected ranges
                    $insertion_point = $this->adjust_insertion_for_protected_ranges($content, $insertion_point, $protected_ranges);
                    $this->endmark_rendered = true;
                    return substr_replace($content, $endmark, $insertion_point, 0);

                case 'before_footnotes':
                    if (false !== $footnotes_pos) {
                        $adjusted_pos = $this->adjust_insertion_for_protected_ranges($content, $footnotes_pos, $protected_ranges);
                        $this->endmark_rendered = true;
                        return substr_replace($content, $endmark, $adjusted_pos, 0);
                    }
                    // Fallback to last_paragraph, then append
                    $insertion_point = $this->find_last_paragraph_insertion_point($content);
                    if (false !== $insertion_point) {
                        $insertion_point = $this->adjust_insertion_for_protected_ranges($content, $insertion_point, $protected_ranges);
                        $this->endmark_rendered = true;
                        return substr_replace($content, $endmark, $insertion_point, 0);
                    }
                    $this->endmark_rendered = true;
                    return $content . $endmark;

                case 'after_footnotes':
                    // Append after content (including footnotes)
                    $this->endmark_rendered = true;
                    return $content . $endmark;

                case 'append':
                    $this->endmark_rendered = true;
                    return $content . $endmark;

                case 'last_paragraph':
                default:
                    $insertion_point = $this->find_last_paragraph_insertion_point($content, $footnotes_pos);
                    if (false !== $insertion_point) {
                        // Adjust for protected ranges
                        $insertion_point = $this->adjust_insertion_for_protected_ranges($content, $insertion_point, $protected_ranges);
                        $this->endmark_rendered = true;
                        return substr_replace($content, $endmark, $insertion_point, 0);
                    }
                    // Fallback: try before_footnotes
                    if (false !== $footnotes_pos) {
                        $adjusted_pos = $this->adjust_insertion_for_protected_ranges($content, $footnotes_pos, $protected_ranges);
                        $this->endmark_rendered = true;
                        return substr_replace($content, $endmark, $adjusted_pos, 0);
                    }
                    // Final fallback: append
                    $this->endmark_rendered = true;
                    return $content . $endmark;
            }
        } catch (Exception $e) {
            // fail_safe: insertion_failure = return_original_content
            return $content;
        }
    }

    /**
     * Render endmark HTML
     */
    private function render_endmark_html($settings) {
        $inner = '';

        if ('symbol' === $settings['type']) {
            $symbol = esc_html($settings['symbol']);
            if (empty($symbol)) {
                // fail_safe: svg_failure = fallback_symbol
                $symbol = '∎';
            }
            $inner = '<span class="endmark endmark-symbol">' . $symbol . '</span>';
        } elseif ('image' === $settings['type'] && !empty($settings['image_url'])) {
            $url = esc_url($settings['image_url']);
            $size = absint($settings['image_size_px']);
            
            // Check if SVG and allowed
            $is_svg = $this->is_svg_url($url);
            if ($is_svg && empty($settings['allow_svg'])) {
                // fail_safe: svg_failure = fallback_symbol
                $inner = '<span class="endmark endmark-symbol">∎</span>';
            } else {
                $inner = sprintf(
                    '<img class="endmark endmark-image" src="%s" alt="" width="%d" height="%d" loading="lazy" decoding="async" />',
                    $url,
                    $size,
                    $size
                );
            }
        } else if ('image' === $settings['type'] && empty($settings['image_url'])) {
            // fail_safe: svg_failure = fallback_symbol (no image configured, use symbol)
            $inner = '<span class="endmark endmark-symbol">∎</span>';
        }

        if (empty($inner)) {
            return '';
        }

        // Build wrapper with microdata support placeholder
        $microdata = '';
        $html = '<span class="' . self::WRAPPER_CLASS . '" aria-hidden="true" role="presentation"' . $microdata . '>' . $inner . '</span>' . self::ANTI_DUP_COMMENT;

        return $html;
    }

    /**
     * Check if a URL points to an SVG file
     * 
     * @param string $url The URL to check
     * @return bool True if URL appears to be an SVG
     */
    private function is_svg_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return false;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $extension === 'svg';
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        if (!is_singular()) {
            return;
        }

        $settings = $this->get_settings();

        // Build CSS
        $margin_top = sanitize_text_field($settings['margin_top']);
        $margin_left = sanitize_text_field($settings['margin_left']);

        $css = '.endmark-wrap{display:inline;white-space:nowrap;vertical-align:baseline;';
        
        if ($settings['use_css_variables']) {
            $css .= '--endmark-margin-top:' . $margin_top . ';--endmark-margin-left:' . $margin_left . ';';
            $css .= 'margin-top:var(--endmark-margin-top);margin-left:var(--endmark-margin-left);';
        } else {
            $css .= 'margin-top:' . $margin_top . ';margin-left:' . $margin_left . ';';
        }
        
        $css .= '}';

        // Symbol styles
        $css .= '.endmark-symbol{display:inline;vertical-align:baseline;}';

        // Image styles
        $size = absint($settings['image_size_px']);
        $css .= '.endmark-image{display:inline-block;vertical-align:baseline;';
        $css .= 'width:' . $size . 'px;height:' . $size . 'px;';
        $css .= 'max-height:1em;width:auto;height:auto;';
        $css .= 'opacity:1!important;transform:none!important;}';

        // Fix for themes that animate images
        $css .= '.endmark-image[class*="td-animation"]{transform:none!important;animation:none!important;opacity:1!important;}';

        // Mobile hide styles if enabled
        if ($settings['hide_on_mobile']) {
            $css .= '@media (max-width: 767px){.endmark-wrap{display:none!important;}}';
        }

        // Typography scale support
        if ($settings['typography_scale_enabled']) {
            $unit = $settings['typography_scale_unit'];
            $css .= '.endmark-wrap{font-size:inherit;line-height:inherit;}';
        }

        wp_register_style('endmark-style', false, array(), self::VERSION);
        wp_enqueue_style('endmark-style');
        wp_add_inline_style('endmark-style', $css);
    }

    /**
     * Output schema.org JSON-LD for endmark
     * 
     * Only outputs when:
     * - schema_enabled setting is true
     * - endmark was actually rendered (requires_render_flag)
     * - schema_mode is an allowed type (WebPageElement or CreativeWork)
     * - schema_mode is NOT a forbidden type
     */
    public function output_schema_json_ld() {
        // fail_safe: schema_failure = skip_output
        try {
            $settings = $this->get_settings();

            // Check if schema output is enabled
            if (empty($settings['schema_enabled'])) {
                return;
            }

            // Check if endmark was rendered (requires_render_flag)
            if (!$this->endmark_rendered) {
                return;
            }

            // Check if we're on a singular view
            if (!is_singular()) {
                return;
            }

            $schema_mode = $settings['schema_mode'];

            // Validate schema mode is in allowed types
            if (!in_array($schema_mode, self::ALLOWED_SCHEMA_TYPES, true)) {
                return;
            }

            // Ensure schema mode is not in forbidden types
            if (in_array($schema_mode, self::FORBIDDEN_SCHEMA_TYPES, true)) {
                return;
            }

            $permalink = get_permalink();
            if (!$permalink) {
                return;
            }

            // Build base schema payload
            $schema = array(
                '@context'    => 'https://schema.org',
                '@type'       => $schema_mode,
                '@id'         => $permalink . '#endmark',
                'name'        => 'Endmark',
                'description' => 'End-of-content marker',
                'isPartOf'    => array(
                    '@id' => $permalink,
                ),
            );

            // Add conditional image fields if image type is used
            if ('image' === $settings['type'] && !empty($settings['image_url'])) {
                $image_url = $settings['image_url'];
                $mime_type = $this->derive_mime_type($image_url);

                if ($mime_type) {
                    $schema['image'] = array(
                        'contentUrl'     => $image_url,
                        'encodingFormat' => $mime_type,
                    );
                }
            }

            // Allow filtering of schema payload
            $schema = apply_filters('endmark_schema_payload', $schema, $settings, $permalink);

            // Validate that filtered schema doesn't use forbidden types
            if (isset($schema['@type']) && in_array($schema['@type'], self::FORBIDDEN_SCHEMA_TYPES, true)) {
                return;
            }

            // Output JSON-LD
            $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json) {
                echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
            }
        } catch (Exception $e) {
            // fail_safe: schema_failure = skip_output
            return;
        }
    }

    /**
     * Derive MIME type from image URL
     * 
     * @param string $url The image URL
     * @return string|false MIME type or false if cannot determine
     */
    private function derive_mime_type($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $mime_map = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'bmp'  => 'image/bmp',
        );

        return isset($mime_map[$extension]) ? $mime_map[$extension] : false;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'endmark'));
        }

        $settings = $this->get_settings();
        $categories = get_categories(array('hide_empty' => false));
        $tags = get_tags(array('hide_empty' => false));
        ?>
        <div class="wrap endmark-wrap-page">
            <!-- Header -->
            <div class="endmark-header">
                <div class="endmark-header-content">
                    <div class="endmark-header-left">
                        <h1><?php esc_html_e('Endmark', 'endmark'); ?></h1>
                        <p><?php esc_html_e('Professional typographic endmarks for your content', 'endmark'); ?></p>
                    </div>
                    <div class="endmark-header-right">
                        <span class="endmark-version">v<?php echo esc_html(self::VERSION); ?></span>
                        <div class="endmark-master-toggle">
                            <span><?php esc_html_e('Active', 'endmark'); ?></span>
                            <div class="endmark-toggle">
                                <input type="checkbox" form="endmark-settings-form" name="<?php echo esc_attr(self::OPTION_SITE); ?>[enabled]" id="endmark_enabled" value="1" <?php checked($settings['enabled']); ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php" id="endmark-settings-form">
                <?php settings_fields('endmark_settings_group'); ?>
                
                <div class="endmark-body">
                    <!-- Tab Navigation -->
                    <div class="endmark-tabs">
                        <button type="button" class="endmark-tab active" data-tab="appearance">
                            <span class="dashicons dashicons-art"></span>
                            <?php esc_html_e('Appearance', 'endmark'); ?>
                        </button>
                        <button type="button" class="endmark-tab" data-tab="placement">
                            <span class="dashicons dashicons-location"></span>
                            <?php esc_html_e('Placement', 'endmark'); ?>
                        </button>
                        <button type="button" class="endmark-tab" data-tab="exclusions">
                            <span class="dashicons dashicons-hidden"></span>
                            <?php esc_html_e('Exclusions', 'endmark'); ?>
                        </button>
                        <button type="button" class="endmark-tab" data-tab="advanced">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('Advanced', 'endmark'); ?>
                        </button>
                    </div>

                    <!-- TAB: Appearance -->
                    <div id="endmark-tab-appearance" class="endmark-tab-content active">
                        
                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Endmark Type', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Choose between a typographic symbol or a custom image for your endmark.', 'endmark'); ?></p>
                            
                            <div class="endmark-type-grid">
                                <label class="endmark-type-option">
                                    <input type="radio" name="<?php echo esc_attr(self::OPTION_SITE); ?>[type]" value="symbol" <?php checked($settings['type'], 'symbol'); ?>>
                                    <div class="type-icon"><span class="dashicons dashicons-editor-textcolor"></span></div>
                                    <div class="type-label"><?php esc_html_e('Symbol', 'endmark'); ?></div>
                                    <div class="type-desc"><?php esc_html_e('Unicode character', 'endmark'); ?></div>
                                </label>
                                
                                <label class="endmark-type-option">
                                    <input type="radio" name="<?php echo esc_attr(self::OPTION_SITE); ?>[type]" value="image" <?php checked($settings['type'], 'image'); ?>>
                                    <div class="type-icon"><span class="dashicons dashicons-format-image"></span></div>
                                    <div class="type-label"><?php esc_html_e('Image', 'endmark'); ?></div>
                                    <div class="type-desc"><?php esc_html_e('Custom graphic', 'endmark'); ?></div>
                                </label>
                            </div>

                            <!-- Symbol Options Panel -->
                            <div id="endmark-symbol-options" class="endmark-options-panel">
                                <div class="endmark-field">
                                    <label for="endmark_symbol"><?php esc_html_e('Symbol Character', 'endmark'); ?></label>
                                    <input type="text" name="<?php echo esc_attr(self::OPTION_SITE); ?>[symbol]" id="endmark_symbol" value="<?php echo esc_attr($settings['symbol']); ?>" maxlength="12">
                                    <div class="endmark-symbols">
                                        <?php
                                        $symbols = array('∎', '◆', '❧', '■', '●', '※', '❖', '◾', '▪', '#');
                                        foreach ($symbols as $sym) {
                                            printf('<button type="button" class="endmark-sym-btn" data-symbol="%s">%s</button>', esc_attr($sym), esc_html($sym));
                                        }
                                        ?>
                                    </div>
                                    <p class="hint"><?php esc_html_e('Click a symbol above or type your own (max 12 characters).', 'endmark'); ?></p>
                                </div>
                            </div>

                            <!-- Image Options Panel -->
                            <div id="endmark-image-options" class="endmark-options-panel">
                                <div class="endmark-field">
                                    <label><?php esc_html_e('Endmark Image', 'endmark'); ?></label>
                                    <div class="endmark-image-upload">
                                        <div class="endmark-image-box <?php echo !empty($settings['image_url']) ? 'has-image' : ''; ?>">
                                            <?php if (!empty($settings['image_url'])) : ?>
                                                <img src="<?php echo esc_url($settings['image_url']); ?>" alt="">
                                            <?php else : ?>
                                                <span class="dashicons dashicons-format-image"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="endmark-image-actions">
                                            <button type="button" id="endmark-upload-btn" class="button button-primary">
                                                <span class="dashicons dashicons-upload"></span>
                                                <?php esc_html_e('Upload Image', 'endmark'); ?>
                                            </button>
                                            <button type="button" id="endmark-remove-btn" class="button button-secondary" style="<?php echo empty($settings['image_url']) ? 'display:none;' : ''; ?>">
                                                <?php esc_html_e('Remove', 'endmark'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_SITE); ?>[image_url]" id="endmark_image_url" value="<?php echo esc_url($settings['image_url']); ?>">
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_SITE); ?>[image_id]" id="endmark_image_id" value="<?php echo esc_attr($settings['image_id']); ?>">
                                </div>
                                
                                <div class="endmark-field" style="margin-top: 24px;">
                                    <label for="endmark_image_size_px"><?php esc_html_e('Image Size', 'endmark'); ?></label>
                                    <div class="endmark-size-control">
                                        <input type="range" name="<?php echo esc_attr(self::OPTION_SITE); ?>[image_size_px]" id="endmark_image_size_px" min="12" max="32" value="<?php echo esc_attr($settings['image_size_px']); ?>">
                                        <span id="endmark-size-value"><?php echo esc_html($settings['image_size_px']); ?>px</span>
                                    </div>
                                    <p class="hint"><?php esc_html_e('Adjust the display size of the endmark image (12-32px).', 'endmark'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Live Preview -->
                        <div class="endmark-preview-container">
                            <div class="endmark-preview-label">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e('Live Preview', 'endmark'); ?>
                            </div>
                            <div class="endmark-preview-box">
                                <?php esc_html_e('This is how your endmark will appear at the end of your content, giving your articles a professional, polished finish.', 'endmark'); ?><span id="endmark-live-mark"><?php echo $this->render_endmark_html($settings); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: Placement -->
                    <div id="endmark-tab-placement" class="endmark-tab-content">
                        
                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Display Location', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Select which content types should display the endmark.', 'endmark'); ?></p>
                            
                            <div class="endmark-field">
                                <label for="endmark_where"><?php esc_html_e('Show endmark on', 'endmark'); ?></label>
                                <select name="<?php echo esc_attr(self::OPTION_SITE); ?>[where]" id="endmark_where">
                                    <option value="post" <?php selected($settings['where'], 'post'); ?>><?php esc_html_e('Posts only', 'endmark'); ?></option>
                                    <option value="page" <?php selected($settings['where'], 'page'); ?>><?php esc_html_e('Pages only', 'endmark'); ?></option>
                                    <option value="both" <?php selected($settings['where'], 'both'); ?>><?php esc_html_e('Posts and Pages', 'endmark'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="endmark-divider"></div>

                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Insertion Point', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Control exactly where the endmark appears within your content.', 'endmark'); ?></p>
                            
                            <div class="endmark-field">
                                <label for="endmark_placement_mode"><?php esc_html_e('Insert endmark', 'endmark'); ?></label>
                                <select name="<?php echo esc_attr(self::OPTION_SITE); ?>[placement_mode]" id="endmark_placement_mode">
                                    <option value="last_paragraph" <?php selected($settings['placement_mode'], 'last_paragraph'); ?>><?php esc_html_e('At end of last paragraph', 'endmark'); ?></option>
                                    <option value="before_footnotes" <?php selected($settings['placement_mode'], 'before_footnotes'); ?>><?php esc_html_e('Before footnotes', 'endmark'); ?></option>
                                    <option value="after_footnotes" <?php selected($settings['placement_mode'], 'after_footnotes'); ?>><?php esc_html_e('After footnotes (end of content)', 'endmark'); ?></option>
                                    <option value="append" <?php selected($settings['placement_mode'], 'append'); ?>><?php esc_html_e('Append to end', 'endmark'); ?></option>
                                    <option value="selector" <?php selected($settings['placement_mode'], 'selector'); ?>><?php esc_html_e('Before CSS selector', 'endmark'); ?></option>
                                </select>
                            </div>

                            <div class="endmark-field endmark-selector-field" style="margin-top: 20px; <?php echo $settings['placement_mode'] !== 'selector' ? 'display:none;' : ''; ?>">
                                <label for="endmark_placement_selector"><?php esc_html_e('CSS Selector', 'endmark'); ?></label>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_SITE); ?>[placement_selector]" id="endmark_placement_selector" value="<?php echo esc_attr($settings['placement_selector']); ?>" placeholder=".my-class or #my-id" maxlength="200" style="width: 100%; max-width: 400px;">
                                <p class="hint"><?php esc_html_e('Enter a class (.classname) or ID (#idname) selector. Endmark will be inserted before this element.', 'endmark'); ?></p>
                            </div>
                        </div>

                        <div class="endmark-divider"></div>

                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Manual Placement', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('For precise control, you can manually place the endmark using shortcodes or blocks.', 'endmark'); ?></p>
                            
                            <div class="endmark-info">
                                <span class="dashicons dashicons-info"></span>
                                <p>
                                    <?php printf(esc_html__('Use the shortcode %1$s or the %2$s block in the editor. When manually placed, automatic insertion is disabled for that post.', 'endmark'), '<code>[endmark]</code>', '<code>Endmark</code>'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: Exclusions -->
                    <div id="endmark-tab-exclusions" class="endmark-tab-content">
                        
                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Content Requirements', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Set minimum content length for endmark display.', 'endmark'); ?></p>
                            
                            <div class="endmark-field">
                                <label for="endmark_min_word_count"><?php esc_html_e('Minimum Word Count', 'endmark'); ?></label>
                                <input type="number" name="<?php echo esc_attr(self::OPTION_SITE); ?>[min_word_count]" id="endmark_min_word_count" value="<?php echo esc_attr($settings['min_word_count']); ?>" min="0" max="50000" style="width: 140px;">
                                <p class="hint"><?php esc_html_e('Only show endmark on posts with at least this many words. Set to 0 to disable.', 'endmark'); ?></p>
                            </div>
                        </div>

                        <?php if (!empty($categories) || !empty($tags)) : ?>
                        <div class="endmark-divider"></div>
                        
                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Taxonomy Exclusions', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Exclude posts in specific categories or with certain tags from displaying endmarks.', 'endmark'); ?></p>
                            
                            <div class="endmark-grid">
                                <?php if (!empty($categories)) : ?>
                                <div class="endmark-field">
                                    <label for="endmark_exclude_categories"><?php esc_html_e('Exclude Categories', 'endmark'); ?></label>
                                    <select name="<?php echo esc_attr(self::OPTION_SITE); ?>[exclude_categories][]" id="endmark_exclude_categories" multiple class="endmark-multiselect" style="width: 100%;">
                                        <?php foreach ($categories as $cat) : ?>
                                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected(in_array($cat->term_id, $settings['exclude_categories'])); ?>><?php echo esc_html($cat->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="hint"><?php esc_html_e('Hold Ctrl/Cmd to select multiple.', 'endmark'); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($tags)) : ?>
                                <div class="endmark-field">
                                    <label for="endmark_exclude_tags"><?php esc_html_e('Exclude Tags', 'endmark'); ?></label>
                                    <select name="<?php echo esc_attr(self::OPTION_SITE); ?>[exclude_tags][]" id="endmark_exclude_tags" multiple class="endmark-multiselect" style="width: 100%;">
                                        <?php foreach ($tags as $tag) : ?>
                                            <option value="<?php echo esc_attr($tag->term_id); ?>" <?php selected(in_array($tag->term_id, $settings['exclude_tags'])); ?>><?php echo esc_html($tag->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="hint"><?php esc_html_e('Hold Ctrl/Cmd to select multiple.', 'endmark'); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="endmark-divider"></div>

                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Specific Posts', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Exclude specific posts by their IDs.', 'endmark'); ?></p>
                            
                            <div class="endmark-field">
                                <label for="endmark_exclude_post_ids"><?php esc_html_e('Exclude Post IDs', 'endmark'); ?></label>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_SITE); ?>[exclude_post_ids]" id="endmark_exclude_post_ids" value="<?php echo esc_attr(implode(', ', $settings['exclude_post_ids'])); ?>" style="width: 100%; max-width: 500px;" placeholder="123, 456, 789">
                                <p class="hint"><?php esc_html_e('Comma-separated list of post IDs to exclude.', 'endmark'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: Advanced -->
                    <div id="endmark-tab-advanced" class="endmark-tab-content">
                        
                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Display Options', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Control visibility on mobile devices and AMP pages.', 'endmark'); ?></p>
                            
                            <div class="endmark-toggle-group">
                                <div class="endmark-toggle-item">
                                    <div class="endmark-toggle">
                                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SITE); ?>[hide_on_mobile]" id="endmark_hide_on_mobile" value="1" <?php checked($settings['hide_on_mobile']); ?>>
                                    </div>
                                    <div class="toggle-content">
                                        <div class="toggle-label"><?php esc_html_e('Hide on mobile devices', 'endmark'); ?></div>
                                        <div class="toggle-desc"><?php esc_html_e('Endmark will not display on phones and tablets.', 'endmark'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="endmark-toggle-item">
                                    <div class="endmark-toggle">
                                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SITE); ?>[disable_on_amp]" id="endmark_disable_on_amp" value="1" <?php checked($settings['disable_on_amp']); ?>>
                                    </div>
                                    <div class="toggle-content">
                                        <div class="toggle-label"><?php esc_html_e('Disable on AMP pages', 'endmark'); ?></div>
                                        <div class="toggle-desc"><?php esc_html_e('Skip endmark insertion on Accelerated Mobile Pages.', 'endmark'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="endmark-divider"></div>

                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('CSS Styling', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Fine-tune the spacing and styling of your endmark.', 'endmark'); ?></p>
                            
                            <div class="endmark-inline-fields">
                                <div class="endmark-inline-field">
                                    <label for="endmark_margin_top"><?php esc_html_e('Margin Top', 'endmark'); ?></label>
                                    <input type="text" name="<?php echo esc_attr(self::OPTION_SITE); ?>[margin_top]" id="endmark_margin_top" value="<?php echo esc_attr($settings['margin_top']); ?>">
                                </div>
                                <div class="endmark-inline-field">
                                    <label for="endmark_margin_left"><?php esc_html_e('Margin Left', 'endmark'); ?></label>
                                    <input type="text" name="<?php echo esc_attr(self::OPTION_SITE); ?>[margin_left]" id="endmark_margin_left" value="<?php echo esc_attr($settings['margin_left']); ?>">
                                </div>
                            </div>

                            <div class="endmark-toggle-item" style="margin-top: 20px;">
                                <div class="endmark-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SITE); ?>[use_css_variables]" id="endmark_use_css_variables" value="1" <?php checked($settings['use_css_variables']); ?>>
                                </div>
                                <div class="toggle-content">
                                    <div class="toggle-label"><?php esc_html_e('Use CSS variables', 'endmark'); ?></div>
                                    <div class="toggle-desc"><?php esc_html_e('Enable CSS custom properties for advanced theme customization.', 'endmark'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="endmark-divider"></div>

                        <div class="endmark-section">
                            <h3 class="endmark-section-title"><?php esc_html_e('Schema.org Structured Data', 'endmark'); ?></h3>
                            <p class="endmark-section-desc"><?php esc_html_e('Add structured data markup for search engines.', 'endmark'); ?></p>
                            
                            <div class="endmark-toggle-item">
                                <div class="endmark-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SITE); ?>[schema_enabled]" id="endmark_schema_enabled" value="1" <?php checked($settings['schema_enabled']); ?>>
                                </div>
                                <div class="toggle-content">
                                    <div class="toggle-label"><?php esc_html_e('Output JSON-LD schema markup', 'endmark'); ?></div>
                                    <div class="toggle-desc"><?php esc_html_e('Add semantic markup identifying the endmark element.', 'endmark'); ?></div>
                                </div>
                            </div>
                            
                            <div class="endmark-schema-mode-field <?php echo empty($settings['schema_enabled']) ? 'disabled' : ''; ?>">
                                <div class="endmark-field">
                                    <label for="endmark_schema_mode"><?php esc_html_e('Schema Type', 'endmark'); ?></label>
                                    <select name="<?php echo esc_attr(self::OPTION_SITE); ?>[schema_mode]" id="endmark_schema_mode">
                                        <option value="WebPageElement" <?php selected($settings['schema_mode'], 'WebPageElement'); ?>>WebPageElement</option>
                                        <option value="CreativeWork" <?php selected($settings['schema_mode'], 'CreativeWork'); ?>>CreativeWork</option>
                                    </select>
                                    <p class="hint"><?php esc_html_e('Only WebPageElement and CreativeWork are allowed to prevent conflicts with other plugins.', 'endmark'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="endmark-footer">
                    <span class="endmark-footer-hint"><?php esc_html_e('Changes apply immediately to all pages.', 'endmark'); ?></span>
                    <?php submit_button(__('Save Changes', 'endmark'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
Endmark_Plugin::get_instance();
