$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let config = window.CUSTOMER_FORM_CONFIG || {};
    let customerId = parseInt(config.customer_id || $('#customer_id').val() || 0);

    loadZones();

    if (customerId > 0) {
        loadCustomerForEdit(customerId);
    }

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
    });

    $(document).on('input', '#mobile', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    $(document).on('input', '#pincode', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    $('#customerForm').on('submit', function (e) {
        e.preventDefault();

        if ($.trim($('#customer_name').val()) === '') {
            showToastSafe('warning', 'Please enter customer name.');
            $('#customer_name').focus();
            return;
        }

        if ($('#zone_id').val() === '') {
            showToastSafe('warning', 'Please select zone.');
            $('#zone_id').focus();
            return;
        }

        let mobile = $.trim($('#mobile').val());
        if (mobile === '') {
            showToastSafe('warning', 'Please enter mobile number.');
            $('#mobile').focus();
            return;
        }

        if (!/^[0-9]{10}$/.test(mobile)) {
            showToastSafe('warning', 'Please enter valid 10 digit mobile number.');
            $('#mobile').focus();
            return;
        }

        let email = $.trim($('#email').val());
        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showToastSafe('warning', 'Please enter valid email address.');
            $('#email').focus();
            return;
        }

        let pincode = $.trim($('#pincode').val());
        if (pincode !== '' && !/^[0-9]{6}$/.test(pincode)) {
            showToastSafe('warning', 'Please enter valid 6 digit pincode.');
            $('#pincode').focus();
            return;
        }

        let openingOutstanding = parseFloat($('#opening_outstanding').val() || 0);
        if (openingOutstanding < 0) {
            showToastSafe('warning', 'Opening outstanding cannot be negative.');
            $('#opening_outstanding').focus();
            return;
        }

        $('#saveCustomerBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'POST',
            dataType: 'json',
            data: $('#customerForm').serialize() + '&action=save_customer',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Customer saved.');
                    setTimeout(function () {
                        window.location.href = window.BASE_URL + 'pages/customers.php';
                    }, 500);
                } else {
                    handleError(response);
                    $('#saveCustomerBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Customer');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveCustomerBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Customer');
            }
        });
    });

    function loadCustomerForEdit(customerId) {
        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_customer',
                customer_id: customerId
            },
            success: function (response) {
                if (response.status === true) {
                    let customer = response.data.customer;

                    $('#customerPageTitle').text('Edit Customer');
                    $('#customer_id').val(customer.id);
                    $('#customer_name').val(customer.customer_name);
                    $('#mobile').val(customer.mobile);
                    $('#email').val(customer.email);
                    $('#gst_number').val(customer.gst_number);
                    $('#address').val(customer.address);
                    $('#city').val(customer.city);
                    $('#state').val(customer.state || 'Tamil Nadu');
                    $('#pincode').val(customer.pincode);
                    $('#opening_outstanding').val(parseFloat(customer.opening_outstanding || 0).toFixed(2));
                    $('#status1').val(customer.status || 1);

                    loadZones(customer.zone_id);
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    }

    function loadZones(selectedZoneId) {
        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_zones'
            },
            success: function (response) {
                if (response.status === true) {
                    let zones = response.data.zones || [];
                    let modalHtml = '<option value="">Select Zone</option>';

                    $.each(zones, function (_, zone) {
                        let label = zone.zone_name + (zone.zone_code ? ' - ' + zone.zone_code : '');
                        let selected = parseInt(zone.id) === parseInt(selectedZoneId || 0) ? 'selected' : '';
                        modalHtml += `<option value="${zone.id}" ${selected}>${escapeHtml(label)}</option>`;
                    });

                    $('#zone_id').html(modalHtml);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        alert(message);
    }

    function handleError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showToastSafe('error', (response && response.message) ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
