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
     * Hard upper bound on results per page.
     */
    const MAX_PER_PAGE = 50;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_gf_user_select_search', array($this, 'handle_user_search'));

        /**
         * Allow the search endpoint for logged-out visitors.
         *
         * Off by default: the endpoint lists site users, which is PII.
         * Enable only when the field is used on a public form and the
         * display format does not expose e-mail addresses.
         */
        if (apply_filters('gf_user_select_allow_anonymous_search', false)) {
            add_action('wp_ajax_nopriv_gf_user_select_search', array($this, 'handle_user_search'));
        }
    }

    /**
     * Handle user search AJAX request
     */
    public function handle_user_search() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gf_user_select_search')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'gf-user-select')
            ), 403);
        }

        if (!$this->check_rate_limit()) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please try again later.', 'gf-user-select')
            ), 429);
        }

        // The form/field decide which roles are listed and how users are
        // labelled. Those settings are configured by the form admin and are
        // never accepted from the request.
        $form_id  = absint($_POST['form_id'] ?? 0);
        $field_id = absint($_POST['field_id'] ?? 0);
        $settings = $this->get_field_settings($form_id, $field_id);

        if (null === $settings) {
            wp_send_json_error(array(
                'message' => __('Invalid field.', 'gf-user-select')
            ), 400);
        }

        $search_term = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $page        = max(1, absint($_POST['page'] ?? 1));
        $per_page    = min(max(1, absint($_POST['per_page'] ?? 20)), self::MAX_PER_PAGE);

        $args = array(
            'orderby'     => 'display_name',
            'order'       => 'ASC',
            'fields'      => array('ID', 'display_name', 'user_login'),
            'number'      => $per_page,
            'offset'      => ($page - 1) * $per_page,
            'count_total' => true,
        );

        if (!empty($settings['roles'])) {
            $args['role__in'] = $settings['roles'];
        }

        if ($search_term !== '') {
            $args['search'] = '*' . $search_term . '*';
            // Never search e-mail or login: that lets a caller confirm
            // whether a given address/username has an account.
            $args['search_columns'] = array('display_name');
        }

        $excluded = self::get_excluded_user_ids();
        if (!empty($excluded)) {
            $args['exclude'] = $excluded;
        }

        $query = new WP_User_Query($args);
        $total = (int) $query->get_total();

        $results = array();
        foreach ($query->get_results() as $user) {
            $results[] = array(
                'id'   => (int) $user->ID,
                'text' => $this->format_user_display($user, $settings['display_format'], $settings['custom_template']),
            );
        }

        wp_send_json_success(array(
            'results'    => $results,
            'pagination' => array(
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ),
        ));
    }

    /**
     * Resolve role/format settings for a User Select field from the form.
     *
     * @return array|null null when the form/field does not exist or is not a user_select field.
     */
    private function get_field_settings($form_id, $field_id) {
        if (!$form_id || !$field_id || !class_exists('GFAPI')) {
            return null;
        }

        $field = GFAPI::get_field($form_id, $field_id);
        if (!$field || is_wp_error($field) || $field->type !== 'user_select') {
            return null;
        }

        $roles = !empty($field->userRoles) ? (array) $field->userRoles : (array) get_option('gf_user_select_roles', array('administrator', 'editor', 'author'));
        $roles = array_values(array_intersect(array_map('sanitize_key', $roles), array_keys(wp_roles()->get_names())));

        $format = !empty($field->displayFormat) ? $field->displayFormat : get_option('gf_user_select_display_format', 'display_name');
        if (!in_array($format, array('display_name', 'first_last', 'username', 'custom'), true)) {
            $format = 'display_name';
        }

        return array(
            'roles'           => $roles,
            'display_format'  => $format,
            'custom_template' => !empty($field->customTemplate) ? (string) $field->customTemplate : '{first_name} {last_name}',
        );
    }

    /**
     * Globally excluded user IDs (Settings > User Select).
     *
     * @return int[]
     */
    public static function get_excluded_user_ids() {
        $raw = (string) get_option('gf_user_select_excluded_users', '');
        if ($raw === '') {
            return array();
        }
        return array_values(array_filter(array_map('absint', explode(',', $raw))));
    }

    /**
     * Format user display text
     */
    private function format_user_display($user, $format, $template) {
        switch ($format) {
            case 'first_last':
                $first = $user->first_name ?: '';
                $last = $user->last_name ?: '';
                return trim($first . ' ' . $last) ?: $user->display_name ?: $user->user_login;

            case 'username':
                return $user->user_login;

            case 'custom':
                return str_replace(
                    array('{first_name}', '{last_name}', '{user_email}', '{display_name}', '{username}'),
                    array($user->first_name ?: '', $user->last_name ?: '', $user->user_email ?: '', $user->display_name ?: '', $user->user_login ?: ''),
                    $template
                );

            case 'display_name':
            default:
                return $user->display_name ?: $user->user_login;
        }
    }

    /**
     * Check rate limiting for AJAX requests (30 per minute per user/IP).
     *
     * REMOTE_ADDR is used as-is; X-Forwarded-For is attacker controlled unless
     * the host rewrites REMOTE_ADDR for trusted proxies.
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $key = 'gf_user_select_rate_limit_' . md5($user_id . '|' . $ip);

        $requests = (int) get_transient($key);
        if ($requests >= 30) {
            return false;
        }

        set_transient($key, $requests + 1, MINUTE_IN_SECONDS);
        return true;
    }
}
