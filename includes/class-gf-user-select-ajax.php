<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX functionality for Gravity Forms User Select
 */
class GF_User_Select_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_gf_user_select_search', array($this, 'handle_user_search'));
        add_action('wp_ajax_nopriv_gf_user_select_search', array($this, 'handle_user_search'));
    }

    /**
     * Handle user search AJAX request
     */
    public function handle_user_search() {
        // Verify nonce using plugin's security method
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gf_user_select_search')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'gf-user-select')
            ));
        }

        // Check permissions
        if (!current_user_can('read')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'gf-user-select')
            ));
        }

        // Rate limiting check
        if (!$this->check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please try again later.', 'gf-user-select')
            ));
        }

        // Get parameters
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $roles = array_map('sanitize_text_field', $_POST['roles'] ?? array());
        $display_format = sanitize_text_field($_POST['display_format'] ?? 'display_name');
        $custom_template = sanitize_text_field($_POST['custom_template'] ?? '');
        $excluded_users = array_map('absint', explode(',', $_POST['excluded_users'] ?? ''));
        $page = absint($_POST['page'] ?? 1);
        $per_page = min(absint($_POST['per_page'] ?? 20), 50);

        // Build user query
        $args = array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'first_name', 'last_name', 'user_email', 'user_login'),
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page
        );

        // Add role filter
        if (!empty($roles)) {
            $args['role__in'] = $roles;
        }

        // Add search term
        if (!empty($search_term)) {
            $args['search'] = '*' . $search_term . '*';
            $args['search_columns'] = array('display_name', 'user_login', 'user_email', 'first_name', 'last_name');
        }

        // Exclude users
        if (!empty($excluded_users)) {
            $args['exclude'] = $excluded_users;
        }

        // Get users
        $users = get_users($args);

        // Format results
        $results = array();
        foreach ($users as $user) {
            $display_text = $this->format_user_display($user, $display_format, $custom_template);
            $results[] = array(
                'id' => $user->ID,
                'text' => $display_text,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'username' => $user->user_login
            );
        }

        // Get total count for pagination
        $total_args = $args;
        unset($total_args['number'], $total_args['offset']);
        $total_users = count(get_users($total_args));

        wp_send_json_success(array(
            'results' => $results,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_users,
                'total_pages' => ceil($total_users / $per_page)
            )
        ));
    }

    /**
     * Format user display text
     */
    private function format_user_display($user, $format, $template) {
        switch ($format) {
            case 'display_name':
                return $user->display_name ?: $user->user_login;
            
            case 'first_last':
                $first = $user->first_name ?: '';
                $last = $user->last_name ?: '';
                return trim($first . ' ' . $last) ?: $user->display_name ?: $user->user_login;
            
            case 'username':
                return $user->user_login;
            
            case 'custom':
                $text = $template;
                $text = str_replace('{first_name}', $user->first_name ?: '', $text);
                $text = str_replace('{last_name}', $user->last_name ?: '', $text);
                $text = str_replace('{user_email}', $user->user_email ?: '', $text);
                $text = str_replace('{display_name}', $user->display_name ?: '', $text);
                $text = str_replace('{username}', $user->user_login ?: '', $text);
                return $text;
            
            default:
                return $user->display_name ?: $user->user_login;
        }
    }

    /**
     * Check rate limiting for AJAX requests
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        $key = 'gf_user_select_rate_limit_' . md5($user_id . $ip);
        
        $requests = get_transient($key);
        if ($requests === false) {
            set_transient($key, 1, 60); // 1 minute window
            return true;
        }
        
        if ($requests >= 30) { // Max 30 requests per minute
            return false;
        }
        
        set_transient($key, $requests + 1, 60);
        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Sanitize search parameters
     */
    private function sanitize_search_params($params) {
        $sanitized = array();
        
        if (isset($params['search'])) {
            $sanitized['search'] = sanitize_text_field($params['search']);
        }
        
        if (isset($params['roles']) && is_array($params['roles'])) {
            $sanitized['roles'] = array_map('sanitize_text_field', $params['roles']);
        }
        
        if (isset($params['display_format'])) {
            $sanitized['display_format'] = sanitize_text_field($params['display_format']);
        }
        
        if (isset($params['template'])) {
            $sanitized['template'] = sanitize_textarea_field($params['template']);
        }
        
        if (isset($params['page'])) {
            $sanitized['page'] = absint($params['page']);
        }
        
        if (isset($params['per_page'])) {
            $sanitized['per_page'] = absint($params['per_page']);
        }
        
        return $sanitized;
    }

    /**
     * Validate user roles
     */
    private function validate_roles($roles) {
        $valid_roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
        $filtered_roles = array();
        
        foreach ($roles as $role) {
            if (in_array($role, $valid_roles)) {
                $filtered_roles[] = $role;
            }
        }
        
        return empty($filtered_roles) ? $valid_roles : $filtered_roles;
    }
}

