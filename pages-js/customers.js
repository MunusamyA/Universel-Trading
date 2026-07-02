$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    loadZones();
    loadCustomers();

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
                    <td class="text-end"><strong>${formatCurrency(customer.current_outstanding)}</strong></td>
                    <td>${statusBadge(customer.status)}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-success" href="${window.BASE_URL}pages/customer-payments.php?customer_id=${customer.id}" title="Payment">
                                <i class="mdi mdi-cash-plus"></i>
                            </a>
                            <a class="btn btn-outline-primary" href="${window.BASE_URL}pages/customers-create.php?id=${customer.id}" title="Edit">
                                <i class="mdi mdi-pencil"></i>
                            </a>
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

    function loadZones() {
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
                    let filterHtml = '<option value="0">All Zones</option>';

                    $.each(zones, function (_, zone) {
                        let label = zone.zone_name + (zone.zone_code ? ' - ' + zone.zone_code : '');
                        filterHtml += `<option value="${zone.id}">${escapeHtml(label)}</option>`;
                    });

                    $('#zoneFilter').html(filterHtml);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
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
