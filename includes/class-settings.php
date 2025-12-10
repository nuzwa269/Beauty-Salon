<?php
/**
 * Settings Management Class
 */

class BSM_Settings {
    
    private $settings_table;
    
    public function __construct() {
        global $wpdb;
        $this->settings_table = $wpdb->prefix . 'salon_settings';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_bsm_get_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_bsm_reset_settings', array($this, 'ajax_reset_settings'));
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT option_value 
            FROM {$this->settings_table} 
            WHERE option_key = %s
        ", $key));
        
        if ($result !== null) {
            return json_decode($result, true);
        }
        
        return $default;
    }
    
    /**
     * Update setting value
     */
    public function update_setting($key, $value) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id 
            FROM {$this->settings_table} 
            WHERE option_key = %s
        ", $key));
        
        $option_value = json_encode($value);
        
        if ($existing) {
            // Update existing setting
            $result = $wpdb->update(
                $this->settings_table,
                array('option_value' => $option_value),
                array('option_key' => $key),
                array('%s'),
                array('%s')
            );
        } else {
            // Insert new setting
            $result = $wpdb->insert(
                $this->settings_table,
                array(
                    'option_key' => $key,
                    'option_value' => $option_value,
                    'autoload' => 1
                ),
                array('%s', '%s', '%d')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Delete setting
     */
    public function delete_setting($key) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->settings_table,
            array('option_key' => $key),
            array('%s')
        ) !== false;
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        global $wpdb;
        
        $settings = array();
        $results = $wpdb->get_results("SELECT option_key, option_value FROM {$this->settings_table}");
        
        foreach ($results as $result) {
            $settings[$result->option_key] = json_decode($result->option_value, true);
        }
        
        return $settings;
    }
    
    /**
     * Get default settings
     */
    public function get_default_settings() {
        return array(
            'general' => array(
                'default_branch' => '',
                'timezone' => get_option('timezone_string') ?: 'UTC',
                'currency' => get_option('currency') ?: 'USD',
                'date_format' => get_option('date_format') ?: 'Y-m-d',
                'time_format' => get_option('time_format') ?: 'H:i:s',
                'min_notice_time' => 60, // minutes
                'max_advance_booking' => 60, // days
            ),
            'branding' => array(
                'salon_name' => get_bloginfo('name'),
                'salon_logo' => '',
                'primary_color' => '#0073aa',
                'secondary_color' => '#f0f0f0',
                'custom_css' => '',
            ),
            'booking' => array(
                'allow_guest_booking' => true,
                'max_appointments_per_client_per_day' => 1,
                'require_email_confirmation' => false,
                'require_phone_verification' => false,
                'allow_online_rescheduling' => true,
                'allow_online_cancellation' => true,
                'cancellation_window' => 24, // hours
                'no_show_fee' => 0,
                'booking_window' => 60, // days
            ),
            'notifications' => array(
                'email_from_name' => get_bloginfo('name'),
                'email_from_address' => get_option('admin_email'),
                'sms_provider' => '',
                'whatsapp_provider' => '',
                'reminder_times' => array(24, 2), // hours before
                'followup_time' => 24, // hours after completion
                'birthday_reminders' => true,
            ),
            'payments' => array(
                'enable_online_payments' => true,
                'default_payment_method' => 'card',
                'accepted_cards' => array('visa', 'mastercard', 'american_express'),
                'tax_rate' => 0,
                'tax_inclusive' => false,
                'currency_symbol' => '$',
                'deposit_required' => false,
                'deposit_percentage' => 0,
            ),
            'staff' => array(
                'default_working_hours_start' => '09:00',
                'default_working_hours_end' => '18:00',
                'default_break_duration' => 60, // minutes
                'enable_commission_tracking' => false,
                'commission_calculation_method' => 'percentage',
            ),
        );
    }
    
    /**
     * Get settings by category
     */
    public function get_settings_by_category($category) {
        $all_settings = $this->get_all_settings();
        $defaults = $this->get_default_settings();
        
        $category_settings = isset($defaults[$category]) ? $defaults[$category] : array();
        
        // Override with saved settings
        foreach ($category_settings as $key => $default_value) {
            $setting_key = $category . '_' . $key;
            if (isset($all_settings[$setting_key])) {
                $category_settings[$key] = $all_settings[$setting_key];
            }
        }
        
        return $category_settings;
    }
    
    /**
     * Initialize default settings
     */
    public function initialize_default_settings() {
        $defaults = $this->get_default_settings();
        
        foreach ($defaults as $category => $settings) {
            foreach ($settings as $key => $value) {
                $setting_key = $category . '_' . $key;
                if (!$this->get_setting($setting_key)) {
                    $this->update_setting($setting_key, $value);
                }
            }
        }
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($settings) {
        $errors = array();
        
        // Validate currency
        if (isset($settings['general_currency']) && !preg_match('/^[A-Z]{3}$/', $settings['general_currency'])) {
            $errors[] = __('Currency must be a 3-letter code (e.g., USD, EUR)', BSM_TEXT_DOMAIN);
        }
        
        // Validate colors
        $color_keys = array_filter(array_keys($settings), function($key) {
            return strpos($key, '_color') !== false;
        });
        
        foreach ($color_keys as $key) {
            if (!preg_match('/^#[a-f0-9]{6}$/i', $settings[$key])) {
                $errors[] = sprintf(__('Invalid color format for %s', BSM_TEXT_DOMAIN), str_replace('_', ' ', ucfirst(explode('_', $key)[1])));
            }
        }
        
        // Validate numeric values
        $numeric_keys = array('general_min_notice_time', 'booking_cancellation_window', 'payments_tax_rate');
        foreach ($numeric_keys as $key) {
            if (isset($settings[$key]) && (!is_numeric($settings[$key]) || $settings[$key] < 0)) {
                $errors[] = sprintf(__('%s must be a positive number', BSM_TEXT_DOMAIN), str_replace('_', ' ', ucfirst(explode('_', $key)[1])));
            }
        }
        
        // Validate email
        if (isset($settings['notifications_email_from_address']) && !is_email($settings['notifications_email_from_address'])) {
            $errors[] = __('Invalid email address for from address', BSM_TEXT_DOMAIN);
        }
        
        return $errors;
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        $settings = $this->get_all_settings();
        
        return array(
            'version' => BSM_VERSION,
            'export_date' => current_time('mysql'),
            'settings' => $settings,
        );
    }
    
    /**
     * Import settings
     */
    public function import_settings($import_data) {
        if (!is_array($import_data) || !isset($import_data['settings'])) {
            return false;
        }
        
        $imported = 0;
        $settings = $import_data['settings'];
        
        foreach ($settings as $key => $value) {
            if ($this->update_setting($key, $value)) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    /**
     * Reset all settings to defaults
     */
    public function reset_to_defaults() {
        global $wpdb;
        
        // Delete all existing settings
        $wpdb->query("DELETE FROM {$this->settings_table}");
        
        // Initialize default settings
        $this->initialize_default_settings();
        
        return true;
    }
    
    /**
     * Get setting metadata for admin UI
     */
    public function get_settings_metadata() {
        return array(
            'general' => array(
                'title' => __('General Settings', BSM_TEXT_DOMAIN),
                'fields' => array(
                    'default_branch' => array(
                        'type' => 'select',
                        'label' => __('Default Branch', BSM_TEXT_DOMAIN),
                        'options' => $this->get_branch_options(),
                    ),
                    'timezone' => array(
                        'type' => 'select',
                        'label' => __('Timezone', BSM_TEXT_DOMAIN),
                        'options' => $this->get_timezone_options(),
                    ),
                    'currency' => array(
                        'type' => 'text',
                        'label' => __('Currency Code', BSM_TEXT_DOMAIN),
                        'description' => __('3-letter currency code (e.g., USD, EUR)', BSM_TEXT_DOMAIN),
                    ),
                    'date_format' => array(
                        'type' => 'select',
                        'label' => __('Date Format', BSM_TEXT_DOMAIN),
                        'options' => array(
                            'Y-m-d' => 'YYYY-MM-DD',
                            'm/d/Y' => 'MM/DD/YYYY',
                            'd/m/Y' => 'DD/MM/YYYY',
                        ),
                    ),
                    'time_format' => array(
                        'type' => 'select',
                        'label' => __('Time Format', BSM_TEXT_DOMAIN),
                        'options' => array(
                            'H:i:s' => '24-hour (HH:MM:SS)',
                            'H:i' => '24-hour (HH:MM)',
                            'g:i:s A' => '12-hour (H:MM:SS AM/PM)',
                            'g:i A' => '12-hour (H:MM AM/PM)',
                        ),
                    ),
                    'min_notice_time' => array(
                        'type' => 'number',
                        'label' => __('Minimum Notice Time (minutes)', BSM_TEXT_DOMAIN),
                        'min' => 0,
                    ),
                ),
            ),
            'branding' => array(
                'title' => __('Branding', BSM_TEXT_DOMAIN),
                'fields' => array(
                    'salon_name' => array(
                        'type' => 'text',
                        'label' => __('Salon Name', BSM_TEXT_DOMAIN),
                    ),
                    'salon_logo' => array(
                        'type' => 'image',
                        'label' => __('Salon Logo', BSM_TEXT_DOMAIN),
                    ),
                    'primary_color' => array(
                        'type' => 'color',
                        'label' => __('Primary Color', BSM_TEXT_DOMAIN),
                    ),
                    'secondary_color' => array(
                        'type' => 'color',
                        'label' => __('Secondary Color', BSM_TEXT_DOMAIN),
                    ),
                    'custom_css' => array(
                        'type' => 'textarea',
                        'label' => __('Custom CSS', BSM_TEXT_DOMAIN),
                        'rows' => 5,
                    ),
                ),
            ),
            'booking' => array(
                'title' => __('Booking Settings', BSM_TEXT_DOMAIN),
                'fields' => array(
                    'allow_guest_booking' => array(
                        'type' => 'checkbox',
                        'label' => __('Allow Guest Booking', BSM_TEXT_DOMAIN),
                    ),
                    'max_appointments_per_client_per_day' => array(
                        'type' => 'number',
                        'label' => __('Max Appointments Per Client Per Day', BSM_TEXT_DOMAIN),
                        'min' => 1,
                    ),
                    'cancellation_window' => array(
                        'type' => 'number',
                        'label' => __('Cancellation Window (hours)', BSM_TEXT_DOMAIN),
                        'min' => 0,
                    ),
                    'allow_online_cancellation' => array(
                        'type' => 'checkbox',
                        'label' => __('Allow Online Cancellation', BSM_TEXT_DOMAIN),
                    ),
                ),
            ),
            'notifications' => array(
                'title' => __('Notifications', BSM_TEXT_DOMAIN),
                'fields' => array(
                    'email_from_name' => array(
                        'type' => 'text',
                        'label' => __('Email From Name', BSM_TEXT_DOMAIN),
                    ),
                    'email_from_address' => array(
                        'type' => 'email',
                        'label' => __('Email From Address', BSM_TEXT_DOMAIN),
                    ),
                    'reminder_times' => array(
                        'type' => 'multiselect',
                        'label' => __('Reminder Times (hours before)', BSM_TEXT_DOMAIN),
                        'options' => array(
                            '48' => '48 hours',
                            '24' => '24 hours',
                            '12' => '12 hours',
                            '6' => '6 hours',
                            '2' => '2 hours',
                            '1' => '1 hour',
                        ),
                    ),
                ),
            ),
            'payments' => array(
                'title' => __('Payment Settings', BSM_TEXT_DOMAIN),
                'fields' => array(
                    'enable_online_payments' => array(
                        'type' => 'checkbox',
                        'label' => __('Enable Online Payments', BSM_TEXT_DOMAIN),
                    ),
                    'tax_rate' => array(
                        'type' => 'number',
                        'label' => __('Tax Rate (%)', BSM_TEXT_DOMAIN),
                        'min' => 0,
                        'max' => 100,
                        'step' => 0.01,
                    ),
                    'currency_symbol' => array(
                        'type' => 'text',
                        'label' => __('Currency Symbol', BSM_TEXT_DOMAIN),
                        'maxlength' => 3,
                    ),
                ),
            ),
        );
    }
    
    /**
     * Get branch options
     */
    private function get_branch_options() {
        global $wpdb;
        
        $branches = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}salon_branches WHERE is_active = 1");
        $options = array('' => __('Select a branch', BSM_TEXT_DOMAIN));
        
        foreach ($branches as $branch) {
            $options[$branch->id] = $branch->name;
        }
        
        return $options;
    }
    
    /**
     * Get timezone options
     */
    private function get_timezone_options() {
        $timezones = array(
            'Pacific/Honolulu' => 'Pacific/Honolulu',
            'America/Anchorage' => 'America/Anchorage',
            'America/Los_Angeles' => 'America/Los_Angeles',
            'America/Denver' => 'America/Denver',
            'America/Chicago' => 'America/Chicago',
            'America/New_York' => 'America/New_York',
            'America/Sao_Paulo' => 'America/Sao_Paulo',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'Europe/Berlin' => 'Europe/Berlin',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Asia/Shanghai' => 'Asia/Shanghai',
            'Asia/Kolkata' => 'Asia/Kolkata',
            'Australia/Sydney' => 'Australia/Sydney',
        );
        
        return array('' => __('Select timezone', BSM_TEXT_DOMAIN)) + $timezones;
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_settings')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $settings = $_POST['settings'];
        $errors = $this->validate_settings($settings);
        
        if (!empty($errors)) {
            wp_send_json_error($errors);
        }
        
        $saved = 0;
        foreach ($settings as $key => $value) {
            if ($this->update_setting($key, $value)) {
                $saved++;
            }
        }
        
        wp_send_json_success(array('saved_count' => $saved));
    }
    
    /**
     * AJAX: Get settings
     */
    public function ajax_get_settings() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_settings')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $category = sanitize_text_field($_POST['category'] ?? 'general');
        $settings = $this->get_settings_by_category($category);
        
        wp_send_json_success($settings);
    }
    
    /**
     * AJAX: Reset settings
     */
    public function ajax_reset_settings() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_settings')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        if (!isset($_POST['confirm_reset']) || !$_POST['confirm_reset']) {
            wp_send_json_error(__('Reset confirmation required', BSM_TEXT_DOMAIN));
        }
        
        $this->reset_to_defaults();
        wp_send_json_success();
    }
}
