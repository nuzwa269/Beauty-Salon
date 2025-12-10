<?php
/**
 * Services Management Class
 */

class BSM_Services {
    
    private $table_name;
    private $categories_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'salon_services';
        $this->categories_table = $wpdb->prefix . 'salon_service_categories';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_bsm_get_service_categories', array($this, 'ajax_get_service_categories'));
        add_action('wp_ajax_bsm_create_service_category', array($this, 'ajax_create_service_category'));
    }
    
    /**
     * Create a new service category
     */
    public function create_category($data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $category_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'is_active' => 1
        );
        
        // Check if category already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->categories_table} WHERE slug = %s",
            $category_data['slug']
        ));
        
        if ($existing) {
            return false;
        }
        
        $result = $wpdb->insert($this->categories_table, $category_data);
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Create a new service
     */
    public function create_service($data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $service_data = array(
            'category_id' => intval($data['category_id']) ?: null,
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_title($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'base_price' => floatval($data['base_price'] ?? 0),
            'base_duration' => intval($data['base_duration'] ?? 60),
            'gender' => sanitize_text_field($data['gender'] ?? 'unisex'),
            'buffer_before' => intval($data['buffer_before'] ?? 0),
            'buffer_after' => intval($data['buffer_after'] ?? 0),
            'tax_rate' => floatval($data['tax_rate'] ?? 0),
            'is_active' => 1
        );
        
        // Check if service already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE slug = %s",
            $service_data['slug']
        ));
        
        if ($existing) {
            return false;
        }
        
        $result = $wpdb->insert($this->table_name, $service_data);
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Update service
     */
    public function update_service($service_id, $data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $service_data = array(
            'category_id' => intval($data['category_id']) ?: null,
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'base_price' => floatval($data['base_price'] ?? 0),
            'base_duration' => intval($data['base_duration'] ?? 60),
            'gender' => sanitize_text_field($data['gender'] ?? 'unisex'),
            'buffer_before' => intval($data['buffer_before'] ?? 0),
            'buffer_after' => intval($data['buffer_after'] ?? 0),
            'tax_rate' => floatval($data['tax_rate'] ?? 0),
        );
        
        if (isset($data['is_active'])) {
            $service_data['is_active'] = intval($data['is_active']);
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $service_data,
            array('id' => $service_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all service categories
     */
    public function get_categories($is_active = 1) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT c.*, COUNT(s.id) as service_count
            FROM {$this->categories_table} c
            LEFT JOIN {$this->table_name} s ON c.id = s.category_id AND s.is_active = 1
            WHERE c.is_active = %d
            GROUP BY c.id
            ORDER BY c.sort_order ASC, c.name ASC
        ", $is_active));
    }
    
    /**
     * Get all services
     */
    public function get_services($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'category_id' => '',
            'is_active' => 1,
            'gender' => '',
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if ($args['is_active'] !== '') {
            $where_conditions[] = 's.is_active = %d';
            $where_values[] = intval($args['is_active']);
        }
        
        if (!empty($args['category_id'])) {
            $where_conditions[] = 's.category_id = %d';
            $where_values[] = intval($args['category_id']);
        }
        
        if (!empty($args['gender'])) {
            $where_conditions[] = 's.gender = %s';
            $where_values[] = $args['gender'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT s.*, c.name as category_name
            FROM {$this->table_name} s
            LEFT JOIN {$this->categories_table} c ON s.category_id = c.id
            WHERE $where_clause
            ORDER BY s.{$args['orderby']} {$args['order']}
        ", $where_values);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get service by ID
     */
    public function get_service($service_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT s.*, c.name as category_name
            FROM {$this->table_name} s
            LEFT JOIN {$this->categories_table} c ON s.category_id = c.id
            WHERE s.id = %d
        ", $service_id));
    }
    
    /**
     * Get services by category
     */
    public function get_services_by_category($category_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$this->table_name}
            WHERE category_id = %d AND is_active = 1
            ORDER BY name ASC
        ", $category_id));
    }
    
    /**
     * Search services
     */
    public function search_services($search_term) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT s.*, c.name as category_name
            FROM {$this->table_name} s
            LEFT JOIN {$this->categories_table} c ON s.category_id = c.id
            WHERE (s.name LIKE %s OR s.description LIKE %s) AND s.is_active = 1
            ORDER BY s.name ASC
        ", $search_term, $search_term));
    }
    
    /**
     * Delete service
     */
    public function delete_service($service_id) {
        global $wpdb;
        
        // Check if service is used in appointments
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $usage_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$appointments_table}
            WHERE service_id = %d
        ", $service_id));
        
        if ($usage_count > 0) {
            // Don't delete, just deactivate
            return $this->update_service($service_id, array('is_active' => 0));
        }
        
        // Safe to delete
        $result = $wpdb->delete($this->table_name, array('id' => $service_id), array('%d'));
        
        // Remove from staff assignments
        $staff_services_table = $wpdb->prefix . 'salon_staff_services';
        $wpdb->delete($staff_services_table, array('service_id' => $service_id), array('%d'));
        
        return $result !== false;
    }
    
    /**
     * Delete service category
     */
    public function delete_category($category_id) {
        global $wpdb;
        
        // Check if category has services
        $service_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$this->table_name}
            WHERE category_id = %d
        ", $category_id));
        
        if ($service_count > 0) {
            return false; // Cannot delete category with services
        }
        
        $result = $wpdb->delete($this->categories_table, array('id' => $category_id), array('%d'));
        
        return $result !== false;
    }
    
    /**
     * Get popular services (most booked)
     */
    public function get_popular_services($limit = 10, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $where_conditions = array('a.status != "cancelled"');
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
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        $query = $wpdb->prepare("
            SELECT s.*, 
                   COUNT(a.id) as booking_count,
                   SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                   COALESCE(AVG(t.amount), 0) as average_price
            FROM {$this->table_name} s
            INNER JOIN {$appointments_table} a ON s.id = a.service_id
            LEFT JOIN {$wpdb->prefix}salon_transactions t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE $where_clause
            GROUP BY s.id
            ORDER BY booking_count DESC, completed_bookings DESC
            LIMIT %d
        ", array_merge($where_values, array($limit)));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get service revenue statistics
     */
    public function get_service_revenue($start_date = null, $end_date = null) {
        global $wpdb;
        
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
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        
        $query = $wpdb->prepare("
            SELECT s.*,
                   COUNT(a.id) as appointment_count,
                   SUM(t.amount) as total_revenue,
                   AVG(t.amount) as average_revenue
            FROM {$this->table_name} s
            INNER JOIN {$appointments_table} a ON s.id = a.service_id
            LEFT JOIN {$transactions_table} t ON a.id = t.appointment_id AND t.status = 'success'
            WHERE $where_clause
            GROUP BY s.id
            ORDER BY total_revenue DESC
        ", $where_values);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Bulk update services
     */
    public function bulk_update_services($service_ids, $data) {
        global $wpdb;
        
        if (empty($service_ids) || !is_array($service_ids)) {
            return false;
        }
        
        $updated = 0;
        
        foreach ($service_ids as $service_id) {
            if ($this->update_service($service_id, $data)) {
                $updated++;
            }
        }
        
        return $updated;
    }
    
    /**
     * Get service duration including buffers
     */
    public function get_total_duration($service_id, $staff_id = null) {
        global $wpdb;
        
        $duration = $wpdb->get_var($wpdb->prepare("
            SELECT base_duration 
            FROM {$this->table_name} 
            WHERE id = %d
        ", $service_id));
        
        if ($staff_id) {
            // Check for custom duration
            $custom_duration = $wpdb->get_var($wpdb->prepare("
                SELECT custom_duration 
                FROM {$wpdb->prefix}salon_staff_services 
                WHERE staff_id = %d AND service_id = %d AND is_active = 1
            ", $staff_id, $service_id));
            
            if ($custom_duration) {
                $duration = $custom_duration;
            }
        }
        
        // Add buffers
        $buffer_before = $wpdb->get_var($wpdb->prepare("
            SELECT buffer_before 
            FROM {$this->table_name} 
            WHERE id = %d
        ", $service_id));
        
        $buffer_after = $wpdb->get_var($wpdb->prepare("
            SELECT buffer_after 
            FROM {$this->table_name} 
            WHERE id = %d
        ", $service_id));
        
        return intval($duration) + intval($buffer_before) + intval($buffer_after);
    }
    
    /**
     * AJAX: Get service categories
     */
    public function ajax_get_service_categories() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_services')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $categories = $this->get_categories();
        wp_send_json_success($categories);
    }
    
    /**
     * AJAX: Create service category
     */
    public function ajax_create_service_category() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_services')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $category_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'sort_order' => intval($_POST['sort_order'] ?? 0)
        );
        
        $result = $this->create_category($category_data);
        
        if ($result) {
            wp_send_json_success(array('category_id' => $result));
        } else {
            wp_send_json_error(__('Failed to create category', BSM_TEXT_DOMAIN));
        }
    }
}
