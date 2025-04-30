<?php
/**
 * Plugin Name: thinkany WP AI Search
 * Plugin URI: https://github.com/thinkany/thinkany-wp-ai-search/
 * Description: Enhances WordPress search using OpenAI to provide more relevant search results.
 * Version: 1.0.0
 * Author: ThinkAny LLC
 * Author URI: https://thinkany.co
 * Text Domain: thinkany-wp-ai-search
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
register_activation_hook(__FILE__, 'thinkany_wp_ai_search_activate');
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
    
    // Create database table for tracking API calls
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_api_usage';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        call_date date NOT NULL,
        call_count int NOT NULL DEFAULT 0,
        prompt_tokens int NOT NULL DEFAULT 0,
        completion_tokens int NOT NULL DEFAULT 0,
        total_tokens int NOT NULL DEFAULT 0,
        estimated_cost decimal(10,6) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY call_date (call_date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'thinkany_wp_ai_search_deactivate');
function thinkany_wp_ai_search_deactivate() {
    // Clean up transients
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thinkany_wp_ai_search_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_thinkany_wp_ai_search_%'");
    
    // Remove plugin settings
    delete_option('thinkany_wp_ai_search_settings');
    
    // Clean up database entries
    $table_name = $wpdb->prefix . 'thinkany_api_usage';
    $wpdb->query("TRUNCATE TABLE $table_name");
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'thinkany_wp_ai_search_uninstall');
function thinkany_wp_ai_search_uninstall() {
    // Remove all plugin options
    delete_option('thinkany_wp_ai_search_settings');
    
    // Drop the API usage tracking table
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_api_usage';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Track a successful API call by incrementing the count for the current date
 * and calculating the cost based on token usage
 * 
 * @param array $usage Token usage data from OpenAI API response
 */
function thinkany_wp_ai_search_track_api_call($usage = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_api_usage';
    $today = current_time('Y-m-d');
    
    // Extract token counts from usage data
    $prompt_tokens = isset($usage['prompt_tokens']) ? intval($usage['prompt_tokens']) : 0;
    $completion_tokens = isset($usage['completion_tokens']) ? intval($usage['completion_tokens']) : 0;
    $total_tokens = isset($usage['total_tokens']) ? intval($usage['total_tokens']) : ($prompt_tokens + $completion_tokens);
    
    // Calculate cost based on GPT-3.5 Turbo pricing
    // $0.0005 per 1K prompt tokens, $0.0015 per 1K completion tokens
    $prompt_cost = ($prompt_tokens / 1000) * 0.0005;
    $completion_cost = ($completion_tokens / 1000) * 0.0015;
    $total_cost = $prompt_cost + $completion_cost;
    
    // Check if we already have an entry for today
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, prompt_tokens, completion_tokens, total_tokens, estimated_cost FROM $table_name WHERE call_date = %s",
        $today
    ));
    
    if ($existing) {
        // Increment the existing count and add token counts and cost
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET 
            call_count = call_count + 1,
            prompt_tokens = prompt_tokens + %d,
            completion_tokens = completion_tokens + %d,
            total_tokens = total_tokens + %d,
            estimated_cost = estimated_cost + %f
            WHERE call_date = %s",
            $prompt_tokens,
            $completion_tokens,
            $total_tokens,
            $total_cost,
            $today
        ));
    } else {
        // Insert a new row for today
        $wpdb->insert(
            $table_name,
            array(
                'call_date' => $today,
                'call_count' => 1,
                'prompt_tokens' => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'total_tokens' => $total_tokens,
                'estimated_cost' => $total_cost
            ),
            array('%s', '%d', '%d', '%d', '%d', '%f')
        );
    }
}