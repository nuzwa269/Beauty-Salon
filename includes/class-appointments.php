<?php
/**
 * Appointments Management Class
 */

class BSM_Appointments {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'salon_appointments';
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_update_appointment_status', array($this, 'update_appointment_status'));
        add_action('wp_ajax_bsm_cancel_appointment', array($this, 'cancel_appointment'));
        add_action('wp_ajax_bsm_reschedule_appointment', array($this, 'reschedule_appointment'));
    }
    
    /**
     * Create a new appointment
     */
    public function create_appointment($data) {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['client_id']) || empty($data['service_id']) || empty($data['staff_id']) || empty($data['start_datetime'])) {
            return false;
        }
        
        // Check for conflicts
        if (!$this->is_slot_available($data['staff_id'], $data['start_datetime'], $data['end_datetime'])) {
            return false;
        }
        
        // Calculate end datetime
        $end_datetime = $this->calculate_end_datetime($data['service_id'], $data['staff_id'], $data['start_datetime']);
        
        // Generate tracking token
        $tracking_token = wp_generate_password(32, false);
        
        // Prepare data
        $appointment_data = array(
            'client_id' => intval($data['client_id']),
            'branch_id' => intval($data['branch_id']) ?: null,
            'service_id' => intval($data['service_id']),
            'staff_id' => intval($data['staff_id']),
            'start_datetime' => sanitize_text_field($data['start_datetime']),
            'end_datetime' => $end_datetime,
            'status' => sanitize_text_field($data['status'] ?? 'pending'),
            'price' => floatval($data['price'] ?? 0),
            'discount' => floatval($data['discount'] ?? 0),
            'tax' => floatval($data['tax'] ?? 0),
            'total_amount' => floatval($data['total_amount'] ?? 0),
            'payment_status' => sanitize_text_field($data['payment_status'] ?? 'unpaid'),
            'source' => sanitize_text_field($data['source'] ?? 'admin'),
            'notes' => sanitize_textarea_field($data['notes']),
            'tracking_token' => $tracking_token
        );
        
        // Insert appointment
        $result = $wpdb->insert($this->table_name, $appointment_data);
        
        if ($result === false) {
            return false;
        }
        
        $appointment_id = $wpdb->insert_id;
        
        // Log the appointment creation
        $this->log_action($appointment_id, 'created', null, $appointment_data['status']);
        
        // Send confirmation email
        $this->send_confirmation_email($appointment_id);
        
        return $appointment_id;
    }
    
    /**
     * Update appointment status
     */
    public function update_appointment_status($appointment_id, $new_status, $notes = '') {
        global $wpdb;
        
        if (!current_user_can('manage_salon_appointments')) {
            return false;
        }
        
        // Get current status
        $current = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$this->table_name} WHERE id = %d", $appointment_id));
        
        if (!$current) {
            return false;
        }
        
        $old_status = $current->status;
        
        // Update appointment
        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the status change
            $this->log_action($appointment_id, 'status_changed', $old_status, $new_status, $notes);
            
            // Trigger status change actions
            do_action('bsm_appointment_status_changed', $appointment_id, $old_status, $new_status);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Cancel appointment
     */
    public function cancel_appointment($appointment_id, $reason = '') {
        return $this->update_appointment_status($appointment_id, 'cancelled', $reason);
    }
    
    /**
     * Reschedule appointment
     */
    public function reschedule_appointment($appointment_id, $new_start_datetime, $new_end_datetime = null) {
        global $wpdb;
        
        if (!current_user_can('manage_salon_appointments')) {
            return false;
        }
        
        if (!$new_end_datetime) {
            $appointment = $this->get_appointment($appointment_id);
            $new_end_datetime = $this->calculate_end_datetime($appointment->service_id, $appointment->staff_id, $new_start_datetime);
        }
        
        // Check if new slot is available
        if (!$this->is_slot_available($appointment->staff_id, $new_start_datetime, $new_end_datetime, $appointment_id)) {
            return false;
        }
        
        $old_start = $appointment->start_datetime;
        $old_end = $appointment->end_datetime;
        
        // Update appointment
        $result = $wpdb->update(
            $this->table_name,
            array(
                'start_datetime' => $new_start_datetime,
                'end_datetime' => $new_end_datetime,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $appointment_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log the reschedule
            $this->log_action($appointment_id, 'rescheduled', null, null, "From: $old_start to $new_start_datetime");
            
            // Send reschedule notification
            $this->send_reschedule_notification($appointment_id, $old_start, $new_start_datetime);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get appointment by ID
     */
    public function get_appointment($appointment_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT a.*, 
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone,
                   s.name as service_name, s.base_price,
                   st.name as staff_name
            FROM {$this->table_name} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE a.id = %d
        ", $appointment_id));
    }
    
    /**
     * Get appointments with filters
     */
    public function get_appointments($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date' => current_time('Y-m-d'),
            'status' => '',
            'staff_id' => '',
            'branch_id' => '',
            'client_id' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'start_datetime',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['date'])) {
            $where_conditions[] = 'DATE(a.start_datetime) = %s';
            $where_values[] = $args['date'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'a.status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['staff_id'])) {
            $where_conditions[] = 'a.staff_id = %d';
            $where_values[] = intval($args['staff_id']);
        }
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        if (!empty($args['client_id'])) {
            $where_conditions[] = 'a.client_id = %d';
            $where_values[] = intval($args['client_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT a.*, 
                   c.first_name, c.last_name, c.email as client_email, c.phone as client_phone,
                   s.name as service_name, s.base_price,
                   st.name as staff_name
            FROM {$this->table_name} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE $where_clause
            ORDER BY a.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Check if slot is available
     */
    public function is_slot_available($staff_id, $start_datetime, $end_datetime, $exclude_appointment_id = null) {
        global $wpdb;
        
        $where_conditions = array('staff_id = %d');
        $where_values = array(intval($staff_id));
        
        // Check for overlapping appointments
        $where_conditions[] = '(
            (start_datetime <= %s AND end_datetime > %s) OR
            (start_datetime < %s AND end_datetime >= %s) OR
            (start_datetime >= %s AND start_datetime < %s)
        )';
        
        $where_values[] = $start_datetime;
        $where_values[] = $start_datetime;
        $where_values[] = $end_datetime;
        $where_values[] = $end_datetime;
        $where_values[] = $start_datetime;
        $where_values[] = $end_datetime;
        
        // Exclude current appointment if rescheduling
        if ($exclude_appointment_id) {
            $where_conditions[] = 'id != %d';
            $where_values[] = intval($exclude_appointment_id);
        }
        
        // Only check against non-cancelled appointments
        $where_conditions[] = "status != 'cancelled'";
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT COUNT(*) FROM {$this->table_name}
            WHERE $where_clause
        ", $where_values);
        
        $conflicts = $wpdb->get_var($query);
        
        return ($conflicts == 0);
    }
    
    /**
     * Calculate end datetime based on service duration
     */
    public function calculate_end_datetime($service_id, $staff_id, $start_datetime) {
        global $wpdb;
        
        // Check if staff has custom duration for this service
        $custom_duration = $wpdb->get_var($wpdb->prepare("
            SELECT custom_duration 
            FROM {$wpdb->prefix}salon_staff_services 
            WHERE staff_id = %d AND service_id = %d AND is_active = 1
        ", $staff_id, $service_id));
        
        if ($custom_duration) {
            $duration = intval($custom_duration);
        } else {
            // Use service base duration
            $base_duration = $wpdb->get_var($wpdb->prepare("
                SELECT base_duration 
                FROM {$wpdb->prefix}salon_services 
                WHERE id = %d AND is_active = 1
            ", $service_id));
            
            $duration = intval($base_duration) ?: 60; // Default 60 minutes
        }
        
        return date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));
    }
    
    /**
     * Get available time slots for a date
     */
    public function get_available_slots($service_id, $staff_id, $date) {
        global $wpdb;
        
        // Get working hours
        $weekday = date('w', strtotime($date));
        $working_hours = $wpdb->get_row($wpdb->prepare("
            SELECT start_time, end_time, breaks_json
            FROM {$wpdb->prefix}salon_staff_schedule
            WHERE staff_id = %d AND weekday = %d
        ", $staff_id, $weekday));
        
        if (!$working_hours) {
            return array();
        }
        
        $slots = array();
        $business_start = strtotime($date . ' ' . $working_hours->start_time);
        $business_end = strtotime($date . ' ' . $working_hours->end_time);
        
        // Get existing appointments for the day
        $existing_appointments = $wpdb->get_results($wpdb->prepare("
            SELECT start_datetime, end_datetime
            FROM {$this->table_name}
            WHERE staff_id = %d 
            AND DATE(start_datetime) = %s
            AND status != 'cancelled'
        ", $staff_id, $date));
        
        // Generate slots (30-minute intervals)
        $slot_duration = 30; // 30 minutes
        for ($time = $business_start; $time < $business_end; $time += ($slot_duration * 60)) {
            $slot_start = date('H:i:s', $time);
            $slot_end_datetime = date('Y-m-d H:i:s', $time + ($slot_duration * 60));
            
            // Check if slot conflicts with existing appointments
            $is_available = true;
            foreach ($existing_appointments as $appointment) {
                if (($time >= strtotime($appointment->start_datetime) && $time < strtotime($appointment->end_datetime)) ||
                    ($time + ($slot_duration * 60) > strtotime($appointment->start_datetime) && $time + ($slot_duration * 60) <= strtotime($appointment->end_datetime)) ||
                    ($time <= strtotime($appointment->start_datetime) && $time + ($slot_duration * 60) >= strtotime($appointment->end_datetime))) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available) {
                $slots[] = array(
                    'time' => $slot_start,
                    'display' => date('g:i A', $time)
                );
            }
        }
        
        return $slots;
    }
    
    /**
     * Log appointment action
     */
    private function log_action($appointment_id, $action, $old_status = null, $new_status = null, $notes = '') {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'salon_appointment_logs',
            array(
                'appointment_id' => $appointment_id,
                'action' => $action,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'notes' => $notes,
                'performed_by' => get_current_user_id(),
                'performed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Send confirmation email
     */
    private function send_confirmation_email($appointment_id) {
        // Implementation would send email notification
        do_action('bsm_send_appointment_confirmation', $appointment_id);
    }
    
    /**
     * Send reschedule notification
     */
    private function send_reschedule_notification($appointment_id, $old_start, $new_start) {
        // Implementation would send reschedule notification
        do_action('bsm_send_reschedule_notification', $appointment_id, $old_start, $new_start);
    }
    
    /**
     * Handle AJAX status update
     */
    public function handle_ajax_update_status() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_appointments')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $result = $this->update_appointment_status($appointment_id, $new_status, $notes);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update appointment status', BSM_TEXT_DOMAIN));
        }
    }
}
