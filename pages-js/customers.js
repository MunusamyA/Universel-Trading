$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_receive_payment: false,
        page_title: 'Customers',
        page_note: '',
        add_button_label: 'Add Customer',
        add_url: ''
    };

    loadPageContext();

    $('#refreshCustomersBtn').on('click', function () {
        loadCustomers();
    });

    $('#customerStatusFilter, #zoneFilter').on('change', function () {
        loadCustomers();
    });

    $('#customerSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadCustomers, 400);
    });

    $(document).on('click', '.delete-customer-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let customerId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this customer?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadZones();
                    loadCustomers();
                } else {
                    $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addCustomerBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
                $('#addCustomerBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Customers');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addCustomerBtnText').text(pageContext.add_button_label || 'Add Customer');

        if (pageContext.add_url) {
            $('#addCustomerBtn').attr('href', pageContext.add_url);
        }

        if (pageContext.ledger_url && $('#customerLedgerBtn').length) {
            $('#customerLedgerBtn').attr('href', pageContext.ledger_url);
        }

        if (pageContext.can_add) {
            $('#addCustomerBtn').removeClass('d-none');
        } else {
            $('#addCustomerBtn').addClass('d-none');
        }
    }

    function loadCustomers() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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
                    $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load customers.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderCustomerRows(customers) {
        if (!customers || customers.length === 0) {
            $('#customerTableBody').html('<tr><td colspan="11" class="text-center text-muted">No customers found.</td></tr>');
            return;
        }

        let html = '';

        $.each(customers, function (index, customer) {
            let actionHtml = '';

            if (pageContext.can_view || pageContext.can_list) {
                actionHtml += '<a class="btn btn-outline-info btn-sm" href="' + window.BASE_URL + 'pages/customer-view.php?id=' + customer.id + '" title="View Customer Sales"><i class="mdi mdi-eye"></i></a>';
            }

            if (customer.can_receive_payment) {
                actionHtml += '<a class="btn btn-outline-success btn-sm ms-1" href="' + window.BASE_URL + 'pages/customer-payments.php?customer_id=' + customer.id + '" title="Payment"><i class="mdi mdi-cash-plus"></i></a>';
            }

            if (customer.can_edit) {
                actionHtml += '<a class="btn btn-outline-primary btn-sm ms-1" href="' + window.BASE_URL + 'pages/customers-create.php?id=' + customer.id + '" title="Edit"><i class="mdi mdi-pencil"></i></a>';
            }

            if (customer.can_delete) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm ms-1 delete-customer-btn" data-id="' + customer.id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>';
            html += '<h6 class="mb-0">' + escapeHtml(customer.customer_name || '') + '</h6>';
            html += '<small class="text-muted">' + escapeHtml(formatAddress(customer)) + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>' + escapeHtml(customer.zone_name || '-') + '</div>';
            html += '<small class="text-muted">' + escapeHtml(customer.zone_code || '') + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>' + escapeHtml(customer.mobile || '-') + '</div>';
            html += '<small class="text-muted">' + escapeHtml(customer.email || '') + '</small>';
            html += '</td>';
            html += '<td>' + escapeHtml(customer.gst_number || '-') + '</td>';
            html += '<td class="text-end"><strong>' + formatCurrency(customer.opening_balance || 0) + '</strong><br><small class="text-muted">Due: ' + formatCurrency(customer.opening_due || 0) + '</small></td>';
            html += '<td class="text-end"><strong>' + formatCurrency(customer.overall_sales || 0) + '</strong></td>';
            html += '<td class="text-end text-success"><strong>' + formatCurrency(customer.paid_amount || 0) + '</strong></td>';
            html += '<td class="text-end text-danger"><strong>' + formatCurrency(customer.due_amount || 0) + '</strong></td>';
            html += '<td>' + statusBadge(customer.status) + '</td>';
            html += '<td class="text-end">' + actionHtml + '</td>';
            html += '</tr>';
        });

        $('#customerTableBody').html(html);
    }

    function loadZones() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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
                        filterHtml += '<option value="' + zone.id + '">' + escapeHtml(label) + '</option>';
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
        $('#totalOpeningBalanceAmount').text(formatCurrency(stats.total_opening_balance || 0));
        $('#overallSalesAmount').text(formatCurrency(stats.total_overall_sales || 0));
        $('#paidAmount').text(formatCurrency(stats.total_paid_amount || 0));
        $('#dueAmount').text(formatCurrency(stats.total_due_amount || 0));
        $('#totalOutstandingAmount').text(formatCurrency(stats.total_outstanding || stats.total_due_amount || 0));
    }

    function formatAddress(customer) {
        let parts = [];

        if (customer.address) {
            parts.push(customer.address);
        }

        if (customer.city) {
            parts.push(customer.city);
        }

        if (customer.state) {
            parts.push(customer.state);
        }

        if (customer.pincode) {
            parts.push(customer.pincode);
        }

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

        if (typeof showAppToast === 'function') {
            showAppToast(type, message);
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
