<?php
/**
 * Admin Settings Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bsm-settings">
    <h1 class="wp-heading-inline"><?php _e('Beauty Salon Settings', BSM_TEXT_DOMAIN); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('bsm_settings');
        do_settings_sections('bsm_settings');
        ?>
        
        <div class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', BSM_TEXT_DOMAIN); ?></a>
            <a href="#appointments" class="nav-tab"><?php _e('Appointments', BSM_TEXT_DOMAIN); ?></a>
            <a href="#notifications" class="nav-tab"><?php _e('Notifications', BSM_TEXT_DOMAIN); ?></a>
            <a href="#payments" class="nav-tab"><?php _e('Payments', BSM_TEXT_DOMAIN); ?></a>
        </div>
        
        <div id="general" class="bsm-settings-tab active">
            <h2><?php _e('General Settings', BSM_TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Business Name', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <input type="text" name="bsm_business_name" value="<?php echo esc_attr(get_option('bsm_business_name', '')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your business name', BSM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Business Hours', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <textarea name="bsm_business_hours" rows="5" cols="50"><?php echo esc_textarea(get_option('bsm_business_hours', '')); ?></textarea>
                        <p class="description"><?php _e('Your business operating hours', BSM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="appointments" class="bsm-settings-tab">
            <h2><?php _e('Appointment Settings', BSM_TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Advance Booking Limit', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <input type="number" name="bsm_advance_booking_limit" value="<?php echo esc_attr(get_option('bsm_advance_booking_limit', '30')); ?>" min="1" max="365">
                        <p class="description"><?php _e('Maximum days in advance clients can book appointments', BSM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default Appointment Duration', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <input type="number" name="bsm_default_duration" value="<?php echo esc_attr(get_option('bsm_default_duration', '60')); ?>" min="15" max="480">
                        <p class="description"><?php _e('Default duration in minutes when no specific duration is set', BSM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="notifications" class="bsm-settings-tab">
            <h2><?php _e('Notification Settings', BSM_TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Email Notifications', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsm_email_confirmations" value="1" <?php checked(get_option('bsm_email_confirmations', '1')); ?>>
                            <?php _e('Send appointment confirmation emails', BSM_TEXT_DOMAIN); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="bsm_email_reminders" value="1" <?php checked(get_option('bsm_email_reminders', '1')); ?>>
                            <?php _e('Send reminder emails', BSM_TEXT_DOMAIN); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Reminder Time', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <input type="number" name="bsm_reminder_hours" value="<?php echo esc_attr(get_option('bsm_reminder_hours', '24')); ?>" min="1" max="168">
                        <p class="description"><?php _e('Hours before appointment to send reminder', BSM_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="payments" class="bsm-settings-tab">
            <h2><?php _e('Payment Settings', BSM_TEXT_DOMAIN); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Currency', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <select name="bsm_currency">
                            <option value="USD" <?php selected(get_option('bsm_currency', 'USD'), 'USD'); ?>><?php _e('US Dollar ($)', BSM_TEXT_DOMAIN); ?></option>
                            <option value="EUR" <?php selected(get_option('bsm_currency', 'USD'), 'EUR'); ?>><?php _e('Euro (€)', BSM_TEXT_DOMAIN); ?></option>
                            <option value="GBP" <?php selected(get_option('bsm_currency', 'USD'), 'GBP'); ?>><?php _e('British Pound (£)', BSM_TEXT_DOMAIN); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Payment Methods', BSM_TEXT_DOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bsm_accept_cash" value="1" <?php checked(get_option('bsm_accept_cash', '1')); ?>>
                            <?php _e('Accept Cash', BSM_TEXT_DOMAIN); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="bsm_accept_card" value="1" <?php checked(get_option('bsm_accept_card', '1')); ?>>
                            <?php _e('Accept Card', BSM_TEXT_DOMAIN); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>