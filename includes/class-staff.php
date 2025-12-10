<?php
/**
 * Staff Management Class
 */

class BSM_Staff {
    
    private $table_name;
    private $staff_services_table;
    private $staff_schedule_table;
    private $staff_time_off_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'salon_staff';
        $this->staff_services_table = $wpdb->prefix . 'salon_staff_services';
        $this->staff_schedule_table = $wpdb->prefix . 'salon_staff_schedule';
        $this->staff_time_off_table = $wpdb->prefix . 'salon_staff_time_off';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_get_staff_schedule', array($this, 'ajax_get_staff_schedule'));
        add_action('wp_ajax_bsm_add_staff_time_off', array($this, 'ajax_add_staff_time_off'));
        add_action('wp_ajax_bsm_update_staff_schedule', array($this, 'ajax_update_staff_schedule'));
    }
    
    /**
     * Create a new staff member
     */
    public function create_staff($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['name'])) {
            return false;
        }
        
        $staff_data = array(
            'wp_user_id' => intval($data['wp_user_id']) ?: null,
            'branch_id' => intval($data['branch_id']) ?: null,
            'name' => sanitize_text_field($data['name']),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'role' => sanitize_text_field($data['role'] ?? 'stylist'),
            'color_code' => sanitize_hex_color($data['color_code'] ?? '#3498db'),
            'commission_type' => sanitize_text_field($data['commission_type'] ?? 'percentage'),
            'commission_value' => floatval($data['commission_value'] ?? 0),
            'is_active' => 1
        );
        
        $result = $wpdb->insert($this->table_name, $staff_data);
        
        if ($result === false) {
            return false;
        }
        
        $staff_id = $wpdb->insert_id;
        
        // Set up default schedule (Monday to Friday, 9 AM to 6 PM)
        $this->create_default_schedule($staff_id);
        
        return $staff_id;
    }
    
    /**
     * Update staff member
     */
    public function update_staff($staff_id, $data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $staff_data = array(
            'wp_user_id' => intval($data['wp_user_id']) ?: null,
            'branch_id' => intval($data['branch_id']) ?: null,
            'name' => sanitize_text_field($data['name']),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'role' => sanitize_text_field($data['role'] ?? 'stylist'),
            'color_code' => sanitize_hex_color($data['color_code'] ?? '#3498db'),
            'commission_type' => sanitize_text_field($data['commission_type'] ?? 'percentage'),
            'commission_value' => floatval($data['commission_value'] ?? 0),
        );
        
        if (isset($data['is_active'])) {
            $staff_data['is_active'] = intval($data['is_active']);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $staff_data,
            array('id' => $staff_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all staff members
     */
    public function get_staff($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'is_active' => 1,
            'branch_id' => '',
            'role' => '',
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if ($args['is_active'] !== '') {
            $where_conditions[] = 'is_active = %d';
            $where_values[] = intval($args['is_active']);
        }
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        if (!empty($args['role'])) {
            $where_conditions[] = 'role = %s';
            $where_values[] = $args['role'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT s.*, b.name as branch_name
            FROM {$this->table_name} s
            LEFT JOIN {$wpdb->prefix}salon_branches b ON s.branch_id = b.id
            WHERE $where_clause
            ORDER BY s.{$args['orderby']} {$args['order']}
        ", $where_values);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get staff member by ID
     */
    public function get_staff_member($staff_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT s.*, b.name as branch_name
            FROM {$this->table_name} s
            LEFT JOIN {$wpdb->prefix}salon_branches b ON s.branch_id = b.id
            WHERE s.id = %d
        ", $staff_id));
    }
    
    /**
     * Assign service to staff member
     */
    public function assign_service($staff_id, $service_id, $custom_duration = null, $custom_price = null) {
        global $wpdb;
        
        $data = array(
            'staff_id' => intval($staff_id),
            'service_id' => intval($service_id),
            'custom_duration' => $custom_duration ? intval($custom_duration) : null,
            'custom_price' => $custom_price ? floatval($custom_price) : null,
            'is_active' => 1
        );
        
        // Check if assignment already exists
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM {$this->staff_services_table}
            WHERE staff_id = %d AND service_id = %d
        ", $staff_id, $service_id));
        
        if ($existing) {
            // Update existing assignment
            return $wpdb->update(
                $this->staff_services_table,
                $data,
                array('staff_id' => $staff_id, 'service_id' => $service_id),
                array('%d', '%d', '%d', '%f', '%d'),
                array('%d', '%d')
            ) !== false;
        } else {
            // Create new assignment
            return $wpdb->insert($this->staff_services_table, $data) !== false;
        }
    }
    
    /**
     * Remove service from staff member
     */
    public function remove_service($staff_id, $service_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->staff_services_table,
            array('staff_id' => $staff_id, 'service_id' => $service_id),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Get services assigned to staff member
     */
    public function get_staff_services($staff_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT s.*, ss.custom_duration, ss.custom_price
            FROM {$wpdb->prefix}salon_services s
            INNER JOIN {$this->staff_services_table} ss ON s.id = ss.service_id
            WHERE ss.staff_id = %d AND ss.is_active = 1 AND s.is_active = 1
            ORDER BY s.name
        ", $staff_id));
    }
    
    /**
     * Update staff schedule
     */
    public function update_schedule($staff_id, $schedule_data) {
        global $wpdb;
        
        // Delete existing schedule
        $wpdb->delete($this->staff_schedule_table, array('staff_id' => $staff_id), array('%d'));
        
        // Insert new schedule
        foreach ($schedule_data as $weekday => $data) {
            if (!isset($data['enabled']) || !$data['enabled']) {
                continue;
            }
            
            $wpdb->insert(
                $this->staff_schedule_table,
                array(
                    'staff_id' => $staff_id,
                    'weekday' => intval($weekday),
                    'start_time' => sanitize_text_field($data['start_time']),
                    'end_time' => sanitize_text_field($data['end_time']),
                    'breaks_json' => wp_json_encode($data['breaks'] ?? array())
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );
        }
        
        return true;
    }
    
    /**
     * Get staff schedule for a specific day
     */
    public function get_staff_schedule($staff_id, $weekday) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$this->staff_schedule_table}
            WHERE staff_id = %d AND weekday = %d
        ", $staff_id, $weekday));
    }
    
    /**
     * Get staff schedule for all days
     */
    public function get_full_schedule($staff_id) {
        global $wpdb;
        
        $schedule = array();
        
        for ($day = 0; $day < 7; $day++) {
            $schedule[$day] = $this->get_staff_schedule($staff_id, $day);
        }
        
        return $schedule;
    }
    
    /**
     * Add staff time off
     */
    public function add_time_off($staff_id, $start_datetime, $end_datetime, $reason = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $this->staff_time_off_table,
            array(
                'staff_id' => intval($staff_id),
                'start_datetime' => sanitize_text_field($start_datetime),
                'end_datetime' => sanitize_text_field($end_datetime),
                'reason' => sanitize_text_field($reason),
                'approved_by' => get_current_user_id()
            ),
            array('%d', '%s', '%s', '%s', '%d')
        ) !== false;
    }
    
    /**
     * Get staff time off
     */
    public function get_time_off($staff_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $where_conditions = array('staff_id = %d');
        $where_values = array(intval($staff_id));
        
        if ($start_date) {
            $where_conditions[] = 'start_datetime >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 'end_datetime <= %s';
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT toff.*, 
                   staff.name as staff_name,
                   approver.display_name as approved_by_name
            FROM {$this->staff_time_off_table} toff
            LEFT JOIN {$this->table_name} staff ON toff.staff_id = staff.id
            LEFT JOIN {$wpdb->users} approver ON toff.approved_by = approver.ID
            WHERE $where_clause
            ORDER BY toff.start_datetime DESC
        ", $where_values);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Check if staff is available at specific time
     */
    public function is_staff_available($staff_id, $start_datetime, $end_datetime) {
        global $wpdb;
        
        // Check time off
        $time_off = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$this->staff_time_off_table}
            WHERE staff_id = %d
            AND (
                (start_datetime <= %s AND end_datetime > %s) OR
                (start_datetime < %s AND end_datetime >= %s) OR
                (start_datetime >= %s AND start_datetime < %s)
            )
        ", $staff_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime));
        
        if ($time_off > 0) {
            return false;
        }
        
        // Check appointments
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $appointments = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$appointments_table}
            WHERE staff_id = %d
            AND status != 'cancelled'
            AND (
                (start_datetime <= %s AND end_datetime > %s) OR
                (start_datetime < %s AND end_datetime >= %s) OR
                (start_datetime >= %s AND start_datetime < %s)
            )
        ", $staff_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime));
        
        return ($appointments == 0);
    }
    
    /**
     * Get staff statistics
     */
    public function get_staff_stats($staff_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $where_conditions = array('staff_id = %d');
        $where_values = array(intval($staff_id));
        
        if ($start_date) {
            $where_conditions[] = 'DATE(start_datetime) >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 'DATE(start_datetime) <= %s';
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                COALESCE(SUM(t.amount), 0) as total_revenue
            FROM {$appointments_table} a
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE $where_clause
        ", $where_values));
        
        return $stats;
    }
    
    /**
     * Create default schedule for new staff
     */
    private function create_default_schedule($staff_id) {
        $default_schedule = array(
            1 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '18:00'), // Monday
            2 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '18:00'), // Tuesday
            3 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '18:00'), // Wednesday
            4 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '18:00'), // Thursday
            5 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '18:00'), // Friday
            6 => array('enabled' => true, 'start_time' => '09:00', 'end_time' => '17:00'), // Saturday
            0 => array('enabled' => false, 'start_time' => '09:00', 'end_time' => '17:00')  // Sunday
        );
        
        $this->update_schedule($staff_id, $default_schedule);
    }
    
    /**
     * AJAX: Get staff schedule
     */
    public function ajax_get_staff_schedule() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_staff')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $staff_id = intval($_POST['staff_id']);
        $schedule = $this->get_full_schedule($staff_id);
        
        wp_send_json_success($schedule);
    }
    
    /**
     * AJAX: Add staff time off
     */
    public function ajax_add_staff_time_off() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_staff')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $staff_id = intval($_POST['staff_id']);
        $start_datetime = sanitize_text_field($_POST['start_datetime']);
        $end_datetime = sanitize_text_field($_POST['end_datetime']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        $result = $this->add_time_off($staff_id, $start_datetime, $end_datetime, $reason);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to add time off', BSM_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Update staff schedule
     */
    public function ajax_update_staff_schedule() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_staff')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $staff_id = intval($_POST['staff_id']);
        $schedule_data = json_decode(stripslashes($_POST['schedule']), true);
        
        if (!is_array($schedule_data)) {
            wp_send_json_error(__('Invalid schedule data', BSM_TEXT_DOMAIN));
        }
        
        $result = $this->update_schedule($staff_id, $schedule_data);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update schedule', BSM_TEXT_DOMAIN));
        }
    }
}
