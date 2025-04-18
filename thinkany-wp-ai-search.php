<?php
/**
 * Plugin Name: thinkany WP AI Search
 * Plugin URI: https://thinkany.com
 * Description: Enhances WordPress search using OpenAI to provide more relevant search results.
 * Version: 1.0.0
 * Author: thinkany llc
 * Author URI: https://thinkany.com
 * Text Domain: thinkany-wp-ai-search
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('THINKANY_WP_AI_SEARCH_VERSION', '1.0.0');
define('THINKANY_WP_AI_SEARCH_PATH', plugin_dir_path(__FILE__));
define('THINKANY_WP_AI_SEARCH_URL', plugin_dir_url(__FILE__));

// Include required files
require_once THINKANY_WP_AI_SEARCH_PATH . 'class-admin-settings.php';
require_once THINKANY_WP_AI_SEARCH_PATH . 'class-ai-search.php';

// Initialize the plugin
function thinkany_wp_ai_search_init() {
    // Initialize admin settings
    ThinkAny_WP_AI_Search_Admin::get_instance();
    
    // Initialize AI search functionality
    ThinkAny_WP_AI_Search::get_instance();
}
add_action('plugins_loaded', 'thinkany_wp_ai_search_init');

// Register activation hook
function thinkany_wp_ai_search_activate() {
    // Add default options if they don't exist
    if (!get_option('thinkany_wp_ai_search_settings')) {
        add_option('thinkany_wp_ai_search_settings', array(
            'api_key' => '',
            'model' => 'gpt-3.5-turbo',
            'cache_duration' => 6, // hours
            'enabled' => false // AI search disabled by default
        ));
    }
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'thinkany_wp_ai_search_deactivate');
function thinkany_wp_ai_search_deactivate() {
    // Clean up transients
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thinkany_wp_ai_search_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_thinkany_wp_ai_search_%'");
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'thinkany_wp_ai_search_uninstall');
function thinkany_wp_ai_search_uninstall() {
    // Remove all plugin options
    delete_option('thinkany_wp_ai_search_settings');
}