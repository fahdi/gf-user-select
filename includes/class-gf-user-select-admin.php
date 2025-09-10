<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality for Gravity Forms User Select
 */
class GF_User_Select_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'User Select Settings',
            'User Select',
            'manage_options',
            'gf-user-select',
            array($this, 'settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Default roles
        register_setting('gf_user_select_settings', 'gf_user_select_roles', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_roles')
        ));

        // Default display format
        register_setting('gf_user_select_settings', 'gf_user_select_display_format', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        // Search threshold
        register_setting('gf_user_select_settings', 'gf_user_select_search_threshold', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        ));

        // Cache duration
        register_setting('gf_user_select_settings', 'gf_user_select_cache_duration', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint'
        ));

        // Excluded users
        register_setting('gf_user_select_settings', 'gf_user_select_excluded_users', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }

    /**
     * Sanitize roles setting
     */
    public function sanitize_roles($roles) {
        if (!is_array($roles)) {
            return array('administrator', 'editor', 'author');
        }

        $valid_roles = array_keys(wp_roles()->get_names());
        return array_intersect($roles, $valid_roles);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_gf-user-select') {
            return;
        }

        wp_enqueue_script('gf-user-select-admin', GF_USER_SELECT_URL . 'assets/admin.js', array('jquery'), GF_USER_SELECT_VERSION, true);
        wp_enqueue_style('gf-user-select-admin', GF_USER_SELECT_URL . 'assets/admin.css', array(), GF_USER_SELECT_VERSION);
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }

        // Get current settings
        $roles = get_option('gf_user_select_roles', array('administrator', 'editor', 'author'));
        $display_format = get_option('gf_user_select_display_format', 'display_name');
        $search_threshold = get_option('gf_user_select_search_threshold', 100);
        $cache_duration = get_option('gf_user_select_cache_duration', 3600);
        $excluded_users = get_option('gf_user_select_excluded_users', '');

        // Get available roles
        $available_roles = wp_roles()->get_names();

        ?>
        <div class="wrap">
            <h1>User Select Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gf_user_select_settings', 'gf_user_select_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gf_user_select_roles">Default User Roles</label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($available_roles as $role_key => $role_name): ?>
                                    <label>
                                        <input type="checkbox" name="gf_user_select_roles[]" value="<?php echo esc_attr($role_key); ?>" 
                                               <?php checked(in_array($role_key, $roles)); ?>>
                                        <?php echo esc_html($role_name); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Select which user roles to include by default in new User Select fields.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gf_user_select_display_format">Default Display Format</label>
                        </th>
                        <td>
                            <select name="gf_user_select_display_format" id="gf_user_select_display_format">
                                <option value="display_name" <?php selected($display_format, 'display_name'); ?>>Display Name</option>
                                <option value="first_last" <?php selected($display_format, 'first_last'); ?>>First + Last Name</option>
                                <option value="username" <?php selected($display_format, 'username'); ?>>Username</option>
                                <option value="custom" <?php selected($display_format, 'custom'); ?>>Custom Template</option>
                            </select>
                            <p class="description">How user names appear in dropdowns by default.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gf_user_select_search_threshold">Search Threshold</label>
                        </th>
                        <td>
                            <input type="number" name="gf_user_select_search_threshold" id="gf_user_select_search_threshold" 
                                   value="<?php echo esc_attr($search_threshold); ?>" min="10" max="1000">
                            <p class="description">Minimum number of users to enable search functionality (10-1000).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gf_user_select_cache_duration">Cache Duration (seconds)</label>
                        </th>
                        <td>
                            <input type="number" name="gf_user_select_cache_duration" id="gf_user_select_cache_duration" 
                                   value="<?php echo esc_attr($cache_duration); ?>" min="300" max="86400">
                            <p class="description">How long to cache user lists (300-86400 seconds).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="gf_user_select_excluded_users">Excluded User IDs</label>
                        </th>
                        <td>
                            <input type="text" name="gf_user_select_excluded_users" id="gf_user_select_excluded_users" 
                                   value="<?php echo esc_attr($excluded_users); ?>" class="regular-text">
                            <p class="description">Comma-separated list of user IDs to exclude from all User Select fields.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <hr>
            
            <h2>Cache Management</h2>
            <p>Clear cached user lists if you're experiencing issues with user data not updating.</p>
            <form method="post" action="">
                <?php wp_nonce_field('gf_user_select_clear_cache', 'gf_user_select_cache_nonce'); ?>
                <input type="hidden" name="gf_user_select_action" value="clear_cache">
                <?php submit_button('Clear Cache', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['gf_user_select_nonce'], 'gf_user_select_settings')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions.');
        }

        // Save settings
        update_option('gf_user_select_roles', $_POST['gf_user_select_roles'] ?? array());
        update_option('gf_user_select_display_format', sanitize_text_field($_POST['gf_user_select_display_format'] ?? 'display_name'));
        update_option('gf_user_select_search_threshold', absint($_POST['gf_user_select_search_threshold'] ?? 100));
        update_option('gf_user_select_cache_duration', absint($_POST['gf_user_select_cache_duration'] ?? 3600));
        update_option('gf_user_select_excluded_users', sanitize_text_field($_POST['gf_user_select_excluded_users'] ?? ''));

        add_settings_error('gf_user_select_settings', 'settings_saved', 'Settings saved successfully.', 'updated');
    }

    /**
     * Handle cache clear
     */
    private function handle_cache_clear() {
        if (!wp_verify_nonce($_POST['gf_user_select_cache_nonce'], 'gf_user_select_clear_cache')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions.');
        }

        // Clear caches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gf_user_select_cache_%'");

        add_settings_error('gf_user_select_settings', 'cache_cleared', 'Cache cleared successfully.', 'updated');
    }
}
