<?php
/**
 * Admin Clients Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-clients">
    <h1 class="wp-heading-inline"><?php _e('Clients', BSM_TEXT_DOMAIN); ?></h1>
    
    <div class="bsm-clients-header">
        <button type="button" class="button button-primary" id="new-client">
            <?php _e('Add New Client', BSM_TEXT_DOMAIN); ?>
        </button>
    </div>
    
    <!-- Clients list will be loaded here via AJAX -->
    <div id="clients-list">
        <p><?php _e('Loading clients...', BSM_TEXT_DOMAIN); ?></p>
    </div>
</div>
