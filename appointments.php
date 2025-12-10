<?php
/**
 * Admin Appointments Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-appointments">
    <h1 class="wp-heading-inline"><?php _e('Appointments', BSM_TEXT_DOMAIN); ?></h1>
    
    <div class="bsm-appointments-header">
        <button type="button" class="button button-primary" id="new-appointment">
            <?php _e('New Appointment', BSM_TEXT_DOMAIN); ?>
        </button>
    </div>
    
    <!-- Appointments list will be loaded here via AJAX -->
    <div id="appointments-list">
        <p><?php _e('Loading appointments...', BSM_TEXT_DOMAIN); ?></p>
    </div>
</div>

<!-- Appointment Modal -->
<div id="appointment-modal" class="bsm-modal">
    <div class="bsm-modal-content">
        <!-- Blue and Orange Accent Strip -->
        <div class="bsm-modal-header">
            <div class="bsm-accent-stripes">
                <div class="bsm-blue-stripe"></div>
                <div class="bsm-orange-stripe"></div>
            </div>
            <span class="bsm-modal-close">&times;</span>
            <h2><?php _e('نیا اپوائنٹمنٹ شامل کریں', BSM_TEXT_DOMAIN); ?></h2>
        </div>
        
        <form id="new-appointment-form" class="bsm-appointment-form">
            <?php wp_nonce_field('bsm_create_appointment', 'bsm_appointment_nonce'); ?>
            
            <div class="bsm-form-row">
                <div class="bsm-form-group">
                    <label for="client_name"><?php _e('کلائنٹ کا نام', BSM_TEXT_DOMAIN); ?></label>
                    <input type="text" id="client_name" name="client_name" required>
                </div>
                <div class="bsm-form-group">
                    <label for="client_phone"><?php _e('فون نمبر', BSM_TEXT_DOMAIN); ?></label>
                    <input type="tel" id="client_phone" name="client_phone" required>
                </div>
            </div>
            
            <div class="bsm-form-row">
                <div class="bsm-form-group">
                    <label for="service_id"><?php _e('سروس منتخب کریں', BSM_TEXT_DOMAIN); ?></label>
                    <select id="service_id" name="service_id" required>
                        <option value=""><?php _e('سروس منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>
                        <!-- Services will be loaded via AJAX -->
                    </select>
                </div>
                <div class="bsm-form-group">
                    <label for="staff_id"><?php _e('عملہ منتخب کریں', BSM_TEXT_DOMAIN); ?></label>
                    <select id="staff_id" name="staff_id" required>
                        <option value=""><?php _e('عملہ منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>
                        <!-- Staff will be loaded via AJAX -->
                    </select>
                </div>
            </div>
            
            <div class="bsm-form-row">
                <div class="bsm-form-group">
                    <label for="appointment_date"><?php _e('تاریخ', BSM_TEXT_DOMAIN); ?></label>
                    <input type="date" id="appointment_date" name="appointment_date" required>
                </div>
                <div class="bsm-form-group">
                    <label for="appointment_time"><?php _e('وقت', BSM_TEXT_DOMAIN); ?></label>
                    <select id="appointment_time" name="appointment_time" required>
                        <option value=""><?php _e('وقت منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="bsm-form-row">
                <div class="bsm-form-group">
                    <label for="appointment_price"><?php _e('قیمت (Rs.)', BSM_TEXT_DOMAIN); ?></label>
                    <input type="number" id="appointment_price" name="appointment_price" step="0.01" required>
                </div>
                <div class="bsm-form-group">
                    <label for="payment_status"><?php _e('پیمنٹ سٹیٹس', BSM_TEXT_DOMAIN); ?></label>
                    <select id="payment_status" name="payment_status">
                        <option value="pending"><?php _e('معلوم ہونا باقی', BSM_TEXT_DOMAIN); ?></option>
                        <option value="paid"><?php _e('ادا شدہ', BSM_TEXT_DOMAIN); ?></option>
                        <option value="partial"><?php _e('جزوی ادائیگی', BSM_TEXT_DOMAIN); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="bsm-form-group">
                <label for="appointment_notes"><?php _e('نوٹس', BSM_TEXT_DOMAIN); ?></label>
                <textarea id="appointment_notes" name="appointment_notes" rows="3" placeholder="<?php _e('کوئی خاص نوٹس...', BSM_TEXT_DOMAIN); ?>"></textarea>
            </div>
            
            <div class="bsm-modal-footer">
                <button type="button" class="button bsm-btn-cancel"><?php _e('منسوخ کریں', BSM_TEXT_DOMAIN); ?></button>
                <button type="submit" class="button button-primary bsm-btn-submit"><?php _e('اپوائنٹمنٹ محفوظ کریں', BSM_TEXT_DOMAIN); ?></button>
            </div>
        </form>
    </div>
</div>