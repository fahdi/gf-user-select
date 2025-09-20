<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gravity Forms User Select Field
 */
class GF_Field_User_Select extends GF_Field_Select {

    /**
     * Field type identifier
     */
    public $type = 'user_select';

    /**
     * Field title
     */
    public $type_label = 'User Select';

    /**
     * Field description
     */
    public $description = 'A dropdown field populated with WordPress users';

    /**
     * Field category
     */
    public $category = 'advanced';

    /**
     * Field icon
     */
    public $icon = 'dashicons-admin-users';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Add field settings
        $this->add_field_settings();
    }

    /**
     * Add field-specific settings
     */
    private function add_field_settings() {
        // User roles setting
        $this->add_setting('user_roles', array(
            'label' => 'User Roles',
            'type' => 'multiselect',
            'choices' => $this->get_role_choices(),
            'default_value' => array('administrator', 'editor', 'author'),
            'description' => 'Select which user roles to include in the dropdown'
        ));

        // Display format setting
        $this->add_setting('display_format', array(
            'label' => 'Display Format',
            'type' => 'select',
            'choices' => array(
                'display_name' => 'Display Name',
                'first_last' => 'First + Last Name',
                'username' => 'Username',
                'custom' => 'Custom Template'
            ),
            'default_value' => 'display_name',
            'description' => 'How user names appear in the dropdown'
        ));

        // Custom template setting
        $this->add_setting('custom_template', array(
            'label' => 'Custom Template',
            'type' => 'text',
            'default_value' => '{first_name} {last_name} ({user_email})',
            'description' => 'Use {first_name}, {last_name}, {user_email}, {display_name}, {username}',
            'dependency' => array(
                'field' => 'display_format',
                'values' => array('custom')
            )
        ));

        // Auto-select current user setting
        $this->add_setting('auto_select_current', array(
            'label' => 'Auto-Select Current User',
            'type' => 'checkbox',
            'default_value' => false,
            'description' => 'Automatically select the logged-in user'
        ));

        // Allow empty selection setting
        $this->add_setting('allow_empty', array(
            'label' => 'Allow Empty Selection',
            'type' => 'checkbox',
            'default_value' => true,
            'description' => 'Allow users to select "None" or leave empty'
        ));

        // Searchable setting
        $this->add_setting('searchable', array(
            'label' => 'Enable Search',
            'type' => 'checkbox',
            'default_value' => false,
            'description' => 'Enable search functionality for large user lists'
        ));

        // Search threshold setting
        $this->add_setting('search_threshold', array(
            'label' => 'Search Threshold',
            'type' => 'number',
            'default_value' => 100,
            'description' => 'Minimum number of users to enable search',
            'dependency' => array(
                'field' => 'searchable',
                'values' => array(true)
            )
        ));
    }

    /**
     * Get role choices for settings
     */
    private function get_role_choices() {
        $roles = wp_roles()->get_names();
        $choices = array();
        
        foreach ($roles as $role_key => $role_name) {
            $choices[] = array(
                'value' => $role_key,
                'label' => $role_name
            );
        }
        
        return $choices;
    }

    /**
     * Get field input HTML
     */
    public function get_field_input($form, $value = '', $entry = null) {
        $form_id = absint($form['id']);
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor = $this->is_form_editor();

        // Get field settings
        $user_roles = $this->get_setting('user_roles', array('administrator', 'editor', 'author'));
        $display_format = $this->get_setting('display_format', 'display_name');
        $custom_template = $this->get_setting('custom_template', '{first_name} {last_name} ({user_email})');
        $auto_select_current = $this->get_setting('auto_select_current', false);
        $allow_empty = $this->get_setting('allow_empty', true);
        $searchable = $this->get_setting('searchable', false);
        $search_threshold = $this->get_setting('search_threshold', 100);

        // Get users
        $users = $this->get_users($user_roles);
        $user_count = count($users);

        // Determine if we should use search
        $use_search = $searchable && $user_count >= $search_threshold;

        // Get choices
        $choices = $this->get_user_choices($users, $display_format, $custom_template, $allow_empty);

        // Auto-select current user if enabled
        if ($auto_select_current && !$is_form_editor && !$is_entry_detail && is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            if (in_array($current_user_id, array_column($users, 'ID'))) {
                $value = $current_user_id;
            }
        }

        // Build field HTML
        if ($use_search) {
            return $this->get_searchable_field_html($form_id, $choices, $value);
        } else {
            return $this->get_standard_field_html($form_id, $choices, $value);
        }
    }

    /**
     * Get users based on roles
     */
    private function get_users($roles) {
        $args = array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'first_name', 'last_name', 'user_email', 'user_login')
        );

        if (!empty($roles)) {
            $args['role__in'] = $roles;
        }

        return get_users($args);
    }

    /**
     * Get user choices for dropdown
     */
    public function get_user_choices($users, $display_format, $custom_template, $allow_empty) {
        $choices = array();

        // Add empty option if allowed
        if ($allow_empty) {
            $choices[] = array(
                'text' => 'Select a user...',
                'value' => ''
            );
        }

        // Add user choices
        foreach ($users as $user) {
            $display_text = $this->format_user_display($user, $display_format, $custom_template);
            $choices[] = array(
                'text' => $display_text,
                'value' => $user->ID
            );
        }

        return $choices;
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
     * Get standard field HTML
     */
    private function get_standard_field_html($form_id, $choices, $value) {
        $field_id = $this->id;
        $field_name = "input_{$field_id}";
        $tabindex = $this->get_tabindex();
        $css_class = $this->get_css_class();
        $is_admin = $this->is_form_editor();

        $html = sprintf(
            '<div class="ginput_container ginput_container_user_select">
                <select name="%s" id="input_%s_%d" class="%s" %s %s>
                    %s
                </select>
            </div>',
            $field_name,
            $form_id,
            $field_id,
            $css_class,
            $tabindex,
            $is_admin ? 'disabled="disabled"' : '',
            $this->get_choices_html($choices, $value)
        );

        return $html;
    }

    /**
     * Get searchable field HTML
     */
    private function get_searchable_field_html($form_id, $choices, $value) {
        $field_id = $this->id;
        $field_name = "input_{$field_id}";
        $tabindex = $this->get_tabindex();
        $css_class = $this->get_css_class();
        $is_admin = $this->is_form_editor();

        $html = sprintf(
            '<div class="ginput_container ginput_container_user_select ginput_container_user_select_searchable">
                <select name="%s" id="input_%s_%d" class="%s gf-user-select-searchable" data-field-id="%d" %s %s>
                    %s
                </select>
                <div class="gf-user-select-search-results" style="display: none;"></div>
            </div>',
            $field_name,
            $form_id,
            $field_id,
            $css_class,
            $field_id,
            $tabindex,
            $is_admin ? 'disabled="disabled"' : '',
            $this->get_choices_html($choices, $value)
        );

        return $html;
    }

    /**
     * Get choices HTML
     */
    private function get_choices_html($choices, $value) {
        $html = '';
        
        foreach ($choices as $choice) {
            $selected = selected($value, $choice['value'], false);
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($choice['value']),
                $selected,
                esc_html($choice['text'])
            );
        }
        
        return $html;
    }

    /**
     * Format field value for display
     */
    public function get_value_export($entry, $input_id = '', $use_text = false, $is_csv = false) {
        if (empty($entry[$input_id])) {
            return '';
        }

        $user_id = $entry[$input_id];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return $user_id;
        }

        $display_format = $this->get_setting('display_format', 'display_name');
        $custom_template = $this->get_setting('custom_template', '{first_name} {last_name} ({user_email})');
        
        return $this->format_user_display($user, $display_format, $custom_template);
    }

    /**
     * Get field value for merge tags
     */
    public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
        if (empty($value)) {
            return $value;
        }

        $user_id = $value;
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return $user_id;
        }

        // Handle modifiers
        switch ($modifier) {
            case 'id':
                return $user_id;
            case 'email':
                return $user->user_email;
            case 'username':
                return $user->user_login;
            case 'name':
            default:
                $display_format = $this->get_setting('display_format', 'display_name');
                $custom_template = $this->get_setting('custom_template', '{first_name} {last_name} ({user_email})');
                return $this->format_user_display($user, $display_format, $custom_template);
        }
    }
}

