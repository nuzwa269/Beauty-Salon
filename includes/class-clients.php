<?php
/**
 * Clients Management Class
 */

class BSM_Clients {
    
    private $table_name;
    private $client_tags_table;
    private $client_tag_map_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'salon_clients';
        $this->client_tags_table = $wpdb->prefix . 'salon_client_tags';
        $this->client_tag_map_table = $wpdb->prefix . 'salon_client_tag_map';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_search_clients', array($this, 'ajax_search_clients'));
        add_action('wp_ajax_bsm_get_client_appointments', array($this, 'ajax_get_client_appointments'));
        add_action('wp_ajax_bsm_add_client_tag', array($this, 'ajax_add_client_tag'));
    }
    
    /**
     * Create a new client
     */
    public function create_client($data) {
        global $wpdb;
        
        if (empty($data['first_name'])) {
            return false;
        }
        
        $client_data = array(
            'wp_user_id' => intval($data['wp_user_id']) ?: null,
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'gender' => sanitize_text_field($data['gender'] ?? ''),
            'date_of_birth' => sanitize_text_field($data['date_of_birth'] ?? ''),
            'notes_internal' => sanitize_textarea_field($data['notes_internal'] ?? ''),
            'notes_public' => sanitize_textarea_field($data['notes_public'] ?? ''),
            'membership_id' => intval($data['membership_id']) ?: null
        );
        
        $result = $wpdb->insert($this->table_name, $client_data);
        
        if ($result === false) {
            return false;
        }
        
        $client_id = $wpdb->insert_id;
        
        // Create WordPress user if needed
        if (!empty($data['create_user']) && !empty($data['email'])) {
            $this->create_wordpress_user($client_id, $data);
        }
        
        return $client_id;
    }
    
    /**
     * Update client
     */
    public function update_client($client_id, $data) {
        global $wpdb;
        
        if (empty($data['first_name'])) {
            return false;
        }
        
        $client_data = array(
            'first_name' => sanitize_text_field($data['first_name']),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'gender' => sanitize_text_field($data['gender'] ?? ''),
            'date_of_birth' => sanitize_text_field($data['date_of_birth'] ?? ''),
            'notes_internal' => sanitize_textarea_field($data['notes_internal'] ?? ''),
            'notes_public' => sanitize_textarea_field($data['notes_public'] ?? ''),
            'membership_id' => intval($data['membership_id']) ?: null
        );
        
        if (isset($data['total_visits'])) {
            $client_data['total_visits'] = intval($data['total_visits']);
        }
        
        if (isset($data['total_spent'])) {
            $client_data['total_spent'] = floatval($data['total_spent']);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $client_data,
            array('id' => $client_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all clients
     */
    public function get_clients($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'search' => '',
            'gender' => '',
            'branch_id' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['gender'])) {
            $where_conditions[] = 'gender = %s';
            $where_values[] = $args['gender'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT c.*,
                   u.display_name as wp_display_name
            FROM {$this->table_name} c
            LEFT JOIN {$wpdb->users} u ON c.wp_user_id = u.ID
            WHERE $where_clause
            ORDER BY c.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset'])));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get client by ID
     */
    public function get_client($client_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT c.*,
                   u.display_name as wp_display_name,
                   u.user_email as wp_email
            FROM {$this->table_name} c
            LEFT JOIN {$wpdb->users} u ON c.wp_user_id = u.ID
            WHERE c.id = %d
        ", $client_id));
    }
    
    /**
     * Get client by email
     */
    public function get_client_by_email($email) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$this->table_name}
            WHERE email = %s
        ", $email));
    }
    
    /**
     * Search clients
     */
    public function search_clients($search_term, $limit = 20) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT id, first_name, last_name, email, phone, last_visit_date
            FROM {$this->table_name}
            WHERE (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)
            ORDER BY first_name ASC, last_name ASC
            LIMIT %d
        ", $search_term, $search_term, $search_term, $search_term, $limit));
    }
    
    /**
     * Get client appointments
     */
    public function get_client_appointments($client_id, $limit = 10, $upcoming_only = false) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        $where_conditions = array('a.client_id = %d');
        $where_values = array($client_id);
        
        if ($upcoming_only) {
            $where_conditions[] = 'a.start_datetime > NOW()';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*,
                   s.name as service_name,
                   st.name as staff_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE $where_clause
            ORDER BY a.start_datetime DESC
            LIMIT %d
        ", array_merge($where_values, array($limit))));
    }
    
    /**
     * Update client statistics
     */
    public function update_client_stats($client_id) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(a.id) as total_visits,
                COALESCE(SUM(t.amount), 0) as total_spent,
                MAX(a.start_datetime) as last_visit_date
            FROM {$appointments_table} a
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE a.client_id = %d AND a.status = 'completed'
        ", $client_id));
        
        if ($stats) {
            $wpdb->update(
                $this->table_name,
                array(
                    'total_visits' => intval($stats->total_visits),
                    'total_spent' => floatval($stats->total_spent),
                    'last_visit_date' => $stats->last_visit_date
                ),
                array('id' => $client_id)
            );
        }
    }
    
    /**
     * Get top clients
     */
    public function get_top_clients($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        $where_conditions = array('a.status = "completed"');
        $where_values = array();
        
        if ($start_date) {
            $where_conditions[] = 'DATE(a.start_datetime) >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 'DATE(a.start_datetime) <= %s';
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT c.*,
                   COUNT(a.id) as visit_count,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   AVG(t.amount) as average_visit_value
            FROM {$this->table_name} c
            INNER JOIN {$appointments_table} a ON c.id = a.client_id
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE $where_clause
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT %d
        ", array_merge($where_values, array($limit)));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get client retention statistics
     */
    public function get_client_retention($start_date, $end_date) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_clients,
                SUM(CASE WHEN c.total_visits > 1 THEN 1 ELSE 0 END) as returning_clients,
                SUM(CASE WHEN c.total_visits = 1 THEN 1 ELSE 0 END) as one_time_clients,
                AVG(c.total_visits) as average_visits_per_client
            FROM {$this->table_name} c
            INNER JOIN {$appointments_table} a ON c.id = a.client_id
            WHERE DATE(a.start_datetime) BETWEEN %s AND %s
            AND a.status = 'completed'
        ", $start_date, $end_date));
    }
    
    /**
     * Add client tag
     */
    public function add_tag($client_id, $tag_name, $color = '#3498db') {
        global $wpdb;
        
        // Check if tag exists, create if not
        $tag = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->client_tags_table}
            WHERE name = %s
        ", $tag_name));
        
        if (!$tag) {
            $wpdb->insert($this->client_tags_table, array(
                'name' => sanitize_text_field($tag_name),
                'color' => sanitize_hex_color($color)
            ));
            $tag_id = $wpdb->insert_id;
        } else {
            $tag_id = $tag->id;
        }
        
        // Add tag to client
        $result = $wpdb->insert($this->client_tag_map_table, array(
            'client_id' => $client_id,
            'tag_id' => $tag_id
        ));
        
        return $result !== false;
    }
    
    /**
     * Remove client tag
     */
    public function remove_tag($client_id, $tag_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->client_tag_map_table,
            array('client_id' => $client_id, 'tag_id' => $tag_id),
            array('%d', '%d')
        ) !== false;
    }
    
    /**
     * Get client tags
     */
    public function get_client_tags($client_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT t.*
            FROM {$this->client_tags_table} t
            INNER JOIN {$this->client_tag_map_table} tm ON t.id = tm.tag_id
            WHERE tm.client_id = %d
            ORDER BY t.name ASC
        ", $client_id));
    }
    
    /**
     * Get all client tags
     */
    public function get_all_tags() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT t.*, COUNT(tm.client_id) as usage_count
            FROM {$this->client_tags_table} t
            LEFT JOIN {$this->client_tag_map_table} tm ON t.id = tm.tag_id
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
    }
    
    /**
     * Create WordPress user for client
     */
    private function create_wordpress_user($client_id, $data) {
        $email = $data['email'];
        $password = wp_generate_password();
        $user_id = wp_create_user($email, $password, $email);
        
        if (!is_wp_error($user_id)) {
            global $wpdb;
            $wpdb->update(
                $this->table_name,
                array('wp_user_id' => $user_id),
                array('id' => $client_id)
            );
            
            // Set user display name
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $data['first_name'] . ' ' . $data['last_name']
            ));
            
            // Send welcome email
            wp_new_user_notification($user_id, null, 'user');
        }
        
        return $user_id;
    }
    
    /**
     * Delete client
     */
    public function delete_client($client_id) {
        global $wpdb;
        
        // Check for existing appointments
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $appointment_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$appointments_table}
            WHERE client_id = %d
        ", $client_id));
        
        if ($appointment_count > 0) {
            return false; // Cannot delete client with appointments
        }
        
        // Get client data to check for WordPress user
        $client = $this->get_client($client_id);
        
        // Delete client
        $result = $wpdb->delete($this->table_name, array('id' => $client_id), array('%d'));
        
        // Clean up tags
        $wpdb->delete($this->client_tag_map_table, array('client_id' => $client_id), array('%d'));
        
        // Optionally delete WordPress user
        if (!empty($client->wp_user_id)) {
            wp_delete_user($client->wp_user_id);
        }
        
        return $result !== false;
    }
    
    /**
     * AJAX: Search clients
     */
    public function ajax_search_clients() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_clients')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $search_term = sanitize_text_field($_POST['search']);
        $clients = $this->search_clients($search_term);
        
        wp_send_json_success($clients);
    }
    
    /**
     * AJAX: Get client appointments
     */
    public function ajax_get_client_appointments() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_clients')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $client_id = intval($_POST['client_id']);
        $upcoming_only = isset($_POST['upcoming_only']) ? (bool) $_POST['upcoming_only'] : false;
        
        $appointments = $this->get_client_appointments($client_id, 20, $upcoming_only);
        
        wp_send_json_success($appointments);
    }
    
    /**
     * AJAX: Add client tag
     */
    public function ajax_add_client_tag() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_clients')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $client_id = intval($_POST['client_id']);
        $tag_name = sanitize_text_field($_POST['tag_name']);
        $color = sanitize_hex_color($_POST['color'] ?? '#3498db');
        
        $result = $this->add_tag($client_id, $tag_name, $color);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to add tag', BSM_TEXT_DOMAIN));
        }
    }
}
