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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gf_user_select_search')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('read')) {
            wp_send_json_error('Insufficient permissions');
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
}
