<?php
/**
 * Transactions Management Class
 */

class BSM_Transactions {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'salon_transactions';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_bsm_add_manual_payment', array($this, 'ajax_add_manual_payment'));
        add_action('wp_ajax_bsm_refund_payment', array($this, 'ajax_refund_payment'));
    }
    
    /**
     * Create a new transaction
     */
    public function create_transaction($data) {
        global $wpdb;
        
        $transaction_data = array(
            'appointment_id' => intval($data['appointment_id']) ?: null,
            'client_id' => intval($data['client_id']) ?: null,
            'branch_id' => intval($data['branch_id']) ?: null,
            'amount' => floatval($data['amount']),
            'currency' => sanitize_text_field($data['currency'] ?? 'USD'),
            'method' => sanitize_text_field($data['method'] ?? 'manual'),
            'gateway' => sanitize_text_field($data['gateway'] ?? ''),
            'transaction_ref' => sanitize_text_field($data['transaction_ref'] ?? ''),
            'gateway_response_json' => wp_json_encode($data['gateway_response'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'success'),
            'paid_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($this->table_name, $transaction_data);
        
        if ($result === false) {
            return false;
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Update appointment payment status if linked
        if ($transaction_data['appointment_id']) {
            $this->update_appointment_payment_status($transaction_data['appointment_id']);
        }
        
        return $transaction_id;
    }
    
    /**
     * Process payment (for online payments)
     */
    public function process_payment($appointment_id, $amount, $payment_data) {
        // This would integrate with payment gateways like Stripe, PayPal, etc.
        // For now, we'll simulate a successful payment
        
        $gateway = sanitize_text_field($payment_data['gateway'] ?? 'stripe');
        
        // Simulate payment processing
        $transaction_ref = $gateway . '_' . wp_generate_password(16, false);
        
        // In real implementation, you would:
        // 1. Call the payment gateway API
        // 2. Handle the response
        // 3. Store the gateway response
        
        $transaction_data = array(
            'appointment_id' => $appointment_id,
            'amount' => $amount,
            'currency' => sanitize_text_field($payment_data['currency'] ?? 'USD'),
            'method' => 'online',
            'gateway' => $gateway,
            'transaction_ref' => $transaction_ref,
            'gateway_response' => array(
                'status' => 'completed',
                'transaction_id' => $transaction_ref,
                'gateway_response' => 'Simulated successful payment'
            ),
            'status' => 'success'
        );
        
        return $this->create_transaction($transaction_data);
    }
    
    /**
     * Add manual payment (cash, check, etc.)
     */
    public function add_manual_payment($appointment_id, $amount, $method, $reference = '') {
        global $wpdb;
        
        $appointment = $wpdb->get_row($wpdb->prepare("
            SELECT client_id, branch_id, total_amount
            FROM {$wpdb->prefix}salon_appointments
            WHERE id = %d
        ", $appointment_id));
        
        if (!$appointment) {
            return false;
        }
        
        $transaction_data = array(
            'appointment_id' => $appointment_id,
            'client_id' => $appointment->client_id,
            'branch_id' => $appointment->branch_id,
            'amount' => $amount,
            'method' => $method,
            'transaction_ref' => $reference,
            'status' => 'success'
        );
        
        return $this->create_transaction($transaction_data);
    }
    
    /**
     * Process refund
     */
    public function process_refund($transaction_id, $amount, $reason = '') {
        global $wpdb;
        
        $transaction = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->table_name}
            WHERE id = %d
        ", $transaction_id));
        
        if (!$transaction) {
            return false;
        }
        
        // Create refund transaction
        $refund_data = array(
            'appointment_id' => $transaction->appointment_id,
            'client_id' => $transaction->client_id,
            'branch_id' => $transaction->branch_id,
            'amount' => -$amount, // Negative amount for refund
            'currency' => $transaction->currency,
            'method' => $transaction->method,
            'gateway' => $transaction->gateway,
            'transaction_ref' => $transaction->transaction_ref . '_refund_' . wp_generate_password(8, false),
            'gateway_response' => array(
                'refund_reason' => $reason,
                'original_transaction' => $transaction->transaction_ref
            ),
            'status' => 'refunded'
        );
        
        $refund_id = $this->create_transaction($refund_data);
        
        // Update appointment payment status
        if ($transaction->appointment_id) {
            $this->update_appointment_payment_status($transaction->appointment_id);
        }
        
        return $refund_id;
    }
    
    /**
     * Get transaction by ID
     */
    public function get_transaction($transaction_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT t.*,
                   c.first_name, c.last_name, c.email as client_email,
                   s.name as service_name,
                   a.start_datetime
            FROM {$this->table_name} t
            LEFT JOIN {$wpdb->prefix}salon_clients c ON t.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON t.appointment_id = a.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            WHERE t.id = %d
        ", $transaction_id));
    }
    
    /**
     * Get transactions with filters
     */
    public function get_transactions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'status' => '',
            'method' => '',
            'gateway' => '',
            'client_id' => '',
            'appointment_id' => '',
            'branch_id' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'paid_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 't.paid_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 't.paid_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = 't.status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['method'])) {
            $where_conditions[] = 't.method = %s';
            $where_values[] = $args['method'];
        }
        
        if (!empty($args['gateway'])) {
            $where_conditions[] = 't.gateway = %s';
            $where_values[] = $args['gateway'];
        }
        
        if (!empty($args['client_id'])) {
            $where_conditions[] = 't.client_id = %d';
            $where_values[] = intval($args['client_id']);
        }
        
        if (!empty($args['appointment_id'])) {
            $where_conditions[] = 't.appointment_id = %d';
            $where_values[] = intval($args['appointment_id']);
        }
        
        if (!empty($args['branch_id'])) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($args['branch_id']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT t.*,
                   c.first_name, c.last_name, c.email as client_email,
                   s.name as service_name,
                   a.start_datetime
            FROM {$this->table_name} t
            LEFT JOIN {$wpdb->prefix}salon_clients c ON t.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON t.appointment_id = a.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            WHERE $where_clause
            ORDER BY t.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get revenue statistics
     */
    public function get_revenue_stats($start_date = null, $end_date = null, $branch_id = null) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"');
        $where_values = array();
        
        if ($start_date) {
            $where_conditions[] = 't.paid_at >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 't.paid_at <= %s';
            $where_values[] = $end_date;
        }
        
        if ($branch_id) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(t.id) as total_transactions,
                SUM(t.amount) as total_revenue,
                AVG(t.amount) as average_transaction,
                MIN(t.amount) as min_transaction,
                MAX(t.amount) as max_transaction
            FROM {$this->table_name} t
            WHERE $where_clause
        ", $where_values));
    }
    
    /**
     * Get revenue by method
     */
    public function get_revenue_by_method($start_date = null, $end_date = null, $branch_id = null) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"');
        $where_values = array();
        
        if ($start_date) {
            $where_conditions[] = 't.paid_at >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 't.paid_at <= %s';
            $where_values[] = $end_date;
        }
        
        if ($branch_id) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.method,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_revenue,
                AVG(t.amount) as average_amount
            FROM {$this->table_name} t
            WHERE $where_clause
            GROUP BY t.method
            ORDER BY total_revenue DESC
        ", $where_values));
    }
    
    /**
     * Get daily revenue
     */
    public function get_daily_revenue($start_date, $end_date, $branch_id = null) {
        global $wpdb;
        
        $where_conditions = array('t.status = "success"', 'DATE(t.paid_at) BETWEEN %s AND %s');
        $where_values = array($start_date, $end_date);
        
        if ($branch_id) {
            $where_conditions[] = 't.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(t.paid_at) as date,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as daily_revenue,
                AVG(t.amount) as average_transaction
            FROM {$this->table_name} t
            WHERE $where_clause
            GROUP BY DATE(t.paid_at)
            ORDER BY date ASC
        ", $where_values));
    }
    
    /**
     * Get pending payments
     */
    public function get_pending_payments($branch_id = null) {
        global $wpdb;
        
        $where_conditions = array('a.payment_status = "unpaid"');
        $where_values = array();
        
        if ($branch_id) {
            $where_conditions[] = 'a.branch_id = %d';
            $where_values[] = intval($branch_id);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*,
                   c.first_name, c.last_name,
                   s.name as service_name,
                   st.name as staff_name
            FROM {$wpdb->prefix}salon_appointments a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE $where_clause
            ORDER BY a.start_datetime ASC
        ", $where_values));
    }
    
    /**
     * Update appointment payment status
     */
    private function update_appointment_payment_status($appointment_id) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        // Get appointment total
        $appointment = $wpdb->get_row($wpdb->prepare("
            SELECT total_amount 
            FROM {$appointments_table}
            WHERE id = %d
        ", $appointment_id));
        
        if (!$appointment) {
            return false;
        }
        
        // Get total paid amount
        $total_paid = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM {$this->table_name}
            WHERE appointment_id = %d AND status = 'success'
        ", $appointment_id));
        
        // Determine payment status
        $total_amount = floatval($appointment->total_amount);
        $paid_amount = floatval($total_paid);
        
        if ($paid_amount >= $total_amount) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'unpaid';
        }
        
        // Update appointment
        return $wpdb->update(
            $appointments_table,
            array('payment_status' => $payment_status),
            array('id' => $appointment_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Calculate commission
     */
    public function calculate_commission($staff_id, $start_date, $end_date) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $staff_table = $wpdb->prefix . 'salon_staff';
        
        $commission = $wpdb->get_row($wpdb->prepare("
            SELECT st.commission_type, st.commission_value,
                   COUNT(a.id) as completed_appointments,
                   COALESCE(SUM(t.amount), 0) as total_revenue
            FROM {$staff_table} st
            LEFT JOIN {$appointments_table} a ON st.id = a.staff_id 
            AND DATE(a.start_datetime) BETWEEN %s AND %s
            AND a.status = 'completed'
            LEFT JOIN {$this->table_name} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE st.id = %d
            GROUP BY st.id
        ", $start_date, $end_date, $staff_id));
        
        if (!$commission || $commission->total_revenue == 0) {
            return 0;
        }
        
        if ($commission->commission_type === 'percentage') {
            return ($commission->total_revenue * $commission->commission_value) / 100;
        } elseif ($commission->commission_type === 'fixed') {
            return $commission->completed_appointments * $commission->commission_value;
        }
        
        return 0;
    }
    
    /**
     * AJAX: Process payment
     */
    public function ajax_process_payment() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_payments')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $appointment_id = intval($_POST['appointment_id']);
        $amount = floatval($_POST['amount']);
        $payment_data = array(
            'gateway' => sanitize_text_field($_POST['gateway'] ?? 'stripe'),
            'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
            'card_token' => sanitize_text_field($_POST['card_token'] ?? ''),
        );
        
        $result = $this->process_payment($appointment_id, $amount, $payment_data);
        
        if ($result) {
            wp_send_json_success(array('transaction_id' => $result));
        } else {
            wp_send_json_error(__('Payment processing failed', BSM_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Add manual payment
     */
    public function ajax_add_manual_payment() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_payments')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $appointment_id = intval($_POST['appointment_id']);
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $reference = sanitize_text_field($_POST['reference'] ?? '');
        
        $result = $this->add_manual_payment($appointment_id, $amount, $method, $reference);
        
        if ($result) {
            wp_send_json_success(array('transaction_id' => $result));
        } else {
            wp_send_json_error(__('Failed to add payment', BSM_TEXT_DOMAIN));
        }
    }
    
    /**
     * AJAX: Refund payment
     */
    public function ajax_refund_payment() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_payments')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $transaction_id = intval($_POST['transaction_id']);
        $amount = floatval($_POST['amount']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        $result = $this->process_refund($transaction_id, $amount, $reason);
        
        if ($result) {
            wp_send_json_success(array('refund_id' => $result));
        } else {
            wp_send_json_error(__('Refund processing failed', BSM_TEXT_DOMAIN));
        }
    }
}