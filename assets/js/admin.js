/**
 * Admin JavaScript for Beauty Salon Manager
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin functionality
    initAdminDashboard();
    initAppointmentManagement();
    initClientManagement();
    initServiceManagement();
    initStaffManagement();
    initSettings();
    
    /**
     * Initialize admin dashboard
     */
    function initAdminDashboard() {
        // Refresh dashboard data
        $('#refresh-dashboard').on('click', function() {
            loadDashboardData();
        });
        
        // Date filter
        $('#dashboard-date').on('change', function() {
            loadDashboardData();
        });
        
        // Branch filter
        $('#dashboard-branch').on('change', function() {
            loadDashboardData();
        });
    }
    
    /**
     * Initialize appointment management
     */
    function initAppointmentManagement() {
        // New appointment button
        $('#new-appointment').on('click', function() {
            showAppointmentModal();
        });
        
        // Refresh appointments list
        $(document).on('click', '#refresh-appointments', function() {
            loadAppointments();
        });
        
        // Modal close events
        $(document).on('click', '.bsm-modal-close, .bsm-btn-cancel', function() {
            hideAppointmentModal();
        });
        
        // Click outside modal to close
        $(document).on('click', '.bsm-modal', function(e) {
            if (e.target === this) {
                hideAppointmentModal();
            }
        });
        
        // Escape key to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#appointment-modal').is(':visible')) {
                hideAppointmentModal();
            }
        });
        
        // Form submission
        $('#new-appointment-form').on('submit', function(e) {
            e.preventDefault();
            submitAppointmentForm();
        });
        
        // Load services when service dropdown changes
        $(document).on('change', '#service_id', function() {
            var serviceId = $(this).val();
            if (serviceId) {
                updateServicePrice(serviceId);
                loadStaffForService(serviceId);
            }
        });
        
        // Load time slots when date changes
        $(document).on('change', '#appointment_date, #staff_id', function() {
            var date = $('#appointment_date').val();
            var staffId = $('#staff_id').val();
            if (date && staffId) {
                loadAvailableTimeSlots(date, staffId);
            }
        });
    }
    
    /**
     * Initialize client management
     */
    function initClientManagement() {
        // New client button
        $('#new-client').on('click', function() {
            showClientModal();
        });
        
        // Search clients
        $('#client-search').on('keyup', function() {
            searchClients($(this).val());
        });
    }
    
    /**
     * Initialize service management
     */
    function initServiceManagement() {
        // New service button
        $('#new-service').on('click', function() {
            showServiceModal();
        });
        
        // Manage categories button
        $('#manage-categories').on('click', function() {
            showCategoryModal();
        });
    }
    
    /**
     * Initialize staff management
     */
    function initStaffManagement() {
        // New staff button
        $('#new-staff').on('click', function() {
            showStaffModal();
        });
    }
    
    /**
     * Initialize settings
     */
    function initSettings() {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.bsm-settings-tab').removeClass('active');
            $($(this).attr('href')).addClass('active');
        });
        
        // Save settings
        $('form').on('submit', function(e) {
            e.preventDefault();
            saveSettings();
        });
    }
    
    /**
     * Load dashboard data
     */
    function loadDashboardData() {
        var date = $('#dashboard-date').val();
        var branch = $('#dashboard-branch').val();
        
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_dashboard_data',
                nonce: bsm_admin_ajax.nonce,
                date: date,
                branch: branch
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            }
        });
    }
    
    /**
     * Load appointments
     */
    function loadAppointments() {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_appointments',
                nonce: bsm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#appointments-list').html(response.data.html);
                }
            }
        });
    }
    
    /**
     * Search clients
     */
    function searchClients(query) {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_search_clients',
                nonce: bsm_admin_ajax.nonce,
                query: query
            },
            success: function(response) {
                if (response.success) {
                    $('#clients-list').html(response.data.html);
                }
            }
        });
    }
    
    /**
     * Save settings
     */
    function saveSettings() {
        var formData = $('form').serialize();
        
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_save_settings',
                nonce: bsm_admin_ajax.nonce,
                settings: formData
            },
            success: function(response) {
                if (response.success) {
                    alert('Settings saved successfully!');
                } else {
                    alert('Error saving settings: ' + response.data);
                }
            }
        });
    }
    
    // Utility functions for modals
    
    /**
     * Show appointment modal
     */
    function showAppointmentModal() {
        // Reset form
        $('#new-appointment-form')[0].reset();
        removeFormValidation();
        
        // Load initial data
        loadServices();
        loadStaff();
        
        // Set minimum date to today
        var today = new Date().toISOString().split('T')[0];
        $('#appointment_date').attr('min', today);
        $('#appointment_date').val(today);
        
        // Show modal
        $('#appointment-modal').fadeIn(300);
        $('body').addClass('bsm-modal-open');
    }
    
    /**
     * Hide appointment modal
     */
    function hideAppointmentModal() {
        $('#appointment-modal').fadeOut(300);
        $('body').removeClass('bsm-modal-open');
        removeFormValidation();
    }
    
    /**
     * Load services into dropdown
     */
    function loadServices() {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_services',
                nonce: bsm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value=""><?php _e('سروس منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>';
                    $.each(response.data.services, function(index, service) {
                        options += '<option value="' + service.id + '" data-price="' + service.price + '">' + service.name + ' - Rs. ' + service.price + '</option>';
                    });
                    $('#service_id').html(options);
                }
            }
        });
    }
    
    /**
     * Load staff into dropdown
     */
    function loadStaff() {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_staff',
                nonce: bsm_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value=""><?php _e('عملہ منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>';
                    $.each(response.data.staff, function(index, staff) {
                        options += '<option value="' + staff.id + '">' + staff.name + ' (' + staff.position + ')</option>';
                    });
                    $('#staff_id').html(options);
                }
            }
        });
    }
    
    /**
     * Load staff for specific service
     */
    function loadStaffForService(serviceId) {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_staff_for_service',
                nonce: bsm_admin_ajax.nonce,
                service_id: serviceId
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value=""><?php _e('عملہ منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>';
                    $.each(response.data.staff, function(index, staff) {
                        options += '<option value="' + staff.id + '">' + staff.name + ' (' + staff.position + ')</option>';
                    });
                    $('#staff_id').html(options);
                }
            }
        });
    }
    
    /**
     * Load available time slots
     */
    function loadAvailableTimeSlots(date, staffId) {
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bsm_get_available_slots',
                nonce: bsm_admin_ajax.nonce,
                date: date,
                staff_id: staffId
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value=""><?php _e('وقت منتخب کریں...', BSM_TEXT_DOMAIN); ?></option>';
                    $.each(response.data.slots, function(index, slot) {
                        options += '<option value="' + slot.time + '">' + slot.display_time + '</option>';
                    });
                    $('#appointment_time').html(options);
                }
            }
        });
    }
    
    /**
     * Update service price when service is selected
     */
    function updateServicePrice(serviceId) {
        var service = $('#service_id option:selected');
        var price = service.data('price');
        if (price) {
            $('#appointment_price').val(price);
        }
    }
    
    /**
     * Submit appointment form
     */
    function submitAppointmentForm() {
        // Validate form
        if (!validateAppointmentForm()) {
            return;
        }
        
        // Show loading state
        var submitBtn = $('.bsm-btn-submit');
        submitBtn.addClass('loading').text('<?php _e('محفوظ کیا جا رہا ہے...', BSM_TEXT_DOMAIN); ?>');
        
        // Prepare form data
        var formData = {
            action: 'bsm_create_appointment',
            nonce: bsm_admin_ajax.nonce,
            client_name: $('#client_name').val(),
            client_phone: $('#client_phone').val(),
            service_id: $('#service_id').val(),
            staff_id: $('#staff_id').val(),
            appointment_date: $('#appointment_date').val(),
            appointment_time: $('#appointment_time').val(),
            appointment_price: $('#appointment_price').val(),
            payment_status: $('#payment_status').val(),
            appointment_notes: $('#appointment_notes').val()
        };
        
        // Submit form
        $.ajax({
            url: bsm_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e('اپوائنٹمنٹ کامیابی سے بنا دیا گیا!', BSM_TEXT_DOMAIN); ?>', 'success');
                    hideAppointmentModal();
                    loadAppointments(); // Refresh appointments list
                } else {
                    showNotification(response.data || '<?php _e('خرابی: اپوائنٹمنٹ بنانے میں مسئلہ ہے', BSM_TEXT_DOMAIN); ?>', 'error');
                }
            },
            error: function() {
                showNotification('<?php _e('خرابی: نیٹ ورک کی خرابی', BSM_TEXT_DOMAIN); ?>', 'error');
            },
            complete: function() {
                submitBtn.removeClass('loading').text('<?php _e('اپوائنٹمنٹ محفوظ کریں', BSM_TEXT_DOMAIN); ?>');
            }
        });
    }
    
    /**
     * Validate appointment form
     */
    function validateAppointmentForm() {
        var isValid = true;
        var requiredFields = ['client_name', 'client_phone', 'service_id', 'staff_id', 'appointment_date', 'appointment_time', 'appointment_price'];
        
        removeFormValidation();
        
        requiredFields.forEach(function(fieldName) {
            var field = $('#' + fieldName);
            var value = field.val().trim();
            
            if (!value) {
                showFieldError(field, '<?php _e('یہ فیلڈ لازمی ہے', BSM_TEXT_DOMAIN); ?>');
                isValid = false;
            }
        });
        
        // Validate phone number
        var phone = $('#client_phone').val().trim();
        if (phone && !/^[0-9+\-\s()]+$/.test(phone)) {
            showFieldError($('#client_phone'), '<?php _e('درست فون نمبر درج کریں', BSM_TEXT_DOMAIN); ?>');
            isValid = false;
        }
        
        // Validate price
        var price = parseFloat($('#appointment_price').val());
        if (isNaN(price) || price <= 0) {
            showFieldError($('#appointment_price'), '<?php _e('درست قیمت درج کریں', BSM_TEXT_DOMAIN); ?>');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Show field validation error
     */
    function showFieldError(field, message) {
        var formGroup = field.closest('.bsm-form-group');
        formGroup.addClass('error');
        
        var errorDiv = formGroup.find('.bsm-error-message');
        if (errorDiv.length === 0) {
            errorDiv = $('<div class="bsm-error-message"></div>');
            formGroup.append(errorDiv);
        }
        errorDiv.text(message);
    }
    
    /**
     * Remove form validation
     */
    function removeFormValidation() {
        $('.bsm-form-group').removeClass('error success');
        $('.bsm-error-message').remove();
        
        // Remove success class after a delay
        setTimeout(function() {
            $('.bsm-form-group').removeClass('success');
        }, 1000);
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type) {
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }
    
    function showClientModal() {
        // Implementation would show client creation modal
        alert('New client modal would open here');
    }
    
    function showServiceModal() {
        // Implementation would show service creation modal
        alert('New service modal would open here');
    }
    
    function showCategoryModal() {
        // Implementation would show category management modal
        alert('Category management modal would open here');
    }
    
    function showStaffModal() {
        // Implementation would show staff creation modal
        alert('New staff modal would open here');
    }
    
    function updateDashboardStats(data) {
        // Implementation would update dashboard statistics
        console.log('Updating dashboard with data:', data);
    }
});
