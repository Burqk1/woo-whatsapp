/**
 * Woo WhatsApp - Cart Tracking Script
 * Checkout sayfasında e-posta ve telefon bilgilerini yakalar
 */
(function($) {
    'use strict';

    var debounceTimer;

    $(document).on('change blur', '#billing_email, input[name="billing_email"]', function() {
        captureGuestData();
    });

    $(document).on('change blur', '#billing_phone, input[name="billing_phone"]', function() {
        captureGuestData();
    });

    $(document).on('change blur', '#billing_first_name, #billing_last_name', function() {
        captureGuestData();
    });

    $(document.body).on('updated_checkout', function() {
        captureGuestData();
    });

    function captureGuestData() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            var email = $('#billing_email').val() || $('input[name="billing_email"]').val();
            var phone = $('#billing_phone').val() || $('input[name="billing_phone"]').val();
            var firstName = $('#billing_first_name').val() || '';
            var lastName = $('#billing_last_name').val() || '';
            var customerName = (firstName + ' ' + lastName).trim();

            if (!email && !phone) {
                return;
            }

            $.ajax({
                url: wwaCartTracking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wwa_capture_guest_data',
                    nonce: wwaCartTracking.nonce,
                    email: email,
                    phone: phone,
                    customer_name: customerName
                },
                success: function(response) {
                    if (response.success) {
                        console.log('WWA: Cart data captured');
                    }
                },
                error: function() {
                    console.log('WWA: Failed to capture cart data');
                }
            });
        }, 1000); 
    }

    $(document).ready(function() {
        if ($('#billing_email').val() || $('#billing_phone').val()) {
            setTimeout(captureGuestData, 2000);
        }
    });

})(jQuery);
