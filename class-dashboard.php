<?php
/**
 * Dashboard Management Class
 */

class BSM_Dashboard {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_bsm_get_today_schedule', array($this, 'ajax_get_today_schedule'));
        add_action('wp_ajax_bsm_get_dashboard_notifications', array($this, 'ajax_get_dashboard_notifications'));
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($date = null, $branch_id = null) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        $where_conditions = array('DATE(a.start_datetime) = %s');
        $where_values = array($date);
        
        if ($branch_id) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get appointment statistics
        $appointment_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_appointments,
                SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                SUM(CASE WHEN a.status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_appointments,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments
            FROM {$appointments_table} a
            WHERE $where_clause
        ", $where_values));
        
        // Get revenue statistics
        $revenue_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COALESCE(SUM(t.amount), 0) as total_revenue,
                COUNT(t.id) as payment_count
            FROM {$appointments_table} a
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE $where_clause
        ", $where_values));
        
        // Get staff on duty
        $staff_on_duty = $this->get_staff_on_duty($date, $branch_id);
        
        // Get peak hours
        $peak_hours = $this->get_peak_hours($date, $branch_id);
        
        return array(
            'appointments' => $appointment_stats,
            'revenue' => $revenue_stats,
            'staff_on_duty' => $staff_on_duty,
            'peak_hours' => $peak_hours
        );
    }
    
    /**
     * Get today's schedule
     */
    public function get_today_schedule($date = null, $branch_id = null, $staff_id = null) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        $where_conditions = array('DATE(a.start_datetime) = %s');
        $where_values = array($date);
        
        if ($branch_id) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        if ($staff_id) {
            $where_conditions[] = 'a.staff_id = %d';
            $where_values[] = intval($staff_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*,
                   c.first_name, c.last_name,
                   s.name as service_name,
                   st.name as staff_name,
                   st.color_code
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE $where_clause
            ORDER BY a.start_datetime ASC
        ", $where_values));
    }
    
    /**
     * Get dashboard notifications
     */
    public function get_dashboard_notifications($limit = 10) {
        global $wpdb;
        
        $notifications = array();
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        // Get upcoming appointments (next 2 hours)
        $upcoming_appointments = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.start_datetime, a.status,
                   c.first_name, c.last_name,
                   s.name as service_name,
                   st.name as staff_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE a.status = 'confirmed'
            AND a.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
            ORDER BY a.start_datetime ASC
            LIMIT 5
        "));
        
        foreach ($upcoming_appointments as $appointment) {
            $notifications[] = array(
                'type' => 'upcoming',
                'title' => sprintf(__('Upcoming: %s', BSM_TEXT_DOMAIN), $appointment->service_name),
                'message' => sprintf(__('%s at %s with %s', BSM_TEXT_DOMAIN), 
                    $appointment->first_name . ' ' . $appointment->last_name,
                    date('g:i A', strtotime($appointment->start_datetime)),
                    $appointment->staff_name
                ),
                'appointment_id' => $appointment->id,
                'time' => $appointment->start_datetime
            );
        }
        
        // Get missed/no-show appointments
        $missed_appointments = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.start_datetime,
                   c.first_name, c.last_name,
                   s.name as service_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            WHERE a.status = 'no_show'
            AND a.start_datetime < NOW()
            ORDER BY a.start_datetime DESC
            LIMIT 5
        "));
        
        foreach ($missed_appointments as $appointment) {
            $notifications[] = array(
                'type' => 'no_show',
                'title' => __('Missed Appointment', BSM_TEXT_DOMAIN),
                'message' => sprintf(__('%s missed %s appointment at %s', BSM_TEXT_DOMAIN),
                    $appointment->first_name . ' ' . $appointment->last_name,
                    $appointment->service_name,
                    date('g:i A', strtotime($appointment->start_datetime))
                ),
                'appointment_id' => $appointment->id,
                'time' => $appointment->start_datetime
            );
        }
        
        // Get pending payments
        $pending_payments = $wpdb->get_results($wpdb->prepare("
            SELECT a.id, a.start_datetime, a.total_amount,
                   c.first_name, c.last_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            WHERE a.payment_status = 'unpaid'
            AND a.status IN ('confirmed', 'in_progress', 'completed')
            ORDER BY a.start_datetime ASC
            LIMIT 5
        "));
        
        foreach ($pending_payments as $payment) {
            $notifications[] = array(
                'type' => 'pending_payment',
                'title' => __('Pending Payment', BSM_TEXT_DOMAIN),
                'message' => sprintf(__('%s has unpaid balance of %s', BSM_TEXT_DOMAIN),
                    $payment->first_name . ' ' . $payment->last_name,
                    '$' . number_format($payment->total_amount, 2)
                ),
                'appointment_id' => $payment->id,
                'time' => $payment->start_datetime
            );
        }
        
        // Sort by time and limit
        usort($notifications, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });
        
        return array_slice($notifications, 0, $limit);
    }
    
    /**
     * Get staff on duty
     */
    private function get_staff_on_duty($date, $branch_id = null) {
        global $wpdb;
        
        $weekday = date('w', strtotime($date));
        
        $where_conditions = array('ss.weekday = %d', 'st.is_active = 1');
        $where_values = array($weekday);
        
        if ($branch_id) {
            $where_conditions[] = 'st.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT st.id, st.name, st.role, st.color_code
            FROM {$wpdb->prefix}salon_staff st
            INNER JOIN {$wpdb->prefix}salon_staff_schedule ss ON st.id = ss.staff_id
            WHERE $where_clause
            ORDER BY st.name ASC
        ", $where_values));
    }
    
    /**
     * Get peak hours for the day
     */
    private function get_peak_hours($date, $branch_id = null) {
        global $wpdb;
        
        $where_conditions = array('DATE(a.start_datetime) = %s');
        $where_values = array($date);
        
        if ($branch_id) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                HOUR(a.start_datetime) as hour,
                COUNT(a.id) as appointment_count
            FROM {$wpdb->prefix}salon_appointments a
            WHERE $where_clause
            AND a.status != 'cancelled'
            GROUP BY HOUR(a.start_datetime)
            ORDER BY appointment_count DESC
            LIMIT 5
        ", $where_values));
    }
    
    /**
     * Get weekly statistics
     */
    public function get_weekly_stats($start_date, $end_date) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(a.start_datetime) as date,
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
                COALESCE(SUM(t.amount), 0) as daily_revenue
            FROM {$appointments_table} a
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE DATE(a.start_datetime) BETWEEN %s AND %s
            GROUP BY DATE(a.start_datetime)
            ORDER BY DATE(a.start_datetime) ASC
        ", $start_date, $end_date));
    }
    
    /**
     * Get monthly statistics
     */
    public function get_monthly_stats($year, $month) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                WEEK(a.start_datetime) as week,
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                COALESCE(SUM(t.amount), 0) as weekly_revenue
            FROM {$appointments_table} a
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE YEAR(a.start_datetime) = %d AND MONTH(a.start_datetime) = %d
            GROUP BY WEEK(a.start_datetime)
            ORDER BY WEEK(a.start_datetime) ASC
        ", $year, $month));
    }
    
    /**
     * Get staff utilization
     */
    public function get_staff_utilization($date = null, $days = 7) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $start_date = date('Y-m-d', strtotime("-$days days", strtotime($date)));
        $end_date = $date;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT st.id, st.name, st.role,
                   COUNT(a.id) as scheduled_appointments,
                   SUM(TIMESTAMPDIFF(MINUTE, a.start_datetime, a.end_datetime)) as total_minutes_scheduled,
                   SUM(CASE WHEN a.status = 'completed' THEN TIMESTAMPDIFF(MINUTE, a.start_datetime, a.end_datetime) ELSE 0 END) as actual_minutes_worked
            FROM {$wpdb->prefix}salon_staff st
            LEFT JOIN {$appointments_table} a ON st.id = a.staff_id 
            AND DATE(a.start_datetime) BETWEEN %s AND %s
            AND a.status != 'cancelled'
            WHERE st.is_active = 1
            GROUP BY st.id
            ORDER BY total_minutes_scheduled DESC
        ", $start_date, $end_date));
    }
    
    /**
     * Get service popularity
     */
    public function get_service_popularity($date = null, $days = 30) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $start_date = date('Y-m-d', strtotime("-$days days", strtotime($date)));
        $end_date = $date;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT s.id, s.name, s.base_price,
                   COUNT(a.id) as booking_count,
                   SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completion_count,
                   COALESCE(SUM(t.amount), 0) as total_revenue,
                   AVG(t.amount) as average_revenue
            FROM {$wpdb->prefix}salon_services s
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON s.id = a.service_id 
            AND DATE(a.start_datetime) BETWEEN %s AND %s
            LEFT JOIN {$wpdb->prefix}salon_transactions t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE s.is_active = 1
            GROUP BY s.id
            HAVING booking_count > 0
            ORDER BY booking_count DESC, total_revenue DESC
            LIMIT 10
        ", $start_date, $end_date));
    }
    
    /**
     * AJAX: Get dashboard statistics
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_all')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        $branch_id = intval($_POST['branch_id'] ?? 0) ?: null;
        
        $stats = $this->get_dashboard_stats($date, $branch_id);
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Get today's schedule
     */
    public function ajax_get_today_schedule() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_all')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        $branch_id = intval($_POST['branch_id'] ?? 0) ?: null;
        $staff_id = intval($_POST['staff_id'] ?? 0) ?: null;
        
        $schedule = $this->get_today_schedule($date, $branch_id, $staff_id);
        
        wp_send_json_success($schedule);
    }
    
    /**
     * AJAX: Get dashboard notifications
     */
    public function ajax_get_dashboard_notifications() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_all')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $limit = intval($_POST['limit'] ?? 10);
        
        $notifications = $this->get_dashboard_notifications($limit);
        
        wp_send_json_success($notifications);
    }
}