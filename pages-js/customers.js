$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
    let searchTimer = null;

    loadZones();
    loadCustomers();

    $('#addCustomerBtn').on('click', function () {
        resetCustomerForm();
        $('#customerModalTitle').text('Add Customer');
        loadZones();
        customerModal.show();
    });

    $('#refreshCustomersBtn').on('click', function () {
        loadCustomers();
    });

    $('#customerStatusFilter, #zoneFilter').on('change', function () {
        loadCustomers();
    });

    $('#customerSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            loadCustomers();
        }, 400);
    });

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
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
        if (mobile !== '' && !/^[0-9]{10}$/.test(mobile)) {
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
                    customerModal.hide();
                    loadCustomers();
                } else {
                    handleError(response);
                }
                $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
            }
        });
    });

    $(document).on('click', '.edit-customer-btn', function () {
        let customerId = $(this).data('id');
        loadCustomerForEdit(customerId);
    });

    $(document).on('click', '.delete-customer-btn', function () {
        let customerId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this customer?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_customer',
                customer_id: customerId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Customer deleted.');
                    loadCustomers();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    });

    function loadCustomers() {
        $('#customerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_customers',
                search: $('#customerSearch').val(),
                status: $('#customerStatusFilter').val(),
                zone_id: $('#zoneFilter').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderCustomerRows(response.data.customers || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#customerTableBody').html(`<tr><td colspan="8" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load customers.')}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderCustomerRows(customers) {
        if (!customers || customers.length === 0) {
            $('#customerTableBody').html('<tr><td colspan="8" class="text-center text-muted">No customers found.</td></tr>');
            return;
        }

        let html = '';

        $.each(customers, function (index, customer) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <h6 class="mb-0">${escapeHtml(customer.customer_name || '')}</h6>
                        <small class="text-muted">${escapeHtml(formatAddress(customer))}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(customer.zone_name || '-')}</div>
                        <small class="text-muted">${escapeHtml(customer.zone_code || '')}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(customer.mobile || '-')}</div>
                        <small class="text-muted">${escapeHtml(customer.email || '')}</small>
                    </td>
                    <td>${escapeHtml(customer.gst_number || '-')}</td>
                    <td><strong>${formatCurrency(customer.current_outstanding)}</strong></td>
                    <td>${statusBadge(customer.status)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary edit-customer-btn" data-id="${customer.id}" title="Edit">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-customer-btn" data-id="${customer.id}" title="Delete">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#customerTableBody').html(html);
    }

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

                    resetCustomerForm();
                    $('#customerModalTitle').text('Edit Customer');

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
                    $('#status').val(customer.status);

                    loadZones(customer.zone_id);
                    customerModal.show();
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
                    let filterHtml = '<option value="0">All Zones</option>';

                    $.each(zones, function (_, zone) {
                        let label = zone.zone_name + (zone.zone_code ? ' - ' + zone.zone_code : '');
                        let selected = parseInt(zone.id) === parseInt(selectedZoneId || 0) ? 'selected' : '';
                        modalHtml += `<option value="${zone.id}" ${selected}>${escapeHtml(label)}</option>`;
                        filterHtml += `<option value="${zone.id}">${escapeHtml(label)}</option>`;
                    });

                    $('#zone_id').html(modalHtml);
                    $('#zoneFilter').html(filterHtml);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function resetCustomerForm() {
        $('#customerForm')[0].reset();
        $('#customer_id').val('');
        $('#state').val('Tamil Nadu');
        $('#opening_outstanding').val('0.00');
        $('#status').val('1');
        $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
    }

    function renderStats(stats) {
        $('#totalCustomersCount').text(stats.total_customers || 0);
        $('#activeCustomersCount').text(stats.active_customers || 0);
        $('#inactiveCustomersCount').text(stats.inactive_customers || 0);
        $('#totalOutstandingAmount').text(formatCurrency(stats.total_outstanding || 0));
    }

    function formatAddress(customer) {
        let parts = [];
        if (customer.address) parts.push(customer.address);
        if (customer.city) parts.push(customer.city);
        if (customer.state) parts.push(customer.state);
        if (customer.pincode) parts.push(customer.pincode);
        return parts.join(', ');
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);
        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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
