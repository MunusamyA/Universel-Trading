$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let purchaseId = parseInt($('#purchaseId').val() || 0, 10);
    let printMode = parseInt($('#printMode').val() || 0, 10) === 1;
    let initialPrintFormat = String($('#printFormat').val() || 'a4').toLowerCase() === 'a3' ? 'a3' : 'a4';
    let currentPurchase = null;
    let currentItems = [];

    if (purchaseId <= 0) {
        showError('Invalid purchase.');
        return;
    }

    $(document).on('click', '.print-purchase-format-btn', function () {
        printPurchaseReport($(this).data('format'));
    });

    loadPurchaseView();

    function loadPurchaseView() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_purchase',
                purchase_id: purchaseId
            },
            success: function (response) {
                if (response.status === true) {
                    currentPurchase = response.data.purchase || {};
                    currentItems = response.data.items || [];

                    renderPurchase(currentPurchase);
                    renderItems(currentItems);
                    renderPrintReport(currentPurchase, currentItems);

                    $('#purchaseViewAlert').hide();
                    $('#purchaseViewContent').show();

                    if (printMode) {
                        setTimeout(function () {
                            printPurchaseReport(initialPrintFormat);
                        }, 600);
                    }
                } else {
                    showError(response.message || 'Unable to load purchase.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showError('Server error.');
            }
        });
    }

    function printPurchaseReport(format) {
        if (!currentPurchase) {
            showToastSafe('warning', 'Purchase details still loading.');
            return;
        }

        format = format === 'a3' ? 'a3' : 'a4';
        preparePrintFormat(format);
        updatePrintGeneratedAt();
        renderPrintReport(currentPurchase, currentItems);

        setTimeout(function () {
            window.print();
        }, 80);
    }

    function preparePrintFormat(format) {
        format = format === 'a3' ? 'a3' : 'a4';
        let size = format === 'a3' ? 'A3 landscape' : 'A4 landscape';

        $('#purchasePrintPageSize').text('@page { size: ' + size + '; margin: 8mm; }');
        $('#printPurchaseFormatText').text(format.toUpperCase() + ' Landscape');
        $('body').removeClass('purchase-print-a3 purchase-print-a4').addClass('purchase-print-' + format);
    }

    function renderPurchase(purchase) {
        $('#billNoText').text(purchase.bill_no || '-');
        $('#purchaseStatusBadge').html(statusBadge(purchase.status));
        $('#dueAmountTop').text(formatCurrency(purchase.due_amount || 0));
        $('#purchaseDateText').text(formatDate(purchase.purchase_date));
        $('#dueDateText').text(formatDate(purchase.due_date));
        $('#supplierNameText').text(purchase.supplier_name || '-');
        $('#batchNoText').text(purchase.batch_no || '-');
        $('#notesText').text(purchase.notes || '-');

        $('#subTotalText').text(formatCurrency(purchase.sub_total || 0));
        $('#discountText').text(formatCurrency(purchase.discount_amount || 0));
        $('#taxText, #taxSummaryText').text(formatCurrency(purchase.tax_amount || 0));
        $('#roundOffText').text(formatCurrency(purchase.round_off || 0));
        $('#grandTotalText, #grandTotalSummaryText').text(formatCurrency(purchase.grand_total || 0));
        $('#paidText, #paidSummaryText').text(formatCurrency(purchase.paid_amount || 0));
        $('#dueText, #dueSummaryText').text(formatCurrency(purchase.due_amount || 0));
    }

    function renderItems(items) {
        $('#itemCountBadge').text((items || []).length + ' Items');

        if (!items || items.length === 0) {
            $('#itemsTableBody').html('<tr><td colspan="8" class="text-center text-muted">No items found.</td></tr>');
            return;
        }

        let html = '';
        $.each(items, function (index, item) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(item.product_name || '') + '</strong><br><small class="text-muted">' + escapeHtml(item.product_code || '') + '</small></td>';
            html += '<td>' + escapeHtml(item.hsn_code || '-') + '</td>';
            html += '<td class="text-end">' + numberFormat(item.qty || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(item.purchase_price || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(item.discount_amount || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(item.gst_amount || 0) + '</td>';
            html += '<td class="text-end fw-bold">' + formatCurrency(item.line_total || 0) + '</td>';
            html += '</tr>';
        });

        $('#itemsTableBody').html(html);
    }

    function renderPrintReport(purchase, items) {
        purchase = purchase || {};
        items = items || [];

        updatePrintGeneratedAt();
        $('#printPurchaseId').text(purchase.id || purchaseId || '-');
        $('#printBillNo').text(purchase.bill_no || '-');
        $('#printPurchaseDate').text(formatDate(purchase.purchase_date));
        $('#printDueDate').text(formatDate(purchase.due_date));
        $('#printBatchNo').text(purchase.batch_no || '-');
        $('#printPurchaseStatus').text(statusText(purchase.status));
        $('#printSupplierName').text(purchase.supplier_name || '-');
        $('#printNotes').text(purchase.notes || '-');

        $('#printKpiSubTotal').text(formatCurrency(purchase.sub_total || 0));
        $('#printKpiDiscount').text(formatCurrency(purchase.discount_amount || 0));
        $('#printKpiTax').text(formatCurrency(purchase.tax_amount || 0));
        $('#printKpiGrandTotal').text(formatCurrency(purchase.grand_total || 0));
        $('#printKpiPaid').text(formatCurrency(purchase.paid_amount || 0));
        $('#printKpiDue').text(formatCurrency(purchase.due_amount || 0));

        if (!items.length) {
            $('#printItemsTableBody').html('<tr><td colspan="8" class="print-text-center">No items found.</td></tr>');
            return;
        }

        let html = '';
        $.each(items, function (index, item) {
            html += '<tr>';
            html += '<td class="print-text-center">' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(item.product_name || '-') + '</strong><br>' + escapeHtml(item.product_code || '-') + '</td>';
            html += '<td>' + escapeHtml(item.hsn_code || '-') + '</td>';
            html += '<td class="print-text-end">' + numberFormat(item.qty || 0) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(item.purchase_price || 0) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(item.discount_amount || 0) + '</td>';
            html += '<td class="print-text-end">' + formatCurrency(item.gst_amount || 0) + '</td>';
            html += '<td class="print-text-end"><strong>' + formatCurrency(item.line_total || 0) + '</strong></td>';
            html += '</tr>';
        });

        $('#printItemsTableBody').html(html);
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

    function statusText(status) {
        return parseInt(status || 0, 10) === 1 ? 'Active' : 'Cancelled';
    }

    function statusBadge(status) {
        return parseInt(status || 0, 10) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Cancelled</span>';
    }

    function formatCurrency(value) {
        return '₹' + numberFormat(value);
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(date) {
        if (!date) {
            return '-';
        }
        let parts = String(date).split('-');
        if (parts.length === 3) {
            return parts[2] + '-' + parts[1] + '-' + parts[0];
        }
        return escapeHtml(date);
    }

    function showError(message) {
        $('#purchaseViewAlert').removeClass('alert-info').addClass('alert-danger').text(message);
        $('#purchaseViewContent').hide();
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

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
