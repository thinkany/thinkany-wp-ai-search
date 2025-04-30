<?php
/**
 * Uninstall ThinkAny WP AI Search
 *
 * @package ThinkAny_WP_AI_Search
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options
delete_option('thinkany_wp_ai_search_settings');

// Clean up transients
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thinkany_wp_ai_search_%'");
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_thinkany_wp_ai_search_%'");
