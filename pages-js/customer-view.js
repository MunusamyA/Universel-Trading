$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let customerId = parseInt(window.CUSTOMER_ID || 0);
    let pageContext = {
        can_receive_payment: false
    };
    let currentCustomer = null;
    let currentSales = [];

    preparePrintFormat('a4');

    if (customerId <= 0) {
        $('#customerSalesTableBody').html('<tr><td colspan="11" class="text-center text-danger">Invalid customer.</td></tr>');
        showToastSafe('error', 'Invalid customer.');
        return;
    }

    $(document).on('click', '.print-customer-format-btn', function () {
        let format = String($(this).data('format') || 'a4').toLowerCase();
        printCustomerReport(format);
    });

    function printCustomerReport(format) {
        if (!currentCustomer) {
            showToastSafe('warning', 'Customer details still loading.');
            return;
        }

        format = format === 'a3' ? 'a3' : 'a4';
        preparePrintFormat(format);
        updatePrintGeneratedAt();
        renderPrintReport(currentCustomer, currentSales);

        setTimeout(function () {
            window.print();
        }, 80);
    }

    function preparePrintFormat(format) {
        let isA3 = format === 'a3';
        let pageCss = isA3
            ? '@page { size: A3 landscape; margin: 10mm; }'
            : '@page { size: A4 landscape; margin: 8mm; }';

        $('#customerPrintPageSize').text(pageCss);
        $('body')
            .removeClass('customer-print-a3 customer-print-a4')
            .addClass(isA3 ? 'customer-print-a3' : 'customer-print-a4');
        $('#printFormatLabel').text(isA3 ? 'A3 Landscape' : 'A4 Landscape');
    }

    loadCustomerView();

    function loadCustomerView() {
        $('#customerSalesTableBody').html('<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_customer_view',
                customer_id: customerId
            },
            success: function (response) {
                if (response.status === true) {
                    let data = response.data || {};
                    pageContext = data.context || pageContext;
                    currentCustomer = data.customer || {};
                    currentSales = data.sales || [];
                    renderCustomer(currentCustomer);
                    renderSales(currentSales);
                    renderPrintReport(currentCustomer, currentSales);
                } else {
                    handleError(response);
                    $('#customerSalesTableBody').html('<tr><td colspan="11" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load customer view.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerSalesTableBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
                showToastSafe('error', 'Server error while loading customer view.');
            }
        });
    }

    function renderCustomer(customer) {
        let name = customer.customer_name || '-';
        let address = formatAddress(customer) || '-';
        let zone = customer.zone_name || '-';
        let zoneCode = customer.zone_code ? ' (' + customer.zone_code + ')' : '';
        let status = parseInt(customer.status || 0) === 1 ? 'Active' : 'Inactive';

        $('#customerViewTitle').text(name);
        $('#customerViewNote').text('Customer sales and payment details');

        $('#viewOpeningBalance').text(formatCurrency(customer.opening_balance || 0));
        $('#viewOpeningDueText').text('Due: ' + formatCurrency(customer.opening_due || 0));
        $('#viewOverallSales').text(formatCurrency(customer.overall_sales || 0));
        $('#viewPaidAmount').text(formatCurrency(customer.paid_amount || 0));
        $('#viewDueAmount').text(formatCurrency(customer.due_amount || 0));

        $('#summarySalesTotal').text(formatCurrency(customer.overall_sales || 0));
        $('#summarySalesPaid').text(formatCurrency(customer.sales_paid_amount || 0));
        $('#summarySalesDue').text(formatCurrency(customer.sales_due_amount || 0));
        $('#summaryOpeningPaid').text(formatCurrency(customer.opening_paid || 0));
        $('#summaryOpeningDue').text(formatCurrency(customer.opening_due || 0));

        $('#customerProfileBox').html(
            '<div><strong>Name:</strong> ' + escapeHtml(name) + '</div>' +
            '<div><strong>Mobile:</strong> ' + escapeHtml(customer.mobile || '-') + '</div>' +
            '<div><strong>Email:</strong> ' + escapeHtml(customer.email || '-') + '</div>' +
            '<div><strong>GST:</strong> ' + escapeHtml(customer.gst_number || '-') + '</div>' +
            '<div><strong>Zone:</strong> ' + escapeHtml(zone + zoneCode) + '</div>' +
            '<div><strong>Status:</strong> ' + escapeHtml(status) + '</div>' +
            '<div><strong>Address:</strong> ' + escapeHtml(address) + '</div>'
        );

        if (pageContext.can_receive_payment && parseFloat(customer.due_amount || 0) > 0) {
            $('#receiveCustomerPaymentBtn')
                .removeClass('d-none')
                .attr('href', window.BASE_URL + 'pages/customer-payments.php?customer_id=' + encodeURIComponent(customer.id));
        } else {
            $('#receiveCustomerPaymentBtn').addClass('d-none');
        }
    }

    function renderSales(rows) {
        $('#totalSalesCountBadge').text((rows || []).length + ' Sales');

        if (!rows || rows.length === 0) {
            $('#customerSalesTableBody').html('<tr><td colspan="11" class="text-center text-muted">No sales records found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.sales_no || '-') + '</strong><br><span class="badge bg-soft-primary text-primary">' + escapeHtml(row.document_label || documentLabel(row.document_type)) + '</span></td>';
            html += '<td>' + formatDate(row.sales_date) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.sub_total || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.tax_amount || 0) + '</td>';
            html += '<td class="text-end"><strong>' + formatCurrency(row.grand_total || 0) + '</strong></td>';
            html += '<td class="text-end text-success">' + formatCurrency(row.paid_amount || 0) + '</td>';
            html += '<td class="text-end text-danger"><strong>' + formatCurrency(row.due_amount || 0) + '</strong></td>';
            html += '<td>' + paymentBadge(row.payment_status, row.due_amount) + '</td>';
            html += '<td>' + statusBadge(row.status) + '</td>';
            html += '<td class="text-end sales-action-group">' + actionButtons(row) + '</td>';
            html += '</tr>';
        });

        $('#customerSalesTableBody').html(html);
    }

    function renderPrintReport(customer, rows) {
        customer = customer || {};
        rows = rows || [];

        let name = customer.customer_name || '-';
        let address = formatAddress(customer) || '-';
        let zone = customer.zone_name || '-';
        let zoneCode = customer.zone_code ? ' (' + customer.zone_code + ')' : '';
        let status = parseInt(customer.status || 0) === 1 ? 'Active' : 'Inactive';

        updatePrintGeneratedAt();
        $('#printCustomerId').text(customer.id || '-');
        $('#printCustomerName').text(name);
        $('#printCustomerMobile').text(customer.mobile || '-');
        $('#printCustomerEmail').text(customer.email || '-');
        $('#printCustomerGst').text(customer.gst_number || '-');
        $('#printCustomerZone').text(zone + zoneCode);
        $('#printCustomerStatus').text(status);
        $('#printCustomerAddress').text(address);

        $('#printOpeningBalance, #printKpiOpeningBalance').text(formatCurrency(customer.opening_balance || 0));
        $('#printOverallSales, #printKpiOverallSales').text(formatCurrency(customer.overall_sales || 0));
        $('#printPaidAmount, #printKpiPaidAmount').text(formatCurrency(customer.paid_amount || 0));
        $('#printDueAmount, #printKpiDueAmount').text(formatCurrency(customer.due_amount || 0));
        $('#printSalesPaid').text(formatCurrency(customer.sales_paid_amount || 0));
        $('#printSalesDue').text(formatCurrency(customer.sales_due_amount || 0));
        $('#printKpiSalesCount').text(numberFormat(rows.length));

        if (!rows.length) {
            $('#printSalesTableBody').html('<tr><td colspan="11" class="print-text-center">No sales records found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            html += '<tr>';
            html += '<td class="print-text-center">' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.sales_no || '-') + '</strong></td>';
            html += '<td>' + escapeHtml(row.document_label || documentLabel(row.document_type)) + '</td>';
            html += '<td>' + formatDate(row.sales_date) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(row.sub_total || 0) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(row.tax_amount || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + formatCurrency(row.grand_total || 0) + '</strong></td>';
            html += '<td class="print-text-end">' + formatCurrency(row.paid_amount || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + formatCurrency(row.due_amount || 0) + '</strong></td>';
            html += '<td>' + escapeHtml(paymentText(row.payment_status, row.due_amount)) + '</td>';
            html += '<td>' + escapeHtml(statusText(row.status)) + '</td>';
            html += '</tr>';
        });

        $('#printSalesTableBody').html(html);
    }

    function actionButtons(row) {
        let id = parseInt(row.id || 0);
        let due = parseFloat(row.due_amount || 0);
        let html = '';

        if (id <= 0) {
            return '<span class="text-muted">-</span>';
        }

        html += '<a class="btn btn-outline-info btn-sm" href="' + window.BASE_URL + 'pages/sales-view.php?id=' + id + '" title="View"><i class="mdi mdi-eye"></i></a>';
        html += '<a class="btn btn-outline-danger btn-sm" target="_blank" href="' + window.BASE_URL + 'pages/sales-print.php?id=' + id + '&print=1" title="Print"><i class="mdi mdi-printer"></i></a>';

        if (pageContext.can_receive_payment && due > 0 && (parseInt(row.status || 0) === 1 || parseInt(row.status || 0) === 2)) {
            html += '<a class="btn btn-outline-success btn-sm" href="' + window.BASE_URL + 'pages/customer-payments.php?sales_id=' + id + '" title="Pending Payment"><i class="mdi mdi-cash-plus"></i></a>';
        }

        return html;
    }

    function paymentText(paymentStatus, dueAmount) {
        let status = parseInt(paymentStatus || 0);
        let due = parseFloat(dueAmount || 0);

        if (due <= 0 || status === 2) {
            return 'Paid';
        }

        if (status === 1) {
            return 'Part Paid';
        }

        return 'Pending';
    }

    function statusText(status) {
        status = parseInt(status || 0);

        if (status === 1) {
            return 'Active';
        }

        if (status === 2) {
            return 'Completed';
        }

        if (status === 3) {
            return 'Deleted';
        }

        if (status === 4) {
            return 'Cancelled';
        }

        return 'Unknown';
    }

    function paymentBadge(paymentStatus, dueAmount) {
        let status = parseInt(paymentStatus || 0);
        let due = parseFloat(dueAmount || 0);

        if (due <= 0 || status === 2) {
            return '<span class="badge bg-success">Paid</span>';
        }

        if (status === 1) {
            return '<span class="badge bg-warning text-dark">Part Paid</span>';
        }

        return '<span class="badge bg-danger">Pending</span>';
    }

    function statusBadge(status) {
        status = parseInt(status || 0);

        if (status === 1) {
            return '<span class="badge bg-info">Active</span>';
        }

        if (status === 2) {
            return '<span class="badge bg-success">Completed</span>';
        }

        if (status === 3) {
            return '<span class="badge bg-danger">Deleted</span>';
        }

        if (status === 4) {
            return '<span class="badge bg-warning text-dark">Cancelled</span>';
        }

        return '<span class="badge bg-secondary">Unknown</span>';
    }

    function documentLabel(type) {
        type = parseInt(type || 0);
        let labels = {
            1: 'Quotation',
            2: 'Proforma Bill',
            3: 'Sales Bill',
            4: 'Direct Sale',
            5: 'Final Invoice'
        };
        return labels[type] || 'Document';
    }

    function formatAddress(customer) {
        let parts = [];
        if (customer.address) parts.push(customer.address);
        if (customer.city) parts.push(customer.city);
        if (customer.state) parts.push(customer.state);
        if (customer.pincode) parts.push(customer.pincode);
        return parts.join(', ');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }
        let parts = String(value).split('-');
        if (parts.length === 3) {
            return parts[2] + '-' + parts[1] + '-' + parts[0];
        }
        return value;
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);
        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function numberFormat(value) {
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function updatePrintGeneratedAt() {
        let now = new Date();
        $('#printGeneratedAt').text(now.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }));
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
