<?php
/**
 * Reports Management Class
 */

class BSM_Reports {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_get_revenue_report', array($this, 'ajax_get_revenue_report'));
        add_action('wp_ajax_bsm_get_appointment_report', array($this, 'ajax_get_appointment_report'));
        add_action('wp_ajax_bsm_get_staff_report', array($this, 'ajax_get_staff_report'));
        add_action('wp_ajax_bsm_get_client_report', array($this, 'ajax_get_client_report'));
        add_action('wp_ajax_bsm_export_report', array($this, 'ajax_export_report'));
    }
    
    /**
     * Get revenue report
     */
    public function get_revenue_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'start_date' => date('Y-m-01'), // First day of current month
            'end_date' => date('Y-m-t'),    // Last day of current month
            'branch_id' => '',
            'group_by' => 'day' // day, week, month, service, staff, method
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        switch ($args['group_by']) {
            case 'service':
                return $this->get_revenue_by_service($args);
            case 'staff':
                return $this->get_revenue_by_staff($args);
            case 'method':
                return $this->get_revenue_by_payment_method($args);
            case 'month':
                return $this->get_monthly_revenue($args);
            case 'week':
                return $this->get_weekly_revenue($args);
            default:
                return $this->get_daily_revenue($args);
        }
    }
    
    /**
     * Get appointment report
     */
    public function get_appointment_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'branch_id' => '',
            'staff_id' => '',
            'service_id' => '',
            'status' => '',
            'group_by' => 'day'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        $where_conditions = array('DATE(a.start_datetime) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        if (!empty($args['staff_id'])) {
            $where_conditions[] = 'a.staff_id = %d';
            $where_values[] = intval($args['staff_id']);
        }
        
        if (!empty($args['service_id'])) {
            $where_conditions[] = 'a.service_id = %d';
            $where_values[] = intval($args['service_id']);
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'a.status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        switch ($args['group_by']) {
            case 'service':
                return $this->get_appointments_by_service($where_clause, $where_values);
            case 'staff':
                return $this->get_appointments_by_staff($where_clause, $where_values);
            case 'status':
                return $this->get_appointments_by_status($where_clause, $where_values);
            default:
                return $this->get_daily_appointments($where_clause, $where_values);
        }
    }
    
    /**
     * Get staff performance report
     */
    public function get_staff_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'branch_id' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        $staff_table = $wpdb->prefix . 'salon_staff';
        
        $where_conditions = array('a.status = "completed"', 'DATE(a.start_datetime) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                st.id,
                st.name,
                st.role,
                st.commission_type,
                st.commission_value,
                COUNT(a.id) as total_appointments,
                SUM(TIMESTAMPDIFF(MINUTE, a.start_datetime, a.end_datetime)) as total_minutes_scheduled,
                COALESCE(SUM(t.amount), 0) as total_revenue,
                COALESCE(AVG(t.amount), 0) as average_revenue_per_appointment,
                -- Calculate commission
                CASE 
                    WHEN st.commission_type = 'percentage' THEN (COALESCE(SUM(t.amount), 0) * st.commission_value / 100)
                    WHEN st.commission_type = 'fixed' THEN (COUNT(a.id) * st.commission_value)
                    ELSE 0
                END as estimated_commission
            FROM {$staff_table} st
            LEFT JOIN {$appointments_table} a ON st.id = a.staff_id 
            AND ($where_clause)
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE st.is_active = 1
            GROUP BY st.id, st.name, st.role, st.commission_type, st.commission_value
            ORDER BY total_revenue DESC
        ", $where_values));
    }
    
    /**
     * Get client report
     */
    public function get_client_report($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'branch_id' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        $clients_table = $wpdb->prefix . 'salon_clients';
        
        $where_conditions = array('a.status = "completed"', 'DATE(a.start_datetime) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                COUNT(a.id) as visit_count,
                COALESCE(SUM(t.amount), 0) as total_spent,
                COALESCE(AVG(t.amount), 0) as average_visit_value,
                MIN(a.start_datetime) as first_visit,
                MAX(a.start_datetime) as last_visit
            FROM {$clients_table} c
            INNER JOIN {$appointments_table} a ON c.id = a.client_id 
            AND ($where_clause)
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone
            HAVING visit_count > 0
            ORDER BY total_spent DESC
        ", $where_values));
    }
    
    /**
     * Get revenue by service
     */
    private function get_revenue_by_service($args) {
        global $wpdb;
        
        $where_conditions = array('a.status = "completed"', 'DATE(a.start_datetime) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.id,
                s.name as service_name,
                s.base_price,
                COUNT(a.id) as appointment_count,
                COALESCE(SUM(t.amount), 0) as total_revenue,
                COALESCE(AVG(t.amount), 0) as average_price,
                MIN(t.amount) as min_price,
                MAX(t.amount) as max_price
            FROM {$wpdb->prefix}salon_services s
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON s.id = a.service_id AND ($where_clause)
            LEFT JOIN {$wpdb->prefix}salon_transactions t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE s.is_active = 1
            GROUP BY s.id, s.name, s.base_price
            HAVING appointment_count > 0
            ORDER BY total_revenue DESC
        ", $where_values));
    }
    
    /**
     * Get revenue by staff
     */
    private function get_revenue_by_staff($args) {
        global $wpdb;
        
        $where_conditions = array('a.status = "completed"', 'DATE(a.start_datetime) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                st.id,
                st.name as staff_name,
                st.role,
                COUNT(a.id) as appointment_count,
                COALESCE(SUM(t.amount), 0) as total_revenue,
                COALESCE(AVG(t.amount), 0) as average_revenue_per_appointment
            FROM {$wpdb->prefix}salon_staff st
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON st.id = a.staff_id AND ($where_clause)
            LEFT JOIN {$wpdb->prefix}salon_transactions t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE st.is_active = 1
            GROUP BY st.id, st.name, st.role
            ORDER BY total_revenue DESC
        ", $where_values));
    }
    
    /**
     * Get revenue by payment method
     */
    private function get_revenue_by_payment_method($args) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"', 't.paid_at BETWEEN %s AND %s');
        $where_values = array($args['start_date'] . ' 00:00:00', $args['end_date'] . ' 23:59:59');
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.method,
                t.gateway,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_revenue,
                COALESCE(AVG(t.amount), 0) as average_transaction
            FROM {$wpdb->prefix}salon_transactions t
            WHERE $where_clause
            GROUP BY t.method, t.gateway
            ORDER BY total_revenue DESC
        ", $where_values));
    }
    
    /**
     * Get daily revenue
     */
    private function get_daily_revenue($args) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"', 'DATE(t.paid_at) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(t.paid_at) as date,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as daily_revenue,
                COALESCE(AVG(t.amount), 0) as average_transaction
            FROM {$wpdb->prefix}salon_transactions t
            WHERE $where_clause
            GROUP BY DATE(t.paid_at)
            ORDER BY date ASC
        ", $where_values));
    }
    
    /**
     * Get weekly revenue
     */
    private function get_weekly_revenue($args) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"', 'DATE(t.paid_at) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_values);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                YEARWEEK(t.paid_at, 1) as week,
                MIN(DATE(t.paid_at)) as week_start,
                MAX(DATE(t.paid_at)) as week_end,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as weekly_revenue
            FROM {$wpdb->prefix}salon_transactions t
            WHERE $where_clause
            GROUP BY YEARWEEK(t.paid_at, 1)
            ORDER BY week ASC
        ", $where_values));
    }
    
    /**
     * Get monthly revenue
     */
    private function get_monthly_revenue($args) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"', 'DATE(t.paid_at) BETWEEN %s AND %s');
        $where_values = array($args['start_date'], $args['end_date']);
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_values);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(t.paid_at, '%%Y-%%m') as month,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as monthly_revenue,
                COALESCE(AVG(t.amount), 0) as average_transaction
            FROM {$wpdb->prefix}salon_transactions t
            WHERE $where_clause
            GROUP BY DATE_FORMAT(t.paid_at, '%%Y-%%m')
            ORDER BY month ASC
        ", $where_values));
    }
    
    /**
     * Export report data
     */
    public function export_report($report_type, $args = array(), $format = 'csv') {
        switch ($report_type) {
            case 'revenue':
                $data = $this->get_revenue_report($args);
                break;
            case 'appointments':
                $data = $this->get_appointment_report($args);
                break;
            case 'staff':
                $data = $this->get_staff_report($args);
                break;
            case 'clients':
                $data = $this->get_client_report($args);
                break;
            default:
                return false;
        }
        
        if (empty($data)) {
            return false;
        }
        
        $filename = 'salon_' . $report_type . '_' . date('Y-m-d_H-i-s');
        
        if ($format === 'csv') {
            return $this->export_to_csv($data, $filename);
        } elseif ($format === 'json') {
            return $this->export_to_json($data, $filename);
        }
        
        return false;
    }
    
    /**
     * Export data to CSV
     */
    private function export_to_csv($data, $filename) {
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/' . $filename . '.csv';
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            return false;
        }
        
        // Add headers
        if (!empty($data)) {
            fputcsv($file, array_keys((array) $data[0]));
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($file, (array) $row);
            }
        }
        
        fclose($file);
        
        return $filepath;
    }
    
    /**
     * Export data to JSON
     */
    private function export_to_json($data, $filename) {
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/' . $filename . '.json';
        
        $json_data = json_encode($data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($filepath, $json_data) === false) {
            return false;
        }
        
        return $filepath;
    }
    
    /**
     * AJAX: Get revenue report
     */
    public function ajax_get_revenue_report() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_reports')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $args = array(
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'branch_id' => intval($_POST['branch_id']) ?: null,
            'group_by' => sanitize_text_field($_POST['group_by'] ?? 'day')
        );
        
        $report = $this->get_revenue_report($args);
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Get appointment report
     */
    public function ajax_get_appointment_report() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_reports')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $args = array(
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'branch_id' => intval($_POST['branch_id']) ?: null,
            'staff_id' => intval($_POST['staff_id']) ?: null,
            'service_id' => intval($_POST['service_id']) ?: null,
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'group_by' => sanitize_text_field($_POST['group_by'] ?? 'day')
        );
        
        $report = $this->get_appointment_report($args);
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Get staff report
     */
    public function ajax_get_staff_report() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_reports')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $args = array(
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'branch_id' => intval($_POST['branch_id']) ?: null
        );
        
        $report = $this->get_staff_report($args);
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Get client report
     */
    public function ajax_get_client_report() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_reports')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $args = array(
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'branch_id' => intval($_POST['branch_id']) ?: null
        );
        
        $report = $this->get_client_report($args);
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Export report
     */
    public function ajax_export_report() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_reports')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $args = array(
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'branch_id' => intval($_POST['branch_id']) ?: null
        );
        
        $filepath = $this->export_report($report_type, $args, $format);
        
        if ($filepath) {
            $upload_url = wp_upload_dir()['baseurl'];
            $file_url = $upload_url . '/' . basename($filepath);
            wp_send_json_success(array('download_url' => $file_url));
        } else {
            wp_send_json_error(__('Export failed', BSM_TEXT_DOMAIN));
        }
    }
}
