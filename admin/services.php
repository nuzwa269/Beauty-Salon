<?php
/**
 * Admin Services Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-services">
    <h1 class="wp-heading-inline"><?php _e('Services', BSM_TEXT_DOMAIN); ?></h1>
    
    <div class="bsm-services-header">
        <button type="button" class="button button-primary" id="new-service">
            <?php _e('Add New Service', BSM_TEXT_DOMAIN); ?>
        </button>
        <button type="button" class="button" id="manage-categories">
            <?php _e('Manage Categories', BSM_TEXT_DOMAIN); ?>
        </button>
    </div>
    
    <!-- Services list will be loaded here via AJAX -->
    <div id="services-list">
        <p><?php _e('Loading services...', BSM_TEXT_DOMAIN); ?></p>
    </div>
</div>
