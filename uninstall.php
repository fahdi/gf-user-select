<?php
/**
 * Uninstall script for Gravity Forms User Select
 * 
 * This file is executed when the plugin is deleted through the WordPress admin.
 * It removes all plugin data, options, and caches.
 * 
 * @package GF_User_Select
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - only allow uninstall from WordPress admin
if (!current_user_can('delete_plugins')) {
    exit;
}

// Log uninstall event
error_log('GF User Select: Plugin uninstalled by user ID: ' . get_current_user_id());

// Remove plugin options
$options_to_remove = array(
    'gf_user_select_roles',
    'gf_user_select_display_format',
    'gf_user_select_search_threshold',
    'gf_user_select_cache_duration',
    'gf_user_select_settings',
    'gf_user_select_version',
    'gf_user_select_activation_time',
    'gf_user_select_last_updated'
);

foreach ($options_to_remove as $option) {
    delete_option($option);
}

// Remove plugin transients (caches)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gf_user_select_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gf_user_select_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gf_user_select_cache_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gf_user_select_rate_limit_%'");

// Remove plugin user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'gf_user_select_%'");

// Remove plugin post meta (if any)
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'gf_user_select_%'");

// Remove plugin comments meta (if any)
$wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE 'gf_user_select_%'");

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Remove plugin-specific database tables (if any were created)
// Note: This plugin doesn't create custom tables, but this is here for future reference
$tables_to_remove = array(
    // Add any custom tables here if they were created
);

foreach ($tables_to_remove as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

// Remove plugin files (this is handled by WordPress automatically)
// But we can log what files were removed
$plugin_files = array(
    'gf-user-select.php',
    'includes/class-gf-field-user-select.php',
    'includes/class-gf-user-select-admin.php',
    'includes/class-gf-user-select-ajax.php',
    'assets/admin.js',
    'assets/admin.css',
    'languages/',
    'README.md',
    'readme.txt',
    'SECURITY.md',
    'security-audit.md',
    'uninstall.php'
);

error_log('GF User Select: Removed files: ' . implode(', ', $plugin_files));

// Remove plugin directory (this is handled by WordPress automatically)
$plugin_dir = WP_PLUGIN_DIR . '/gf-user-select';
if (is_dir($plugin_dir)) {
    error_log('GF User Select: Plugin directory will be removed: ' . $plugin_dir);
}

// Clear any scheduled events
wp_clear_scheduled_hook('gf_user_select_cleanup_cache');
wp_clear_scheduled_hook('gf_user_select_cleanup_rate_limits');

// Remove any custom capabilities (if any were added)
// Note: This plugin doesn't add custom capabilities, but this is here for future reference
$capabilities_to_remove = array(
    // Add any custom capabilities here if they were added
);

foreach ($capabilities_to_remove as $capability) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
        $wpdb->get_blog_prefix() . 'capabilities',
        $capability
    ));
}

// Remove any custom roles (if any were added)
// Note: This plugin doesn't add custom roles, but this is here for future reference
$roles_to_remove = array(
    // Add any custom roles here if they were added
);

foreach ($roles_to_remove as $role) {
    remove_role($role);
}

// Log completion
error_log('GF User Select: Uninstall completed successfully');

// Optional: Send notification to admin
$admin_email = get_option('admin_email');
if ($admin_email) {
    $subject = 'Plugin Uninstalled: Gravity Forms User Select';
    $message = 'The Gravity Forms User Select plugin has been uninstalled from your WordPress site.';
    $message .= "\n\nSite: " . get_site_url();
    $message .= "\nTime: " . current_time('mysql');
    $message .= "\nUser: " . wp_get_current_user()->display_name;
    
    wp_mail($admin_email, $subject, $message);
}

// Final cleanup
unset($options_to_remove, $plugin_files, $tables_to_remove, $capabilities_to_remove, $roles_to_remove);
