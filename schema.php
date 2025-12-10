<?php
/**
 * Database Schema for Beauty Salon Manager
 */

class BSM_Database {
    
    public function __construct() {
        // Constructor doesn't automatically create tables
        // Tables are created during plugin activation
    }
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Branches table
        $branches_table = $wpdb->prefix . 'salon_branches';
        $branches_sql = "CREATE TABLE $branches_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text,
            city varchar(100),
            phone varchar(20),
            timezone varchar(50) DEFAULT 'UTC',
            booking_enabled tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Service categories table
        $categories_table = $wpdb->prefix . 'salon_service_categories';
        $categories_sql = "CREATE TABLE $categories_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        // Services table
        $services_table = $wpdb->prefix . 'salon_services';
        $services_sql = "CREATE TABLE $services_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            category_id int(11),
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            base_price decimal(10,2) DEFAULT 0.00,
            base_duration int(11) DEFAULT 60,
            gender varchar(20) DEFAULT 'unisex',
            buffer_before int(11) DEFAULT 0,
            buffer_after int(11) DEFAULT 0,
            tax_rate decimal(5,4) DEFAULT 0.0000,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        // Staff table
        $staff_table = $wpdb->prefix . 'salon_staff';
        $staff_sql = "CREATE TABLE $staff_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) UNSIGNED,
            branch_id int(11),
            name varchar(255) NOT NULL,
            email varchar(255),
            phone varchar(20),
            role varchar(50) DEFAULT 'stylist',
            color_code varchar(7) DEFAULT '#3498db',
            commission_type varchar(20) DEFAULT 'percentage',
            commission_value decimal(5,4) DEFAULT 0.0000,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY branch_id (branch_id)
        ) $charset_collate;";
        
        // Staff services table
        $staff_services_table = $wpdb->prefix . 'salon_staff_services';
        $staff_services_sql = "CREATE TABLE $staff_services_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            staff_id int(11) NOT NULL,
            service_id int(11) NOT NULL,
            custom_duration int(11),
            custom_price decimal(10,2),
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY staff_service (staff_id, service_id),
            KEY staff_id (staff_id),
            KEY service_id (service_id)
        ) $charset_collate;";
        
        // Staff schedule table
        $staff_schedule_table = $wpdb->prefix . 'salon_staff_schedule';
        $staff_schedule_sql = "CREATE TABLE $staff_schedule_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            staff_id int(11) NOT NULL,
            weekday tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            breaks_json text,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY weekday (weekday)
        ) $charset_collate;";
        
        // Staff time off table
        $staff_time_off_table = $wpdb->prefix . 'salon_staff_time_off';
        $staff_time_off_sql = "CREATE TABLE $staff_time_off_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            staff_id int(11) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason varchar(255),
            approved_by bigint(20) UNSIGNED,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY start_datetime (start_datetime)
        ) $charset_collate;";
        
        // Clients table
        $clients_table = $wpdb->prefix . 'salon_clients';
        $clients_sql = "CREATE TABLE $clients_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) UNSIGNED,
            first_name varchar(100) NOT NULL,
            last_name varchar(100),
            email varchar(255),
            phone varchar(20),
            gender varchar(20),
            date_of_birth date,
            notes_internal text,
            notes_public text,
            total_visits int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            last_visit_date date,
            membership_id int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY wp_user_id (wp_user_id),
            KEY email (email),
            KEY phone (phone),
            KEY last_visit_date (last_visit_date)
        ) $charset_collate;";
        
        // Client tags table
        $client_tags_table = $wpdb->prefix . 'salon_client_tags';
        $client_tags_sql = "CREATE TABLE $client_tags_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#3498db',
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Client tag mapping table
        $client_tag_map_table = $wpdb->prefix . 'salon_client_tag_map';
        $client_tag_map_sql = "CREATE TABLE $client_tag_map_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            tag_id int(11) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_tag (client_id, tag_id),
            KEY client_id (client_id),
            KEY tag_id (tag_id)
        ) $charset_collate;";
        
        // Appointments table
        $appointments_table = $wpdb->prefix . 'salon_appointments';
        $appointments_sql = "CREATE TABLE $appointments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            branch_id int(11),
            service_id int(11) NOT NULL,
            staff_id int(11) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            price decimal(10,2) DEFAULT 0.00,
            discount decimal(10,2) DEFAULT 0.00,
            tax decimal(10,2) DEFAULT 0.00,
            total_amount decimal(10,2) DEFAULT 0.00,
            payment_status varchar(20) DEFAULT 'unpaid',
            source varchar(50) DEFAULT 'admin',
            notes text,
            reminder_sent tinyint(1) DEFAULT 0,
            followup_sent tinyint(1) DEFAULT 0,
            tracking_token varchar(32),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY staff_id (staff_id),
            KEY service_id (service_id),
            KEY branch_id (branch_id),
            KEY start_datetime (start_datetime),
            KEY status (status),
            KEY tracking_token (tracking_token)
        ) $charset_collate;";
        
        // Transactions table
        $transactions_table = $wpdb->prefix . 'salon_transactions';
        $transactions_sql = "CREATE TABLE $transactions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            appointment_id int(11),
            client_id int(11),
            branch_id int(11),
            amount decimal(10,2) NOT NULL,
            currency varchar(3) DEFAULT 'USD',
            method varchar(50),
            gateway varchar(50),
            transaction_ref varchar(255),
            gateway_response_json text,
            status varchar(20) DEFAULT 'success',
            paid_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY client_id (client_id),
            KEY branch_id (branch_id),
            KEY transaction_ref (transaction_ref)
        ) $charset_collate;";
        
        // Discounts table
        $discounts_table = $wpdb->prefix . 'salon_discounts';
        $discounts_sql = "CREATE TABLE $discounts_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            type varchar(20) DEFAULT 'percentage',
            value decimal(10,2) NOT NULL,
            max_uses int(11) DEFAULT 0,
            used_count int(11) DEFAULT 0,
            valid_from datetime,
            valid_to datetime,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_table = $wpdb->prefix . 'salon_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            appointment_id int(11),
            client_id int(11),
            channel varchar(20) NOT NULL,
            type varchar(50) NOT NULL,
            subject varchar(255),
            message text,
            sent_at datetime,
            status varchar(20) DEFAULT 'pending',
            response_log text,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY client_id (client_id),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'salon_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            option_key varchar(255) NOT NULL,
            option_value longtext,
            autoload tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY option_key (option_key)
        ) $charset_collate;";
        
        // Appointment logs table
        $appointment_logs_table = $wpdb->prefix . 'salon_appointment_logs';
        $appointment_logs_sql = "CREATE TABLE $appointment_logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            appointment_id int(11) NOT NULL,
            action varchar(50) NOT NULL,
            old_status varchar(20),
            new_status varchar(20),
            notes text,
            performed_by bigint(20) UNSIGNED,
            performed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY performed_at (performed_at)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($branches_sql);
        dbDelta($categories_sql);
        dbDelta($services_sql);
        dbDelta($staff_sql);
        dbDelta($staff_services_sql);
        dbDelta($staff_schedule_sql);
        dbDelta($staff_time_off_sql);
        dbDelta($clients_sql);
        dbDelta($client_tags_sql);
        dbDelta($client_tag_map_sql);
        dbDelta($appointments_sql);
        dbDelta($transactions_sql);
        dbDelta($discounts_sql);
        dbDelta($notifications_sql);
        dbDelta($settings_table);
        dbDelta($appointment_logs_sql);
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'salon_branches',
            $wpdb->prefix . 'salon_service_categories',
            $wpdb->prefix . 'salon_services',
            $wpdb->prefix . 'salon_staff',
            $wpdb->prefix . 'salon_staff_services',
            $wpdb->prefix . 'salon_staff_schedule',
            $wpdb->prefix . 'salon_staff_time_off',
            $wpdb->prefix . 'salon_clients',
            $wpdb->prefix . 'salon_client_tags',
            $wpdb->prefix . 'salon_client_tag_map',
            $wpdb->prefix . 'salon_appointments',
            $wpdb->prefix . 'salon_transactions',
            $wpdb->prefix . 'salon_discounts',
            $wpdb->prefix . 'salon_notifications',
            $wpdb->prefix . 'salon_settings',
            $wpdb->prefix . 'salon_appointment_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}