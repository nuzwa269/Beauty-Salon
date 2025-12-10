<?php
/**
 * Frontend Booking Management Class
 */

class BSM_Booking {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_booking_assets'));
        add_shortcode('salon_booking', array($this, 'booking_shortcode'));
        add_shortcode('salon_my_appointments', array($this, 'my_appointments_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_bsm_get_branches', array($this, 'ajax_get_branches'));
        add_action('wp_ajax_bsm_get_services', array($this, 'ajax_get_services'));
        add_action('wp_ajax_bsm_get_staff_for_service', array($this, 'ajax_get_staff_for_service'));
        add_action('wp_ajax_bsm_get_available_slots', array($this, 'ajax_get_available_slots'));
        add_action('wp_ajax_bsm_create_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_nopriv_bsm_get_branches', array($this, 'ajax_get_branches'));
        add_action('wp_ajax_nopriv_bsm_get_services', array($this, 'ajax_get_services'));
        add_action('wp_ajax_nopriv_bsm_get_staff_for_service', array($this, 'ajax_get_staff_for_service'));
        add_action('wp_ajax_nopriv_bsm_get_available_slots', array($this, 'ajax_get_available_slots'));
        add_action('wp_ajax_nopriv_bsm_create_booking', array($this, 'ajax_create_booking'));
    }
    
    /**
     * Enqueue booking assets
     */
    public function enqueue_booking_assets() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('bsm-frontend', BSM_PLUGIN_URL . 'assets/css/frontend.css', array(), BSM_VERSION);
        wp_enqueue_script('bsm-frontend', BSM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), BSM_VERSION, true);
        
        wp_localize_script('bsm-frontend', 'bsm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bsm_frontend_nonce')
        ));
    }
    
    /**
     * Booking shortcode
     */
    public function booking_shortcode($atts) {
        $atts = shortcode_atts(array(
            'branch_id' => '',
            'style' => 'default'
        ), $atts);
        
        ob_start();
        $this->render_booking_widget($atts);
        return ob_get_clean();
    }
    
    /**
     * My appointments shortcode
     */
    public function my_appointments_shortcode($atts) {
        $atts = shortcode_atts(array(
            'login_redirect' => ''
        ), $atts);
        
        ob_start();
        $this->render_client_portal($atts);
        return ob_get_clean();
    }
    
    /**
     * Render booking widget
     */
    private function render_booking_widget($atts) {
        ?>
        <div class="bsm-booking-widget" data-branch="<?php echo esc_attr($atts['branch_id']); ?>">
            <div class="bsm-booking-steps">
                <!-- Step 1: Service Selection -->
                <div class="bsm-step bsm-step-service active">
                    <h3><?php _e('Select a Service', BSM_TEXT_DOMAIN); ?></h3>
                    <div class="bsm-form-group">
                        <label for="bsm-branch-select"><?php _e('Choose Location', BSM_TEXT_DOMAIN); ?></label>
                        <select id="bsm-branch-select" class="bsm-branch-select">
                            <option value=""><?php _e('Select a location', BSM_TEXT_DOMAIN); ?></option>
                        </select>
                    </div>
                    
                    <div class="bsm-form-group">
                        <label for="bsm-category-select"><?php _e('Service Category', BSM_TEXT_DOMAIN); ?></label>
                        <select id="bsm-category-select" class="bsm-category-select">
                            <option value=""><?php _e('Select a category', BSM_TEXT_DOMAIN); ?></option>
                        </select>
                    </div>
                    
                    <div class="bsm-services-grid" id="bsm-services-grid">
                        <!-- Services will be loaded dynamically -->
                    </div>
                </div>
                
                <!-- Step 2: Staff Selection -->
                <div class="bsm-step bsm-step-staff">
                    <h3><?php _e('Choose Your Stylist', BSM_TEXT_DOMAIN); ?></h3>
                    <div class="bsm-staff-grid" id="bsm-staff-grid">
                        <!-- Staff will be loaded dynamically -->
                    </div>
                </div>
                
                <!-- Step 3: Date & Time Selection -->
                <div class="bsm-step bsm-step-datetime">
                    <h3><?php _e('Select Date & Time', BSM_TEXT_DOMAIN); ?></h3>
                    
                    <div class="bsm-form-group">
                        <label for="bsm-date-select"><?php _e('Choose Date', BSM_TEXT_DOMAIN); ?></label>
                        <input type="date" id="bsm-date-select" class="bsm-date-picker" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>">
                    </div>
                    
                    <div class="bsm-time-slots" id="bsm-time-slots">
                        <p class="bsm-no-slots"><?php _e('Please select a date first.', BSM_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
                
                <!-- Step 4: Client Information -->
                <div class="bsm-step bsm-step-client">
                    <h3><?php _e('Your Information', BSM_TEXT_DOMAIN); ?></h3>
                    <form class="bsm-client-form">
                        <div class="bsm-form-row">
                            <div class="bsm-form-group">
                                <label for="first_name"><?php _e('First Name', BSM_TEXT_DOMAIN); ?> *</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="bsm-form-group">
                                <label for="last_name"><?php _e('Last Name', BSM_TEXT_DOMAIN); ?> *</label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="bsm-form-row">
                            <div class="bsm-form-group">
                                <label for="email"><?php _e('Email', BSM_TEXT_DOMAIN); ?> *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="bsm-form-group">
                                <label for="phone"><?php _e('Phone', BSM_TEXT_DOMAIN); ?> *</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                        </div>
                        
                        <div class="bsm-form-group">
                            <label for="gender"><?php _e('Gender', BSM_TEXT_DOMAIN); ?></label>
                            <select id="gender" name="gender">
                                <option value=""><?php _e('Prefer not to say', BSM_TEXT_DOMAIN); ?></option>
                                <option value="female"><?php _e('Female', BSM_TEXT_DOMAIN); ?></option>
                                <option value="male"><?php _e('Male', BSM_TEXT_DOMAIN); ?></option>
                                <option value="other"><?php _e('Other', BSM_TEXT_DOMAIN); ?></option>
                            </select>
                        </div>
                        
                        <div class="bsm-form-group">
                            <label for="notes"><?php _e('Special Requests', BSM_TEXT_DOMAIN); ?></label>
                            <textarea id="notes" name="notes" rows="3" 
                                      placeholder="<?php _e('Any special requests or allergies...', BSM_TEXT_DOMAIN); ?>"></textarea>
                        </div>
                        
                        <div class="bsm-form-group">
                            <label>
                                <input type="checkbox" name="terms_accepted" required>
                                <?php printf(__('I agree to the %s and %s', BSM_TEXT_DOMAIN), 
                                    '<a href="#" target="_blank">' . __('Terms of Service', BSM_TEXT_DOMAIN) . '</a>',
                                    '<a href="#" target="_blank">' . __('Privacy Policy', BSM_TEXT_DOMAIN) . '</a>'
                                ); ?>
                            </label>
                        </div>
                    </form>
                </div>
                
                <!-- Step 5: Confirmation -->
                <div class="bsm-step bsm-step-confirmation">
                    <h3><?php _e('Confirm Your Booking', BSM_TEXT_DOMAIN); ?></h3>
                    
                    <div class="bsm-booking-summary">
                        <!-- Summary will be populated by JavaScript -->
                    </div>
                    
                    <div class="bsm-payment-options">
                        <h4><?php _e('Payment Method', BSM_TEXT_DOMAIN); ?></h4>
                        <label>
                            <input type="radio" name="payment_method" value="at_salon" checked>
                            <?php _e('Pay at Salon', BSM_TEXT_DOMAIN); ?>
                        </label>
                        <label>
                            <input type="radio" name="payment_method" value="online">
                            <?php _e('Pay Online', BSM_TEXT_DOMAIN); ?>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="bsm-navigation">
                <button type="button" class="bsm-btn bsm-btn-secondary bsm-prev-step" style="display: none;">
                    <?php _e('Previous', BSM_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="bsm-btn bsm-btn-primary bsm-next-step" disabled>
                    <?php _e('Next', BSM_TEXT_DOMAIN); ?>
                </button>
                <button type="button" class="bsm-btn bsm-btn-primary bsm-submit-booking" style="display: none;">
                    <?php _e('Book Now', BSM_TEXT_DOMAIN); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render client portal
     */
    private function render_client_portal($atts) {
        ?>
        <div class="bsm-client-portal">
            <?php if (!is_user_logged_in()): ?>
                <!-- Login Form -->
                <div class="bsm-login-form">
                    <h3><?php _e('My Appointments', BSM_TEXT_DOMAIN); ?></h3>
                    <p><?php _e('Please log in to view and manage your appointments.', BSM_TEXT_DOMAIN); ?></p>
                    <?php wp_login_form(); ?>
                    
                    <div class="bsm-tracking-login">
                        <h4><?php _e('Or view by tracking code', BSM_TEXT_DOMAIN); ?></h4>
                        <form id="bsm-tracking-form">
                            <div class="bsm-form-group">
                                <label for="tracking_code"><?php _e('Tracking Code', BSM_TEXT_DOMAIN); ?></label>
                                <input type="text" id="tracking_code" name="tracking_code" required>
                            </div>
                            <button type="submit" class="bsm-btn bsm-btn-primary"><?php _e('View Appointments', BSM_TEXT_DOMAIN); ?></button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Client Portal Tabs -->
                <div class="bsm-portal-header">
                    <h2><?php _e('My Appointments', BSM_TEXT_DOMAIN); ?></h2>
                </div>
                
                <div class="bsm-portal-tabs">
                    <button class="bsm-portal-tab active" data-tab="upcoming"><?php _e('Upcoming', BSM_TEXT_DOMAIN); ?></button>
                    <button class="bsm-portal-tab" data-tab="past"><?php _e('Past', BSM_TEXT_DOMAIN); ?></button>
                    <button class="bsm-portal-tab" data-tab="profile"><?php _e('Profile', BSM_TEXT_DOMAIN); ?></button>
                </div>
                
                <div class="bsm-portal-content active" data-tab="upcoming">
                    <div class="bsm-appointment-list" id="upcoming-appointments">
                        <!-- Upcoming appointments will be loaded here -->
                    </div>
                </div>
                
                <div class="bsm-portal-content" data-tab="past">
                    <div class="bsm-appointment-list" id="past-appointments">
                        <!-- Past appointments will be loaded here -->
                    </div>
                </div>
                
                <div class="bsm-portal-content" data-tab="profile">
                    <div class="bsm-client-profile">
                        <h3><?php _e('Profile Information', BSM_TEXT_DOMAIN); ?></h3>
                        <form id="bsm-profile-form">
                            <!-- Profile form will be populated by JavaScript -->
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get branches
     */
    public function ajax_get_branches() {
        global $wpdb;
        
        $branches = $wpdb->get_results("
            SELECT id, name, address, phone 
            FROM {$wpdb->prefix}salon_branches 
            WHERE is_active = 1 AND booking_enabled = 1
            ORDER BY name
        ");
        
        wp_send_json_success($branches);
    }
    
    /**
     * AJAX: Get services
     */
    public function ajax_get_services() {
        $category_id = intval($_POST['category_id'] ?? 0);
        
        $services = new BSM_Services();
        if ($category_id) {
            $result = $services->get_services_by_category($category_id);
        } else {
            $result = $services->get_services();
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get staff for service
     */
    public function ajax_get_staff_for_service() {
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        $staff = new BSM_Staff();
        $result = $staff->get_staff();
        
        // Filter staff by service availability if date is provided
        if ($date) {
            $available_staff = array();
            foreach ($result as $staff_member) {
                if ($this->is_staff_available_for_service($staff_member->id, $service_id, $date)) {
                    $available_staff[] = $staff_member;
                }
            }
            $result = $available_staff;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get available slots
     */
    public function ajax_get_available_slots() {
        $service_id = intval($_POST['service_id']);
        $staff_id = intval($_POST['staff_id']);
        $date = sanitize_text_field($_POST['date']);
        
        if (!$service_id || !$staff_id || !$date) {
            wp_send_json_error(__('Missing required parameters', BSM_TEXT_DOMAIN));
        }
        
        $appointments = new BSM_Appointments();
        $slots = $appointments->get_available_slots($service_id, $staff_id, $date);
        
        wp_send_json_success($slots);
    }
    
    /**
     * AJAX: Create booking
     */
    public function ajax_create_booking() {
        check_ajax_referer('bsm_frontend_nonce', 'nonce');
        
        $booking_data = $_POST['booking_data'];
        
        // Create or find client
        $client_id = $this->create_or_find_client($booking_data['client']);
        
        if (!$client_id) {
            wp_send_json_error(__('Failed to create client', BSM_TEXT_DOMAIN));
        }
        
        // Create appointment
        $appointments = new BSM_Appointments();
        $appointment_data = array(
            'client_id' => $client_id,
            'service_id' => $booking_data['service_id'],
            'staff_id' => $booking_data['staff_id'],
            'start_datetime' => $booking_data['date'] . ' ' . $booking_data['time'],
            'status' => 'pending',
            'source' => 'online'
        );
        
        $appointment_id = $appointments->create_appointment($appointment_data);
        
        if (!$appointment_id) {
            wp_send_json_error(__('Failed to create appointment', BSM_TEXT_DOMAIN));
        }
        
        // Process payment if required
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'at_salon');
        
        if ($payment_method === 'online') {
            $transactions = new BSM_Transactions();
            $service = new BSM_Services();
            $service_data = $service->get_service($booking_data['service_id']);
            $amount = $service_data->base_price;
            
            $transaction_id = $transactions->process_payment($appointment_id, $amount, array(
                'gateway' => 'stripe', // This would be determined by your payment setup
                'currency' => 'USD'
            ));
            
            if (!$transaction_id) {
                wp_send_json_error(__('Payment processing failed', BSM_TEXT_DOMAIN));
            }
        }
        
        // Get appointment details for confirmation
        $appointment = $appointments->get_appointment($appointment_id);
        $staff = new BSM_Staff();
        $staff_member = $staff->get_staff_member($booking_data['staff_id']);
        $service = new BSM_Services();
        $service_data = $service->get_service($booking_data['service_id']);
        
        wp_send_json_success(array(
            'appointment_id' => $appointment_id,
            'confirmation_number' => 'BKG-' . date('Y') . '-' . $appointment_id,
            'manage_link' => home_url('/my-appointments/?token=' . $appointment->tracking_token),
            'service_name' => $service_data->name,
            'staff_name' => $staff_member->name,
            'date' => date('l, F j, Y', strtotime($appointment->start_datetime)),
            'time' => date('g:i A', strtotime($appointment->start_datetime))
        ));
    }
    
    /**
     * Check if staff is available for service
     */
    private function is_staff_available_for_service($staff_id, $service_id, $date) {
        global $wpdb;
        
        $staff_services_table = $wpdb->prefix . 'salon_staff_services';
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$staff_services_table} 
            WHERE staff_id = %d AND service_id = %d AND is_active = 1
        ", $staff_id, $service_id));
        
        return $result > 0;
    }
    
    /**
     * Create or find client
     */
    private function create_or_find_client($client_data) {
        $clients = new BSM_Clients();
        
        // Try to find existing client by email
        if (!empty($client_data['email'])) {
            $existing_client = $clients->get_client_by_email($client_data['email']);
            if ($existing_client) {
                return $existing_client->id;
            }
        }
        
        // Create new client
        return $clients->create_client($client_data);
    }
}