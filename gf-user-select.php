<?php
/**
 * Plugin Name: Gravity Forms User Select
 * Plugin URI: https://github.com/fahdi/gf-user-select
 * Description: Adds a "User Select" field type to Gravity Forms, allowing you to create dropdowns populated with WordPress users.
 * Version: 1.0.1
 * Author: Fahad Murtaza aka iSuperCoder
 * Author URI: https://isupercoder.com/contact
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gf-user-select
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GF_USER_SELECT_VERSION', '1.0.1');
define('GF_USER_SELECT_FILE', __FILE__);
define('GF_USER_SELECT_PATH', plugin_dir_path(__FILE__));
define('GF_USER_SELECT_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class GF_User_Select {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
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
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'security_init'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            add_action('admin_notices', array($this, 'gravity_forms_required_notice'));
            return;
        }

        // Load plugin files
        $this->load_files();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Security initialization
     */
    public function security_init() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Sanitize and validate all inputs
        add_action('init', array($this, 'sanitize_inputs'));
        
        // Add capability checks
        add_action('admin_init', array($this, 'check_admin_capabilities'));
    }

    /**
     * Load required files
     */
    private function load_files() {
        require_once GF_USER_SELECT_PATH . 'includes/class-gf-field-user-select.php';
        require_once GF_USER_SELECT_PATH . 'includes/class-gf-user-select-admin.php';
        require_once GF_USER_SELECT_PATH . 'includes/class-gf-user-select-ajax.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register field type
        add_action('gform_loaded', array($this, 'register_field_type'), 5);

        // Initialize admin
        if (is_admin()) {
            new GF_User_Select_Admin();
        }

        // Initialize AJAX
        new GF_User_Select_Ajax();

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Register the field type
     */
    public function register_field_type() {
        if (!class_exists('GFForms')) {
            return;
        }

        GF_Fields::register(new GF_Field_User_Select());
    }

    /**
     * Show notice if Gravity Forms is not active
     */
    public function gravity_forms_required_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Gravity Forms User Select</strong> requires Gravity Forms to be installed and active.
                <a href="<?php echo admin_url('plugin-install.php?s=gravity+forms&tab=search&type=term'); ?>">
                    Install Gravity Forms
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'gf_user_select_roles' => array('administrator', 'editor', 'author'),
            'gf_user_select_display_format' => 'display_name',
            'gf_user_select_search_threshold' => 100,
            'gf_user_select_cache_duration' => 3600,
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear caches
        $this->clear_caches();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear plugin caches
     */
    public function clear_caches() {
        global $wpdb;
        
        // Clear user list caches
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gf_user_select_cache_%'");
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    /**
     * Sanitize inputs
     */
    public function sanitize_inputs() {
        // Sanitize GET parameters
        if (!empty($_GET)) {
            $_GET = array_map('sanitize_text_field', $_GET);
        }
        
        // Sanitize POST parameters (except for form submissions)
        if (!empty($_POST) && !isset($_POST['gform_submit'])) {
            $_POST = array_map('sanitize_text_field', $_POST);
        }
    }

    /**
     * Check admin capabilities
     */
    public function check_admin_capabilities() {
        if (is_admin() && !current_user_can('manage_options')) {
            // Log unauthorized access attempts
            error_log('GF User Select: Unauthorized admin access attempt from user ID: ' . get_current_user_id());
        }
    }

    /**
     * Validate nonce for AJAX requests
     */
    public static function verify_ajax_nonce($nonce, $action) {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'gf-user-select')
            ));
        }
    }

    /**
     * Sanitize user data for output
     */
    public static function sanitize_user_data($user_data) {
        if (!is_array($user_data)) {
            return sanitize_text_field($user_data);
        }
        
        $sanitized = array();
        foreach ($user_data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_user_data($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Escape user data for output
     */
    public static function escape_user_data($user_data, $context = 'display') {
        if (!is_array($user_data)) {
            return esc_html($user_data);
        }
        
        $escaped = array();
        foreach ($user_data as $key => $value) {
            if (is_array($value)) {
                $escaped[$key] = self::escape_user_data($value, $context);
            } else {
                $escaped[$key] = esc_html($value);
            }
        }
        
        return $escaped;
    }
}

// Initialize the plugin
GF_User_Select::get_instance();

