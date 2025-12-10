<?php
/**
 * User Roles and Capabilities Management
 */

class BSM_Roles {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'add_roles_and_capabilities'));
        add_action('wp_ajax_bsm_manage_user_roles', array($this, 'manage_user_roles'));
    }
    
    /**
     * Add custom roles and capabilities
     */
    public function add_roles_and_capabilities() {
        // Salon Owner role
        add_role('salon_owner', __('Salon Owner', BSM_TEXT_DOMAIN), array(
            'read' => true,
            'manage_salon_all' => true,
            'manage_salon_appointments' => true,
            'manage_salon_clients' => true,
            'manage_salon_services' => true,
            'manage_salon_staff' => true,
            'manage_salon_payments' => true,
            'manage_salon_reports' => true,
            'manage_salon_settings' => true,
            'manage_options' => false,
        ));
        
        // Manager role
        add_role('salon_manager', __('Salon Manager', BSM_TEXT_DOMAIN), array(
            'read' => true,
            'manage_salon_appointments' => true,
            'manage_salon_clients' => true,
            'manage_salon_services' => true,
            'manage_salon_staff' => true,
            'manage_salon_payments' => true,
            'manage_salon_reports' => true,
            'manage_salon_settings' => false,
        ));
        
        // Receptionist role
        add_role('salon_receptionist', __('Salon Receptionist', BSM_TEXT_DOMAIN), array(
            'read' => true,
            'manage_salon_appointments' => true,
            'manage_salon_clients' => true,
            'manage_salon_payments' => false,
            'manage_salon_reports' => false,
            'manage_salon_services' => false,
            'manage_salon_staff' => false,
            'manage_salon_settings' => false,
        ));
        
        // Staff role
        add_role('salon_staff', __('Salon Staff', BSM_TEXT_DOMAIN), array(
            'read' => true,
            'view_own_schedule' => true,
            'update_appointment_status' => true,
            'add_service_notes' => true,
        ));
        
        // Add capabilities to Administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_salon_all');
            $admin_role->add_cap('manage_salon_appointments');
            $admin_role->add_cap('manage_salon_clients');
            $admin_role->add_cap('manage_salon_services');
            $admin_role->add_cap('manage_salon_staff');
            $admin_role->add_cap('manage_salon_payments');
            $admin_role->add_cap('manage_salon_reports');
            $admin_role->add_cap('manage_salon_settings');
        }
        
        // Add capabilities to Editor
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('manage_salon_appointments');
            $editor_role->add_cap('manage_salon_clients');
            $editor_role->add_cap('manage_salon_reports');
        }
        
        // Add capabilities to Author
        $author_role = get_role('author');
        if ($author_role) {
            $author_role->add_cap('view_own_schedule');
            $author_role->add_cap('update_appointment_status');
        }
    }
    
    /**
     * Get all salon roles
     */
    public function get_salon_roles() {
        return array(
            'salon_owner' => __('Salon Owner', BSM_TEXT_DOMAIN),
            'salon_manager' => __('Salon Manager', BSM_TEXT_DOMAIN),
            'salon_receptionist' => __('Salon Receptionist', BSM_TEXT_DOMAIN),
            'salon_staff' => __('Salon Staff', BSM_TEXT_DOMAIN),
        );
    }
    
    /**
     * Get role capabilities
     */
    public function get_role_capabilities($role_name) {
        $roles = $this->get_salon_roles();
        
        if (!isset($roles[$role_name])) {
            return array();
        }
        
        $role = get_role($role_name);
        return $role ? $role->capabilities : array();
    }
    
    /**
     * Update user role
     */
    public function update_user_role($user_id, $new_role) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Remove user from all salon roles
        $salon_roles = $this->get_salon_roles();
        foreach ($salon_roles as $role_key => $role_name) {
            $user->remove_role($role_key);
        }
        
        // Add user to new role if it's a salon role
        if (isset($salon_roles[$new_role])) {
            $user->add_role($new_role);
        }
        
        return true;
    }
    
    /**
     * Get users by salon role
     */
    public function get_users_by_salon_role($role_name) {
        $salon_roles = $this->get_salon_roles();
        
        if (!isset($salon_roles[$role_name])) {
            return array();
        }
        
        return get_users(array(
            'role' => $role_name,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Check if user has salon access
     */
    public function user_has_salon_access($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Check if user has any salon capabilities
        $salon_capabilities = array(
            'manage_salon_all',
            'manage_salon_appointments',
            'manage_salon_clients',
            'manage_salon_services',
            'manage_salon_staff',
            'manage_salon_payments',
            'manage_salon_reports',
            'manage_salon_settings'
        );
        
        foreach ($salon_capabilities as $cap) {
            if (user_can($user, $cap)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * AJAX: Manage user roles
     */
    public function manage_user_roles() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_all')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $action = sanitize_text_field($_POST['sub_action']);
        $user_id = intval($_POST['user_id']);
        
        switch ($action) {
            case 'update_role':
                $new_role = sanitize_text_field($_POST['new_role']);
                $result = $this->update_user_role($user_id, $new_role);
                break;
                
            case 'get_user_roles':
                $roles = $this->get_salon_roles();
                wp_send_json_success($roles);
                break;
                
            default:
                wp_send_json_error(__('Invalid action', BSM_TEXT_DOMAIN));
        }
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update user role', BSM_TEXT_DOMAIN));
        }
    }
    
    /**
     * Remove salon roles (for cleanup)
     */
    public static function remove_salon_roles() {
        $roles = array('salon_owner', 'salon_manager', 'salon_receptionist', 'salon_staff');
        
        foreach ($roles as $role) {
            remove_role($role);
        }
    }
    
    /**
     * Get capability descriptions
     */
    public static function get_capability_descriptions() {
        return array(
            'manage_salon_all' => __('Full access to all salon features', BSM_TEXT_DOMAIN),
            'manage_salon_appointments' => __('Manage appointments and bookings', BSM_TEXT_DOMAIN),
            'manage_salon_clients' => __('Manage client information', BSM_TEXT_DOMAIN),
            'manage_salon_services' => __('Manage services and pricing', BSM_TEXT_DOMAIN),
            'manage_salon_staff' => __('Manage staff and schedules', BSM_TEXT_DOMAIN),
            'manage_salon_payments' => __('Handle payments and transactions', BSM_TEXT_DOMAIN),
            'manage_salon_reports' => __('Access reports and analytics', BSM_TEXT_DOMAIN),
            'manage_salon_settings' => __('Modify salon settings and configuration', BSM_TEXT_DOMAIN),
            'view_own_schedule' => __('View personal schedule', BSM_TEXT_DOMAIN),
            'update_appointment_status' => __('Update appointment status', BSM_TEXT_DOMAIN),
            'add_service_notes' => __('Add notes to appointments', BSM_TEXT_DOMAIN),
        );
    }
}