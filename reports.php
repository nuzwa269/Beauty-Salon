<?php
/**
 * Admin Reports Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-reports">
    <h1 class="wp-heading-inline"><?php _e('Reports', BSM_TEXT_DOMAIN); ?></h1>
    
    <div class="bsm-reports-filters">
        <label for="report-type"><?php _e('Report Type:', BSM_TEXT_DOMAIN); ?></label>
        <select id="report-type">
            <option value="revenue"><?php _e('Revenue Report', BSM_TEXT_DOMAIN); ?></option>
            <option value="appointments"><?php _e('Appointment Statistics', BSM_TEXT_DOMAIN); ?></option>
            <option value="staff"><?php _e('Staff Performance', BSM_TEXT_DOMAIN); ?></option>
            <option value="clients"><?php _e('Client Retention', BSM_TEXT_DOMAIN); ?></option>
        </select>
        
        <label for="date-from"><?php _e('From:', BSM_TEXT_DOMAIN); ?></label>
        <input type="date" id="date-from">
        
        <label for="date-to"><?php _e('To:', BSM_TEXT_DOMAIN); ?></label>
        <input type="date" id="date-to">
        
        <button type="button" class="button" id="generate-report">
            <?php _e('Generate Report', BSM_TEXT_DOMAIN); ?>
        </button>
    </div>
    
    <!-- Report content will be loaded here -->
    <div id="report-content">
        <p><?php _e('Select a report type and date range to generate reports.', BSM_TEXT_DOMAIN); ?></p>
    </div>
</div>