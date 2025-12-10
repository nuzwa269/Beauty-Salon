<?php
/**
 * Admin Staff Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-staff">
    <h1 class="wp-heading-inline"><?php _e('Staff', BSM_TEXT_DOMAIN); ?></h1>
    
    <div class="bsm-staff-header">
        <button type="button" class="button button-primary" id="new-staff">
            <?php _e('Add New Staff Member', BSM_TEXT_DOMAIN); ?>
        </button>
    </div>
    
    <!-- Staff list will be loaded here via AJAX -->
    <div id="staff-list">
        <p><?php _e('Loading staff members...', BSM_TEXT_DOMAIN); ?></p>
    </div>
</div>