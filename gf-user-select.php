<?php
/**
 * Plugin Name: Gravity Forms User Select
 * Plugin URI: https://github.com/your-username/gf-user-select
 * Description: Adds a "User Select" field type to Gravity Forms, allowing you to create dropdowns populated with WordPress users.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
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
define('GF_USER_SELECT_VERSION', '1.0.0');
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
}

// Initialize the plugin
GF_User_Select::get_instance();
