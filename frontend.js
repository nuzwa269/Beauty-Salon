/**
 * Beauty Salon Manager - Frontend JavaScript
 */

(function($) {
    'use strict';

    var BSMBooking = {
        currentStep: 0,
        steps: [],
        selected: {
            branch: null,
            category: null,
            service: null,
            staff: null,
            date: null,
            time: null,
            client: {}
        },
        
        init: function() {
            this.bindEvents();
            this.loadBranches();
        },
        
        bindEvents: function() {
            // Service selection
            $(document).on('click', '.bsm-service-card', this.selectService);
            
            // Staff selection
            $(document).on('click', '.bsm-staff-card', this.selectStaff);
            
            // Date selection
            $(document).on('change', '.bsm-date-picker', this.selectDate);
            
            // Time slot selection
            $(document).on('click', '.bsm-time-slot', this.selectTime);
            
            // Navigation
            $(document).on('click', '.bsm-next-step', this.nextStep);
            $(document).on('click', '.bsm-prev-step', this.prevStep);
            $(document).on('click', '.bsm-submit-booking', this.submitBooking);
            
            // Form validation
            $(document).on('change', '.bsm-client-form input, .bsm-client-form select', this.validateForm);
        },
        
        loadBranches: function() {
            $.ajax({
                url: bsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bsm_get_branches',
                    nonce: bsm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BSMBooking.populateBranchSelect(response.data);
                    }
                }
            });
        },
        
        populateBranchSelect: function(branches) {
            var $select = $('.bsm-branch-select');
            $select.empty().append('<option value="">Select a branch</option>');
            
            branches.forEach(function(branch) {
                $select.append('<option value="' + branch.id + '">' + branch.name + '</option>');
            });
        },
        
        selectService: function(e) {
            e.preventDefault();
            
            var $card = $(this);
            var serviceId = $card.data('service-id');
            var serviceName = $card.data('service-name');
            var servicePrice = $card.data('service-price');
            var serviceDuration = $card.data('service-duration');
            
            // Remove previous selection
            $('.bsm-service-card').removeClass('selected');
            
            // Add selection to current card
            $card.addClass('selected');
            
            // Store selection
            BSMBooking.selected.service = {
                id: serviceId,
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            };
            
            // Update summary
            BSMBooking.updateSummary();
            
            // Enable next step button
            $('.bsm-next-step').prop('disabled', false);
        },
        
        selectStaff: function(e) {
            e.preventDefault();
            
            var $card = $(this);
            var staffId = $card.data('staff-id');
            var staffName = $card.data('staff-name');
            
            // Remove previous selection
            $('.bsm-staff-card').removeClass('selected');
            
            // Add selection to current card
            $card.addClass('selected');
            
            // Store selection
            BSMBooking.selected.staff = {
                id: staffId,
                name: staffName
            };
            
            // Update summary
            BSMBooking.updateSummary();
            
            // Load available time slots
            BSMBooking.loadTimeSlots();
        },
        
        selectDate: function(e) {
            var selectedDate = $(this).val();
            BSMBooking.selected.date = selectedDate;
            
            if (BSMBooking.selected.service && BSMBooking.selected.staff) {
                BSMBooking.loadTimeSlots();
            }
        },
        
        selectTime: function(e) {
            e.preventDefault();
            
            var $slot = $(this);
            
            if ($slot.hasClass('unavailable')) {
                return;
            }
            
            // Remove previous selection
            $('.bsm-time-slot').removeClass('selected');
            
            // Add selection to current slot
            $slot.addClass('selected');
            
            // Store selection
            BSMBooking.selected.time = $slot.data('time');
            
            // Update summary
            BSMBooking.updateSummary();
            
            // Enable next step button
            $('.bsm-next-step').prop('disabled', false);
        },
        
        loadTimeSlots: function() {
            if (!BSMBooking.selected.date || !BSMBooking.selected.service || !BSMBooking.selected.staff) {
                return;
            }
            
            var $timeSlots = $('.bsm-time-slots');
            $timeSlots.html('<div class="bsm-loading"><div class="bsm-spinner"></div>Loading available times...</div>');
            
            $.ajax({
                url: bsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bsm_get_available_slots',
                    service_id: BSMBooking.selected.service.id,
                    staff_id: BSMBooking.selected.staff.id,
                    date: BSMBooking.selected.date,
                    nonce: bsm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BSMBooking.displayTimeSlots(response.data);
                    } else {
                        $timeSlots.html('<div class="bsm-error">No available slots for this date.</div>');
                    }
                },
                error: function() {
                    $timeSlots.html('<div class="bsm-error">Failed to load available slots.</div>');
                }
            });
        },
        
        displayTimeSlots: function(slots) {
            var $timeSlots = $('.bsm-time-slots');
            $timeSlots.empty();
            
            if (slots.length === 0) {
                $timeSlots.html('<div class="bsm-error">No available slots for this date.</div>');
                return;
            }
            
            slots.forEach(function(slot) {
                var $slot = $('<div class="bsm-time-slot" data-time="' + slot.time + '">' + slot.display + '</div>');
                $timeSlots.append($slot);
            });
        },
        
        nextStep: function(e) {
            e.preventDefault();
            
            var currentStep = $('.bsm-step.active');
            var nextStep = currentStep.next('.bsm-step');
            
            if (nextStep.length) {
                currentStep.removeClass('active');
                nextStep.addClass('active');
                
                // Update progress indicator
                BSMBooking.updateProgress();
                
                // Load data for next step if needed
                if (nextStep.hasClass('bsm-step-staff') && BSMBooking.selected.service) {
                    BSMBooking.loadStaff();
                }
            }
        },
        
        prevStep: function(e) {
            e.preventDefault();
            
            var currentStep = $('.bsm-step.active');
            var prevStep = currentStep.prev('.bsm-step');
            
            if (prevStep.length) {
                currentStep.removeClass('active');
                prevStep.addClass('active');
                
                // Update progress indicator
                BSMBooking.updateProgress();
            }
        },
        
        loadStaff: function() {
            if (!BSMBooking.selected.service) {
                return;
            }
            
            var $staffGrid = $('.bsm-staff-grid');
            $staffGrid.html('<div class="bsm-loading"><div class="bsm-spinner"></div>Loading available staff...</div>');
            
            $.ajax({
                url: bsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bsm_get_staff_for_service',
                    service_id: BSMBooking.selected.service.id,
                    date: BSMBooking.selected.date,
                    nonce: bsm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BSMBooking.displayStaff(response.data);
                    } else {
                        $staffGrid.html('<div class="bsm-error">No staff available for this service.</div>');
                    }
                },
                error: function() {
                    $staffGrid.html('<div class="bsm-error">Failed to load staff.</div>');
                }
            });
        },
        
        displayStaff: function(staff) {
            var $staffGrid = $('.bsm-staff-grid');
            $staffGrid.empty();
            
            if (staff.length === 0) {
                $staffGrid.html('<div class="bsm-error">No staff available for this service.</div>');
                return;
            }
            
            // Add "Any staff" option
            var $anyStaff = $('<div class="bsm-staff-card" data-staff-id="" data-staff-name="Any available staff">' +
                '<div class="bsm-staff-avatar">?</div>' +
                '<div class="bsm-staff-name">Any Available Staff</div>' +
                '<div class="bsm-staff-role">Best match</div>' +
                '</div>');
            $staffGrid.append($anyStaff);
            
            // Add individual staff members
            staff.forEach(function(member) {
                var $staffCard = $('<div class="bsm-staff-card" data-staff-id="' + member.id + '" data-staff-name="' + member.name + '">' +
                    '<div class="bsm-staff-avatar" style="background-color: ' + member.color_code + ';">' +
                    (member.name.charAt(0).toUpperCase()) +
                    '</div>' +
                    '<div class="bsm-staff-name">' + member.name + '</div>' +
                    '<div class="bsm-staff-role">' + member.role + '</div>' +
                    '</div>');
                $staffGrid.append($staffCard);
            });
        },
        
        updateSummary: function() {
            var $summary = $('.bsm-booking-summary');
            var summary = '';
            
            if (BSMBooking.selected.service) {
                summary += '<div class="bsm-summary-item">' +
                    '<span class="bsm-summary-label">Service:</span>' +
                    '<span class="bsm-summary-value">' + BSMBooking.selected.service.name + '</span>' +
                    '</div>';
            }
            
            if (BSMBooking.selected.staff) {
                summary += '<div class="bsm-summary-item">' +
                    '<span class="bsm-summary-label">Staff:</span>' +
                    '<span class="bsm-summary-value">' + BSMBooking.selected.staff.name + '</span>' +
                    '</div>';
            }
            
            if (BSMBooking.selected.date) {
                summary += '<div class="bsm-summary-item">' +
                    '<span class="bsm-summary-label">Date:</span>' +
                    '<span class="bsm-summary-value">' + BSMBooking.formatDate(BSMBooking.selected.date) + '</span>' +
                    '</div>';
            }
            
            if (BSMBooking.selected.time) {
                summary += '<div class="bsm-summary-item">' +
                    '<span class="bsm-summary-label">Time:</span>' +
                    '<span class="bsm-summary-value">' + BSMBooking.selected.time + '</span>' +
                    '</div>';
            }
            
            if (BSMBooking.selected.service && BSMBooking.selected.service.price) {
                summary += '<div class="bsm-summary-item">' +
                    '<span class="bsm-summary-label">Price:</span>' +
                    '<span class="bsm-summary-value">$' + BSMBooking.selected.service.price.toFixed(2) + '</span>' +
                    '</div>';
            }
            
            $summary.html(summary);
        },
        
        validateForm: function() {
            var $form = $('.bsm-client-form');
            var isValid = true;
            
            // Reset previous validation
            $form.find('.bsm-error').remove();
            
            // Required fields
            var requiredFields = ['first_name', 'last_name', 'email', 'phone'];
            requiredFields.forEach(function(fieldName) {
                var $field = $form.find('[name="' + fieldName + '"]');
                if (!$field.val().trim()) {
                    BSMBooking.showFieldError($field, 'This field is required');
                    isValid = false;
                }
            });
            
            // Email validation
            var $email = $form.find('[name="email"]');
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if ($email.val() && !emailPattern.test($email.val())) {
                BSMBooking.showFieldError($email, 'Please enter a valid email address');
                isValid = false;
            }
            
            // Terms acceptance
            if (!$form.find('[name="terms_accepted"]').is(':checked')) {
                BSMBooking.showFieldError($form.find('[name="terms_accepted"]').closest('.bsm-form-group'), 'You must accept the terms and conditions');
                isValid = false;
            }
            
            return isValid;
        },
        
        showFieldError: function($field, message) {
            $field.addClass('error');
            $field.after('<div class="bsm-error">' + message + '</div>');
        },
        
        submitBooking: function(e) {
            e.preventDefault();
            
            if (!BSMBooking.validateForm()) {
                return;
            }
            
            var $submitBtn = $(this);
            var originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Booking...');
            
            // Collect client data
            var $form = $('.bsm-client-form');
            BSMBooking.selected.client = {
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                gender: $form.find('[name="gender"]').val(),
                notes: $form.find('[name="notes"]').val()
            };
            
            $.ajax({
                url: bsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bsm_create_booking',
                    booking_data: {
                        service_id: BSMBooking.selected.service.id,
                        staff_id: BSMBooking.selected.staff.id,
                        date: BSMBooking.selected.date,
                        time: BSMBooking.selected.time,
                        client: BSMBooking.selected.client
                    },
                    nonce: bsm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BSMBooking.showBookingConfirmation(response.data);
                    } else {
                        BSMBooking.showBookingError(response.data);
                    }
                },
                error: function() {
                    BSMBooking.showBookingError('Failed to create booking. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        showBookingConfirmation: function(data) {
            var confirmation = '<div class="bsm-booking-confirmation">' +
                '<h3>Booking Confirmed!</h3>' +
                '<p>Your appointment has been successfully booked.</p>' +
                '<p><strong>Confirmation Number:</strong> ' + data.confirmation_number + '</p>' +
                '<p><strong>Appointment Details:</strong></p>' +
                '<ul>' +
                '<li>Service: ' + data.service_name + '</li>' +
                '<li>Date: ' + data.date + '</li>' +
                '<li>Time: ' + data.time + '</li>' +
                '<li>Staff: ' + data.staff_name + '</li>' +
                '</ul>' +
                '<p>You will receive a confirmation email shortly.</p>' +
                '<a href="' + data.manage_link + '" class="bsm-btn bsm-btn-primary">Manage Appointment</a>' +
                '</div>';
            
            $('.bsm-booking-widget').html(confirmation);
        },
        
        showBookingError: function(message) {
            var error = '<div class="bsm-booking-error">' +
                '<h3>Booking Failed</h3>' +
                '<p>' + message + '</p>' +
                '<button class="bsm-btn bsm-btn-primary" onclick="location.reload()">Try Again</button>' +
                '</div>';
            
            $('.bsm-booking-widget').html(error);
        },
        
        updateProgress: function() {
            var totalSteps = $('.bsm-step').length;
            var currentIndex = $('.bsm-step.active').index();
            var progress = ((currentIndex + 1) / totalSteps) * 100;
            
            $('.bsm-progress-bar').css('width', progress + '%');
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    };
    
    // Client Portal Functions
    var BSMClientPortal = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '.bsm-portal-tab', this.switchTab);
            $(document).on('click', '.bsm-cancel-appointment', this.cancelAppointment);
            $(document).on('click', '.bsm-reschedule-appointment', this.rescheduleAppointment);
        },
        
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.data('tab');
            
            // Update tabs
            $('.bsm-portal-tab').removeClass('active');
            $tab.addClass('active');
            
            // Update content
            $('.bsm-portal-content').removeClass('active');
            $('.bsm-portal-content[data-tab="' + target + '"]').addClass('active');
        },
        
        cancelAppointment: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to cancel this appointment?')) {
                return;
            }
            
            var appointmentId = $(this).data('appointment-id');
            var $button = $(this);
            
            $button.prop('disabled', true).text('Cancelling...');
            
            $.ajax({
                url: bsm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bsm_cancel_appointment',
                    appointment_id: appointmentId,
                    nonce: bsm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to cancel appointment');
                    }
                },
                error: function() {
                    alert('Failed to cancel appointment');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Cancel');
                }
            });
        },
        
        rescheduleAppointment: function(e) {
            e.preventDefault();
            
            // Implementation for rescheduling
            console.log('Reschedule appointment');
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        BSMBooking.init();
        BSMClientPortal.init();
    });
    
    // Expose to global scope for inline event handlers
    window.BSMBooking = BSMBooking;
    window.BSMClientPortal = BSMClientPortal;
    
})(jQuery);