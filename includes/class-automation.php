<?php
/**
 * Automation and Scheduling Class
 */

class BSM_Automation {
    
    private $notifications_table;
    
    public function __construct() {
        global $wpdb;
        $this->notifications_table = $wpdb->prefix . 'salon_notifications';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('bsm_send_reminders', array($this, 'send_appointment_reminders'));
        add_action('bsm_process_followups', array($this, 'send_followup_messages'));
        add_action('bsm_birthday_reminders', array($this, 'send_birthday_reminders'));
        add_action('bsm_auto_no_show', array($this, 'process_no_shows'));
        add_action('wp_ajax_bsm_test_notification', array($this, 'ajax_test_notification'));
    }
    
    /**
     * Send appointment reminders
     */
    public function send_appointment_reminders() {
        $settings = new BSM_Settings();
        $reminder_times = $settings->get_setting('notifications_reminder_times', array(24, 2));
        
        foreach ($reminder_times as $hours_before) {
            $this->process_reminder_batch($hours_before);
        }
    }
    
    /**
     * Process reminder batch for specific time
     */
    private function process_reminder_batch($hours_before) {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        
        // Get appointments that need reminders
        $appointments = $wpdb->get_results($wpdb->prepare("
            SELECT a.*,
                   c.first_name, c.last_name, c.email, c.phone,
                   s.name as service_name,
                   st.name as staff_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            LEFT JOIN {$wpdb->prefix}salon_staff st ON a.staff_id = st.id
            WHERE a.status = 'confirmed'
            AND a.reminder_sent < %d
            AND a.start_datetime BETWEEN DATE_ADD(NOW(), INTERVAL %d HOUR) AND DATE_ADD(NOW(), INTERVAL %d HOUR)
            ORDER BY a.start_datetime ASC
        ", 1, $hours_before, $hours_before + 1));
        
        foreach ($appointments as $appointment) {
            $this->send_reminder($appointment, $hours_before);
            
            // Mark reminder as sent
            $wpdb->update(
                $appointments_table,
                array('reminder_sent' => 1),
                array('id' => $appointment->id)
            );
        }
    }
    
    /**
     * Send individual reminder
     */
    private function send_reminder($appointment, $hours_before) {
        $template_data = array(
            'appointment' => $appointment,
            'hours_before' => $hours_before
        );
        
        // Send email reminder
        if (!empty($appointment->email)) {
            $this->send_email_reminder($appointment, $template_data);
        }
        
        // Send SMS reminder (would integrate with SMS provider)
        if (!empty($appointment->phone)) {
            $this->send_sms_reminder($appointment, $template_data);
        }
        
        // Send WhatsApp reminder (would integrate with WhatsApp API)
        if (!empty($appointment->phone)) {
            $this->send_whatsapp_reminder($appointment, $template_data);
        }
        
        // Log notification
        $this->log_notification($appointment->id, $appointment->client_id, 'reminder', 'email', 'sent');
    }
    
    /**
     * Send email reminder
     */
    private function send_email_reminder($appointment, $data) {
        $settings = new BSM_Settings();
        $email_settings = $settings->get_settings_by_category('notifications');
        
        $subject = sprintf(
            __('Reminder: %s appointment at %s', BSM_TEXT_DOMAIN),
            $data['appointment']->service_name,
            date('g:i A', strtotime($data['appointment']->start_datetime))
        );
        
        $message = $this->get_email_template('reminder', $data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $email_settings['email_from_name'] . ' <' . $email_settings['email_from_address'] . '>'
        );
        
        wp_mail($data['appointment']->email, $subject, $message, $headers);
    }
    
    /**
     * Send SMS reminder
     */
    private function send_sms_reminder($appointment, $data) {
        // This would integrate with your SMS provider (Twilio, etc.)
        $sms_message = sprintf(
            __('Reminder: You have a %s appointment with %s at %s. Please reply YES to confirm.', BSM_TEXT_DOMAIN),
            $data['appointment']->service_name,
            $data['appointment']->staff_name,
            date('g:i A', strtotime($data['appointment']->start_datetime))
        );
        
        // TODO: Integrate with SMS provider
        // $this->send_sms($data['appointment']->phone, $sms_message);
        
        $this->log_notification($appointment->id, $appointment->client_id, 'reminder', 'sms', 'sent');
    }
    
    /**
     * Send WhatsApp reminder
     */
    private function send_whatsapp_reminder($appointment, $data) {
        // This would integrate with WhatsApp Business API
        $whatsapp_message = sprintf(
            __('Hello %s! This is a reminder about your %s appointment with %s at %s. See you soon!', BSM_TEXT_DOMAIN),
            $data['appointment']->first_name,
            $data['appointment']->service_name,
            $data['appointment']->staff_name,
            date('g:i A', strtotime($data['appointment']->start_datetime))
        );
        
        // TODO: Integrate with WhatsApp API
        // $this->send_whatsapp($data['appointment']->phone, $whatsapp_message);
        
        $this->log_notification($appointment->id, $appointment->client_id, 'reminder', 'whatsapp', 'sent');
    }
    
    /**
     * Send follow-up messages
     */
    public function send_followup_messages() {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $settings = new BSM_Settings();
        $followup_time = $settings->get_setting('notifications_followup_time', 24);
        
        $appointments = $wpdb->get_results($wpdb->prepare("
            SELECT a.*,
                   c.first_name, c.last_name, c.email, c.phone,
                   s.name as service_name
            FROM {$appointments_table} a
            LEFT JOIN {$wpdb->prefix}salon_clients c ON a.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_services s ON a.service_id = s.id
            WHERE a.status = 'completed'
            AND a.followup_sent = 0
            AND a.start_datetime < DATE_SUB(NOW(), INTERVAL %d HOUR)
            ORDER BY a.start_datetime ASC
            LIMIT 50
        ", $followup_time));
        
        foreach ($appointments as $appointment) {
            $this->send_followup($appointment);
            
            // Mark follow-up as sent
            $wpdb->update(
                $appointments_table,
                array('followup_sent' => 1),
                array('id' => $appointment->id)
            );
        }
    }
    
    /**
     * Send individual follow-up
     */
    private function send_followup($appointment) {
        $template_data = array('appointment' => $appointment);
        
        // Send email follow-up
        if (!empty($appointment->email)) {
            $subject = sprintf(__('Thank you for your visit!', BSM_TEXT_DOMAIN));
            $message = $this->get_email_template('followup', $template_data);
            
            wp_mail($appointment->email, $subject, $message);
        }
        
        $this->log_notification($appointment->id, $appointment->client_id, 'followup', 'email', 'sent');
    }
    
    /**
     * Send birthday reminders
     */
    public function send_birthday_reminders() {
        global $wpdb;
        
        $clients_table = $wpdb->prefix . 'salon_clients';
        
        $clients = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$clients_table}
            WHERE MONTH(date_of_birth) = MONTH(NOW())
            AND DAY(date_of_birth) = DAY(NOW())
            AND email IS NOT NULL AND email != ''
        "));
        
        foreach ($clients as $client) {
            $this->send_birthday_message($client);
        }
    }
    
    /**
     * Send individual birthday message
     */
    private function send_birthday_message($client) {
        $subject = __('Happy Birthday! Special Offer Inside', BSM_TEXT_DOMAIN);
        $template_data = array('client' => $client);
        $message = $this->get_email_template('birthday', $template_data);
        
        wp_mail($client->email, $subject, $message);
        
        $this->log_notification(null, $client->id, 'birthday', 'email', 'sent');
    }
    
    /**
     * Process no-show appointments
     */
    public function process_no_shows() {
        global $wpdb;
        
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $settings = new BSM_Settings();
        $grace_period = $settings->get_setting('booking_grace_period_minutes', 15);
        
        // Mark appointments as no-show after grace period
        $wpdb->query($wpdb->prepare("
            UPDATE {$appointments_table}
            SET status = 'no_show'
            WHERE status = 'confirmed'
            AND start_datetime < DATE_SUB(NOW(), INTERVAL %d MINUTE)
        ", $grace_period));
    }
    
    /**
     * Get email template
     */
    private function get_email_template($type, $data) {
        $templates = $this->get_email_templates();
        
        if (!isset($templates[$type])) {
            return '';
        }
        
        $template = $templates[$type];
        
        // Replace placeholders
        if ($type === 'reminder' && isset($data['appointment'])) {
            $appointment = $data['appointment'];
            
            $replacements = array(
                '{client_name}' => $appointment->first_name . ' ' . $appointment->last_name,
                '{service_name}' => $appointment->service_name,
                '{staff_name}' => $appointment->staff_name,
                '{appointment_date}' => date('l, F j, Y', strtotime($appointment->start_datetime)),
                '{appointment_time}' => date('g:i A', strtotime($appointment->start_datetime)),
                '{hours_before}' => $data['hours_before'],
                '{salon_name}' => get_bloginfo('name'),
            );
        } elseif ($type === 'followup' && isset($data['appointment'])) {
            $appointment = $data['appointment'];
            
            $replacements = array(
                '{client_name}' => $appointment->first_name . ' ' . $appointment->last_name,
                '{service_name}' => $appointment->service_name,
                '{appointment_date}' => date('l, F j, Y', strtotime($appointment->start_datetime)),
                '{salon_name}' => get_bloginfo('name'),
            );
        } elseif ($type === 'birthday' && isset($data['client'])) {
            $client = $data['client'];
            
            $replacements = array(
                '{client_name}' => $client->first_name,
                '{salon_name}' => get_bloginfo('name'),
            );
        }
        
        if (isset($replacements)) {
            $template = str_replace(array_keys($replacements), array_values($replacements), $template);
        }
        
        return $template;
    }
    
    /**
     * Get email templates
     */
    private function get_email_templates() {
        return array(
            'reminder' => '
                <h2>Appointment Reminder</h2>
                <p>Dear {client_name},</p>
                <p>This is a friendly reminder about your upcoming appointment:</p>
                <ul>
                    <li><strong>Service:</strong> {service_name}</li>
                    <li><strong>Stylist:</strong> {staff_name}</li>
                    <li><strong>Date:</strong> {appointment_date}</li>
                    <li><strong>Time:</strong> {appointment_time}</li>
                </ul>
                <p>Please arrive 5 minutes early for your appointment.</p>
                <p>Thank you for choosing {salon_name}!</p>
            ',
            'followup' => '
                <h2>Thank You for Your Visit!</h2>
                <p>Dear {client_name},</p>
                <p>Thank you for visiting {salon_name} for your {service_name} on {appointment_date}.</p>
                <p>We hope you loved the results! Your feedback is important to us.</p>
                <p>Book your next appointment today and receive 10% off your next visit.</p>
                <p>See you soon!</p>
                <p>Best regards,<br>The {salon_name} Team</p>
            ',
            'birthday' => '
                <h2>Happy Birthday {client_name}!</h2>
                <p>Happy Birthday from all of us at {salon_name}!</p>
                <p>To celebrate your special day, we\'d like to offer you:</p>
                <ul>
                    <li>20% off any service</li>
                    <li>Free consultation</li>
                    <li>Gift with any purchase over $50</li>
                </ul>
                <p>Show this email and claim your birthday gift!</p>
                <p>We hope to see you soon!</p>
            ',
        );
    }
    
    /**
     * Log notification
     */
    private function log_notification($appointment_id, $client_id, $type, $channel, $status) {
        global $wpdb;
        
        $wpdb->insert(
            $this->notifications_table,
            array(
                'appointment_id' => $appointment_id,
                'client_id' => $client_id,
                'channel' => $channel,
                'type' => $type,
                'sent_at' => current_time('mysql'),
                'status' => $status
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Send test notification
     */
    public function send_test_notification($email, $type) {
        $client = (object) array(
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email
        );
        
        $appointment = (object) array(
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'service_name' => 'Test Service',
            'staff_name' => 'Test Staff',
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 day'))
        );
        
        switch ($type) {
            case 'reminder':
                $subject = __('Test Reminder Email', BSM_TEXT_DOMAIN);
                $message = $this->get_email_template('reminder', array('appointment' => $appointment, 'hours_before' => 24));
                break;
            case 'followup':
                $subject = __('Test Follow-up Email', BSM_TEXT_DOMAIN);
                $message = $this->get_email_template('followup', array('appointment' => $appointment));
                break;
            case 'birthday':
                $subject = __('Test Birthday Email', BSM_TEXT_DOMAIN);
                $message = $this->get_email_template('birthday', array('client' => $client));
                break;
        }
        
        if (isset($subject) && isset($message)) {
            return wp_mail($email, $subject, $message);
        }
        
        return false;
    }
    
    /**
     * Get notification logs
     */
    public function get_notification_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'type' => '',
            'channel' => '',
            'status' => '',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'n.sent_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'n.sent_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['type'])) {
            $where_conditions[] = 'n.type = %s';
            $where_values[] = $args['type'];
        }
        
        if (!empty($args['channel'])) {
            $where_conditions[] = 'n.channel = %s';
            $where_values[] = $args['channel'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'n.status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT n.*,
                   c.first_name, c.last_name,
                   a.start_datetime
            FROM {$this->notifications_table} n
            LEFT JOIN {$wpdb->prefix}salon_clients c ON n.client_id = c.id
            LEFT JOIN {$wpdb->prefix}salon_appointments a ON n.appointment_id = a.id
            WHERE $where_clause
            ORDER BY n.sent_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($args['limit'], $args['offset']))));
    }
    
    /**
     * AJAX: Test notification
     */
    public function ajax_test_notification() {
        check_ajax_referer('bsm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_salon_settings')) {
            wp_die(__('Insufficient permissions', BSM_TEXT_DOMAIN));
        }
        
        $email = sanitize_email($_POST['email']);
        $type = sanitize_text_field($_POST['type']);
        
        $result = $this->send_test_notification($email, $type);
        
        if ($result) {
            wp_send_json_success(__('Test email sent successfully', BSM_TEXT_DOMAIN));
        } else {
            wp_send_json_error(__('Failed to send test email', BSM_TEXT_DOMAIN));
        }
    }
}
