$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let supplierId = parseInt($('#supplierId').val() || 0);
    let context = {
        can_edit: false,
        can_supplier_payment: false,
        supplier_payment_url: '',
        purchase_view_url: ''
    };
    let currentSupplier = null;
    let currentPurchases = [];

    preparePrintFormat('a4');

    if (supplierId <= 0) {
        showError('Invalid supplier.');
        return;
    }

    $(document).on('click', '.print-supplier-format-btn', function () {
        let format = String($(this).data('format') || 'a4').toLowerCase();
        printSupplierReport(format);
    });

    loadSupplierView();

    function loadSupplierView() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_supplier_view',
                supplier_id: supplierId
            },
            success: function (response) {
                if (response.status === true) {
                    context = response.data.context || context;
                    currentSupplier = response.data.supplier || {};
                    currentPurchases = response.data.purchases || [];

                    renderSupplier(currentSupplier);
                    renderPurchases(currentPurchases);
                    renderPrintReport(currentSupplier, currentPurchases);

                    $('#supplierViewAlert').hide();
                    $('#supplierViewContent').show();
                } else {
                    showError(response.message || 'Unable to load supplier.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showError('Server error.');
            }
        });
    }

    function printSupplierReport(format) {
        if (!currentSupplier) {
            showToastSafe('warning', 'Supplier details still loading.');
            return;
        }

        format = format === 'a3' ? 'a3' : 'a4';
        preparePrintFormat(format);
        updatePrintGeneratedAt();
        renderPrintReport(currentSupplier, currentPurchases);

        setTimeout(function () {
            window.print();
        }, 80);
    }

    function preparePrintFormat(format) {
        format = format === 'a3' ? 'a3' : 'a4';
        let size = format === 'a3' ? 'A3 landscape' : 'A4 landscape';

        $('#supplierPrintPageSize').text('@page { size: ' + size + '; margin: 8mm; }');
        $('#printSupplierFormatText').text(format.toUpperCase() + ' Landscape');
        $('body').removeClass('supplier-print-a3 supplier-print-a4').addClass('supplier-print-' + format);
    }

    function renderSupplier(supplier) {
        let opening = parseFloat(supplier.opening_outstanding || 0);
        let purchaseTotal = parseFloat(supplier.purchase_total || 0);
        let paid = parseFloat(supplier.purchase_paid || 0);
        let due = parseFloat(supplier.total_due || supplier.current_outstanding || 0);

        $('#openingOutstandingCard').text(formatCurrency(opening));
        $('#purchaseAmountCard').text(formatCurrency(purchaseTotal));
        $('#paidAmountCard').text(formatCurrency(paid));
        $('#dueAmountCard').text(formatCurrency(due));

        $('#supplierNameText').text(supplier.supplier_name || '-');
        $('#supplierStatusBadge').html(statusBadge(supplier.status));
        $('#mobileText').text(supplier.mobile || '-');
        $('#emailText').text(supplier.email || '-');
        $('#gstText').text(supplier.gst_number || '-');
        $('#panText').text(supplier.pan_number || '-');
        $('#dlText').text(supplier.dl_number || '-');
        $('#flText').text(supplier.fl_number || '-');
        $('#addressText').text(formatAddress(supplier) || '-');
        $('#bankNameText').text(supplier.bank_name || '-');
        $('#accountNoText').text(supplier.bank_account_no || '-');
        $('#bankBranchText').text(supplier.bank_branch || '-');
        $('#ifscText').text(supplier.bank_ifsc || '-');
        $('#upiText').text(supplier.upi_id || '-');

        let topActions = '';
        if (context.can_supplier_payment) {
            topActions += '<a href="' + supplierPaymentUrl(supplier.id, '') + '" class="btn btn-outline-success btn-sm me-1" title="Supplier Payment"><i class="mdi mdi-cash-multiple me-1"></i> Payment</a>';
        }
        if (context.can_edit) {
            topActions += '<a href="' + window.BASE_URL + 'pages/suppliers.php?edit=' + encodeURIComponent(supplier.id || 0) + '" class="btn btn-outline-primary btn-sm" title="Edit"><i class="mdi mdi-pencil me-1"></i> Edit</a>';
        }
        $('#supplierTopActions').html(topActions);
    }

    function renderPurchases(rows) {
        $('#purchaseCountBadge').text((rows ? rows.length : 0) + ' Purchases');

        if (!rows || rows.length === 0) {
            $('#purchaseTableBody').html('<tr><td colspan="9" class="text-center text-muted">No purchases found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            let purchaseId = parseInt(row.id || 0);
            let dueAmount = parseFloat(row.due_amount || 0);
            let actionHtml = '';

            if (row.can_view) {
                actionHtml += '<a href="' + purchaseViewUrl(purchaseId) + '" class="btn btn-outline-info btn-sm" title="View"><i class="mdi mdi-eye"></i></a>';
            }
            if (row.can_print) {
                actionHtml += '<a href="' + purchaseViewUrl(purchaseId, true) + '" class="btn btn-outline-secondary btn-sm ms-1" title="Print"><i class="mdi mdi-printer"></i></a>';
            }
            if (row.can_supplier_payment && dueAmount > 0) {
                actionHtml += '<a href="' + supplierPaymentUrl(supplierId, purchaseId) + '" class="btn btn-outline-success btn-sm ms-1" title="Pending Payment"><i class="mdi mdi-cash"></i></a>';
            }
            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.bill_no || '') + '</strong><br><small class="text-muted">#' + purchaseId + '</small></td>';
            html += '<td>' + formatDate(row.purchase_date) + '</td>';
            html += '<td>' + parseInt(row.items_count || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.grand_total || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.paid_amount || 0) + '</td>';
            html += '<td class="text-end text-danger fw-bold">' + formatCurrency(row.due_amount || 0) + '</td>';
            html += '<td>' + purchaseStatusBadge(row.status) + '</td>';
            html += '<td class="text-end no-print"><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#purchaseTableBody').html(html);
    }

    function renderPrintReport(supplier, rows) {
        supplier = supplier || {};
        rows = rows || [];

        let status = parseInt(supplier.status || 0) === 1 ? 'Active' : 'Inactive';
        let dlFl = (supplier.dl_number || '-') + ' / ' + (supplier.fl_number || '-');

        updatePrintGeneratedAt();
        $('#printSupplierId').text(supplier.id || supplierId || '-');
        $('#printSupplierName').text(supplier.supplier_name || '-');
        $('#printSupplierMobile').text(supplier.mobile || '-');
        $('#printSupplierEmail').text(supplier.email || '-');
        $('#printSupplierGst').text(supplier.gst_number || '-');
        $('#printSupplierPan').text(supplier.pan_number || '-');
        $('#printSupplierDlFl').text(dlFl);
        $('#printSupplierStatus').text(status);
        $('#printSupplierAddress').text(formatAddress(supplier) || '-');

        $('#printBankName').text(supplier.bank_name || '-');
        $('#printAccountNo').text(supplier.bank_account_no || '-');
        $('#printBankBranch').text(supplier.bank_branch || '-');
        $('#printIfsc').text(supplier.bank_ifsc || '-');
        $('#printUpi').text(supplier.upi_id || '-');

        $('#printKpiOpening').text(formatCurrency(supplier.opening_outstanding || 0));
        $('#printKpiPurchase').text(formatCurrency(supplier.purchase_total || 0));
        $('#printKpiPaid').text(formatCurrency(supplier.purchase_paid || 0));
        $('#printKpiPurchaseDue').text(formatCurrency(supplier.purchase_due || 0));
        $('#printKpiDue').text(formatCurrency(supplier.total_due || supplier.current_outstanding || 0));

        if (!rows.length) {
            $('#printPurchaseTableBody').html('<tr><td colspan="9" class="print-text-center">No purchases found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            html += '<tr>';
            html += '<td class="print-text-center">' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.bill_no || '-') + '</strong><br>#' + parseInt(row.id || 0) + '</td>';
            html += '<td>' + formatDate(row.purchase_date) + '</td>';
            html += '<td class="print-text-center">' + parseInt(row.items_count || 0) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(row.sub_total || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + formatCurrency(row.grand_total || 0) + '</strong></td>';
            html += '<td class="print-text-end">' + formatCurrency(row.paid_amount || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + formatCurrency(row.due_amount || 0) + '</strong></td>';
            html += '<td>' + escapeHtml(purchaseStatusText(row.status)) + (row.notes ? '<br>' + escapeHtml(row.notes) : '') + '</td>';
            html += '</tr>';
        });

        $('#printPurchaseTableBody').html(html);
    }

    function supplierPaymentUrl(supplierId, purchaseId) {
        let url = context.supplier_payment_url || (window.BASE_URL + 'pages/supplier-payments.php');
        let query = '?supplier_id=' + encodeURIComponent(supplierId || 0);
        if (purchaseId) {
            query += '&purchase_id=' + encodeURIComponent(purchaseId);
        }
        return url + query;
    }

    function purchaseViewUrl(purchaseId, printMode) {
        let url = context.purchase_view_url || (window.BASE_URL + 'pages/purchase-view.php');
        return url + '?id=' + encodeURIComponent(purchaseId) + (printMode ? '&print=1' : '');
    }

    function formatAddress(supplier) {
        let parts = [];
        if (supplier.address) parts.push(supplier.address);
        if (supplier.city) parts.push(supplier.city);
        if (supplier.state) parts.push(supplier.state);
        if (supplier.pincode) parts.push(supplier.pincode);
        return parts.join(', ');
    }

    function purchaseStatusText(status) {
        return parseInt(status || 0) === 1 ? 'Active' : 'Cancelled';
    }

    function purchaseStatusBadge(status) {
        return parseInt(status || 0) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Cancelled</span>';
    }

    function statusBadge(status) {
        return parseInt(status || 0) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function formatCurrency(value) {
        return '₹' + parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(date) {
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
        return escapeHtml(date);
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

    function showError(message) {
        $('#supplierViewAlert').removeClass('alert-info').addClass('alert-danger').text(message);
        $('#supplierViewContent').hide();
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
