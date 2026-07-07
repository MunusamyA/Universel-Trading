$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let purchaseId = parseInt($('#purchaseId').val() || 0);
    let printMode = parseInt($('#printMode').val() || 0) === 1;

    if (purchaseId <= 0) {
        showError('Invalid purchase.');
        return;
    }

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
                    renderPurchase(response.data.purchase || {});
                    renderItems(response.data.items || []);
                    $('#purchaseViewAlert').hide();
                    $('#purchaseViewContent').show();

                    if (printMode) {
                        setTimeout(function () { window.print(); }, 600);
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
        $('#taxText').text(formatCurrency(purchase.tax_amount || 0));
        $('#roundOffText').text(formatCurrency(purchase.round_off || 0));
        $('#grandTotalText').text(formatCurrency(purchase.grand_total || 0));
        $('#paidText').text(formatCurrency(purchase.paid_amount || 0));
        $('#dueText').text(formatCurrency(purchase.due_amount || 0));
    }

    function renderItems(items) {
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

    function statusBadge(status) {
        return parseInt(status || 0) === 1
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
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
        return escapeHtml(date);
    }

    function showError(message) {
        $('#purchaseViewAlert').removeClass('alert-info').addClass('alert-danger').text(message);
        $('#purchaseViewContent').hide();
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
