<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gravity Forms User Select Field
 *
 * Field settings are stored as field properties (via GF_Field::__set):
 * userRoles, displayFormat, customTemplate, autoSelectCurrent, allowEmpty,
 * searchable, searchThreshold.
 */
class GF_Field_User_Select extends GF_Field_Select {

    /**
     * Field type identifier
     */
    public $type = 'user_select';

    /**
     * Display formats accepted for the dropdown label.
     */
    const FORMATS = array('display_name', 'first_last', 'username', 'custom');

    /**
     * Hook editor settings UI + frontend assets. Called once from the main plugin.
     */
    public static function register_hooks() {
        add_action('gform_field_standard_settings', array(__CLASS__, 'render_editor_settings'), 10, 2);
        add_action('gform_editor_js', array(__CLASS__, 'editor_js'));
        add_filter('gform_tooltips', array(__CLASS__, 'tooltips'));
        add_action('gform_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'), 10, 2);
    }

    public function get_form_editor_field_title() {
        return esc_attr__('User Select', 'gf-user-select');
    }

    public function get_form_editor_field_description() {
        return esc_attr__('A dropdown populated with WordPress users.', 'gf-user-select');
    }

    public function get_form_editor_field_icon() {
        return 'dashicons-admin-users';
    }

    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        );
    }

    public function get_form_editor_field_settings() {
        return array(
            'conditional_logic_field_setting',
            'prepopulate_field_setting',
            'error_message_setting',
            'label_setting',
            'label_placement_setting',
            'admin_label_setting',
            'size_setting',
            'rules_setting',
            'description_setting',
            'css_class_setting',
            'user_select_setting',
        );
    }

    public function is_conditional_logic_supported() {
        return true;
    }

    /**
     * Maximum number of users rendered inline before the field falls back to search.
     */
    public static function get_max_inline_users() {
        return max(1, (int) apply_filters('gf_user_select_max_inline_users', 500));
    }

    /**
     * Resolved roles for this field (validated against registered roles).
     *
     * @return string[]
     */
    public function get_roles() {
        $roles = !empty($this->userRoles) ? (array) $this->userRoles : (array) get_option('gf_user_select_roles', array('administrator', 'editor', 'author'));
        return array_values(array_intersect(array_map('sanitize_key', $roles), array_keys(wp_roles()->get_names())));
    }

    public function get_display_format() {
        $format = !empty($this->displayFormat) ? $this->displayFormat : get_option('gf_user_select_display_format', 'display_name');
        return in_array($format, self::FORMATS, true) ? $format : 'display_name';
    }

    public function get_custom_template() {
        return !empty($this->customTemplate) ? (string) $this->customTemplate : '{first_name} {last_name}';
    }

    /**
     * Get field input HTML
     */
    public function get_field_input($form, $value = '', $entry = null) {
        $form_id         = absint($form['id']);
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor  = $this->is_form_editor();

        $roles      = $this->get_roles();
        $excluded   = class_exists('GF_User_Select_Ajax') ? GF_User_Select_Ajax::get_excluded_user_ids() : array();
        $base_args  = array('orderby' => 'display_name', 'order' => 'ASC');
        if (!empty($roles)) {
            $base_args['role__in'] = $roles;
        }
        if (!empty($excluded)) {
            $base_args['exclude'] = $excluded;
        }

        // Count without loading rows.
        $count_query = new WP_User_Query(array_merge($base_args, array('number' => 1, 'fields' => 'ID', 'count_total' => true)));
        $user_count  = (int) $count_query->get_total();

        $threshold  = max(1, (int) ($this->searchThreshold ?: get_option('gf_user_select_search_threshold', 100)));
        $max_inline = self::get_max_inline_users();
        $use_search = (!empty($this->searchable) && $user_count >= $threshold) || $user_count > $max_inline;

        if ($this->autoSelectCurrent && !$is_form_editor && !$is_entry_detail && empty($value) && is_user_logged_in()) {
            $value = get_current_user_id();
        }

        if ($use_search && !$is_form_editor) {
            $choices = $this->get_search_initial_choices($value);
        } else {
            $users   = $this->get_users_bounded($base_args, $max_inline);
            $choices = $this->get_user_choices($users);
        }

        return $this->render_select($form_id, $choices, $value, $use_search && !$is_form_editor);
    }

    /**
     * Bounded, cached user fetch.
     */
    private function get_users_bounded($args, $limit) {
        $args['number'] = $limit;
        $args['fields'] = array('ID', 'display_name', 'user_login', 'user_email');

        $ttl       = (int) get_option('gf_user_select_cache_duration', 3600);
        $cache_key = 'gf_user_select_cache_' . md5(wp_json_encode($args) . $this->get_display_format() . $this->get_custom_template());
        $users     = $ttl > 0 ? get_transient($cache_key) : false;

        if (!is_array($users)) {
            $users = get_users($args);
            if ($ttl > 0) {
                set_transient($cache_key, $users, $ttl);
            }
        }

        return $users;
    }

    /**
     * Choices for a search-mode select: placeholder plus the selected user only.
     */
    private function get_search_initial_choices($value) {
        $choices = array();
        if ($this->allowEmpty !== false && $this->allowEmpty !== '0') {
            $choices[] = array('text' => __('Search for a user...', 'gf-user-select'), 'value' => '');
        }
        if (!empty($value)) {
            $user = get_user_by('id', absint($value));
            if ($user) {
                $choices[] = array('text' => $this->format_user_display($user), 'value' => $user->ID);
            }
        }
        return $choices;
    }

    /**
     * Get user choices for dropdown
     */
    public function get_user_choices($users) {
        $choices = array();

        if ($this->allowEmpty !== false && $this->allowEmpty !== '0') {
            $choices[] = array('text' => __('Select a user...', 'gf-user-select'), 'value' => '');
        }

        foreach ($users as $user) {
            $choices[] = array('text' => $this->format_user_display($user), 'value' => $user->ID);
        }

        return $choices;
    }

    /**
     * Format user display text
     */
    public function format_user_display($user) {
        switch ($this->get_display_format()) {
            case 'first_last':
                $first = $user->first_name ?: '';
                $last  = $user->last_name ?: '';
                return trim($first . ' ' . $last) ?: $user->display_name ?: $user->user_login;

            case 'username':
                return $user->user_login;

            case 'custom':
                return str_replace(
                    array('{first_name}', '{last_name}', '{user_email}', '{display_name}', '{username}'),
                    array($user->first_name ?: '', $user->last_name ?: '', $user->user_email ?: '', $user->display_name ?: '', $user->user_login ?: ''),
                    $this->get_custom_template()
                );

            case 'display_name':
            default:
                return $user->display_name ?: $user->user_login;
        }
    }

    private function render_select($form_id, $choices, $value, $searchable) {
        $field_id   = (int) $this->id;
        $is_editor  = $this->is_form_editor();
        $html_id    = $this->is_entry_detail() || $is_editor || $form_id == 0 ? "input_{$field_id}" : "input_{$form_id}_{$field_id}";
        $class      = trim('gfield_select ' . $this->size . ($searchable ? ' gf-user-select-searchable' : ''));
        $attrs      = $searchable ? sprintf(' data-form-id="%d" data-field-id="%d"', $form_id, $field_id) : '';

        return sprintf(
            '<div class="ginput_container ginput_container_select ginput_container_user_select"><select name="input_%d" id="%s" class="%s" %s %s%s>%s</select></div>',
            $field_id,
            esc_attr($html_id),
            esc_attr($class),
            $this->get_tabindex(),
            $is_editor ? 'disabled="disabled"' : $this->get_conditional_logic_event('change'),
            $attrs,
            $this->get_choices_html($choices, $value)
        );
    }

    private function get_choices_html($choices, $value) {
        $html = '';
        foreach ($choices as $choice) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($choice['value']),
                selected((string) $value, (string) $choice['value'], false),
                esc_html($choice['text'])
            );
        }
        return $html;
    }

    /**
     * Only accept IDs of users the field is allowed to list.
     */
    public function validate($value, $form) {
        if (empty($value)) {
            return;
        }
        $user = get_user_by('id', absint($value));
        $ok   = $user && (empty($this->get_roles()) || array_intersect($this->get_roles(), (array) $user->roles));
        if ($ok && class_exists('GF_User_Select_Ajax') && in_array((int) $user->ID, GF_User_Select_Ajax::get_excluded_user_ids(), true)) {
            $ok = false;
        }
        if (!$ok) {
            $this->failed_validation  = true;
            $this->validation_message = __('Please select a valid user.', 'gf-user-select');
        }
    }

    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
        return $this->user_label($value);
    }

    public function get_value_entry_list($value, $entry, $field_id, $columns, $form) {
        return esc_html($this->user_label($value));
    }

    public function get_value_export($entry, $input_id = '', $use_text = false, $is_csv = false) {
        return $this->user_label(rgar($entry, $input_id));
    }

    public function get_value_merge_tag($value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br) {
        if (empty($value)) {
            return $value;
        }
        $user = get_user_by('id', absint($value));
        if (!$user) {
            return $value;
        }
        switch ($modifier) {
            case 'id':
                return $user->ID;
            case 'email':
                return $user->user_email;
            case 'username':
                return $user->user_login;
            default:
                return $this->format_user_display($user);
        }
    }

    private function user_label($value) {
        if (empty($value)) {
            return '';
        }
        $user = get_user_by('id', absint($value));
        return $user ? $this->format_user_display($user) : (string) $value;
    }

    /* ---------------------------------------------------------------- editor */

    public static function render_editor_settings($position, $form_id) {
        if ($position !== 25) {
            return;
        }
        $roles = wp_roles()->get_names();
        ?>
        <li class="user_select_setting field_setting">
            <label class="section_label"><?php esc_html_e('User Roles', 'gf-user-select'); ?> <?php gform_tooltip('form_field_user_select_roles'); ?></label>
            <select id="field_user_roles" multiple="multiple" size="4" onchange="SetFieldProperty('userRoles', jQuery(this).val());">
                <?php foreach ($roles as $key => $name) : ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="field_display_format" class="section_label" style="margin-top:10px;"><?php esc_html_e('Display Format', 'gf-user-select'); ?></label>
            <select id="field_display_format" onchange="SetFieldProperty('displayFormat', this.value); jQuery('#field_custom_template_wrap').toggle(this.value === 'custom');">
                <option value="display_name"><?php esc_html_e('Display Name', 'gf-user-select'); ?></option>
                <option value="first_last"><?php esc_html_e('First + Last Name', 'gf-user-select'); ?></option>
                <option value="username"><?php esc_html_e('Username', 'gf-user-select'); ?></option>
                <option value="custom"><?php esc_html_e('Custom Template', 'gf-user-select'); ?></option>
            </select>

            <div id="field_custom_template_wrap" style="display:none;">
                <label for="field_custom_template" class="section_label"><?php esc_html_e('Custom Template', 'gf-user-select'); ?> <?php gform_tooltip('form_field_user_select_template'); ?></label>
                <input type="text" id="field_custom_template" class="fieldwidth-3" onkeyup="SetFieldProperty('customTemplate', this.value);" />
            </div>

            <div style="margin-top:10px;">
                <input type="checkbox" id="field_auto_select_current" onclick="SetFieldProperty('autoSelectCurrent', this.checked);" />
                <label for="field_auto_select_current" class="inline"><?php esc_html_e('Auto-select the logged-in user', 'gf-user-select'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_allow_empty" onclick="SetFieldProperty('allowEmpty', this.checked);" />
                <label for="field_allow_empty" class="inline"><?php esc_html_e('Allow empty selection', 'gf-user-select'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_searchable" onclick="SetFieldProperty('searchable', this.checked); jQuery('#field_search_threshold_wrap').toggle(this.checked);" />
                <label for="field_searchable" class="inline"><?php esc_html_e('Enable search for large user lists', 'gf-user-select'); ?></label>
            </div>
            <div id="field_search_threshold_wrap" style="display:none;">
                <label for="field_search_threshold" class="section_label"><?php esc_html_e('Search Threshold', 'gf-user-select'); ?></label>
                <input type="number" id="field_search_threshold" min="1" class="fieldwidth-1" onkeyup="SetFieldProperty('searchThreshold', parseInt(this.value, 10) || 100);" />
            </div>
        </li>
        <?php
    }

    public static function editor_js() {
        ?>
        <script type="text/javascript">
        jQuery(document).on('gform_load_field_settings', function (event, field) {
            if (field.type !== 'user_select') { return; }
            jQuery('#field_user_roles').val(field.userRoles || <?php echo wp_json_encode(array_values((array) get_option('gf_user_select_roles', array('administrator', 'editor', 'author')))); ?>);
            var fmt = field.displayFormat || 'display_name';
            jQuery('#field_display_format').val(fmt);
            jQuery('#field_custom_template_wrap').toggle(fmt === 'custom');
            jQuery('#field_custom_template').val(field.customTemplate || '{first_name} {last_name}');
            jQuery('#field_auto_select_current').prop('checked', !!field.autoSelectCurrent);
            jQuery('#field_allow_empty').prop('checked', field.allowEmpty !== false && field.allowEmpty !== '0');
            jQuery('#field_searchable').prop('checked', !!field.searchable);
            jQuery('#field_search_threshold_wrap').toggle(!!field.searchable);
            jQuery('#field_search_threshold').val(field.searchThreshold || 100);
        });
        </script>
        <?php
    }

    public static function tooltips($tooltips) {
        $tooltips['form_field_user_select_roles']    = '<h6>' . esc_html__('User Roles', 'gf-user-select') . '</h6>' . esc_html__('Only users with one of these roles are listed. Hold Ctrl/Cmd to select several.', 'gf-user-select');
        $tooltips['form_field_user_select_template'] = '<h6>' . esc_html__('Custom Template', 'gf-user-select') . '</h6>' . esc_html__('Placeholders: {first_name}, {last_name}, {display_name}, {username}, {user_email}. Avoid {user_email} on public forms.', 'gf-user-select');
        return $tooltips;
    }

    /* -------------------------------------------------------------- frontend */

    public static function enqueue_frontend_scripts($form, $is_ajax) {
        if (empty($form['fields'])) {
            return;
        }
        foreach ($form['fields'] as $field) {
            if ($field instanceof self) {
                wp_enqueue_script('gf-user-select-frontend', GF_USER_SELECT_URL . 'assets/frontend.js', array('jquery'), GF_USER_SELECT_VERSION, true);
                wp_localize_script('gf-user-select-frontend', 'gf_user_select_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('gf_user_select_search'),
                    'i18n'     => array(
                        'placeholder' => __('Type to search users...', 'gf-user-select'),
                        'no_results'  => __('No users found.', 'gf-user-select'),
                    ),
                ));
                return;
            }
        }
    }
}
