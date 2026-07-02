$(document).ready(function () {
    $('#preloader').fadeOut('slow');
    let searchTimer = null;

    loadPurchases();

    $('#refreshPurchasesBtn').on('click', loadPurchases);
    $('#purchaseStatusFilter, #fromDate, #toDate').on('change', loadPurchases);
    $('#purchaseSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadPurchases, 400);
    });

    $(document).on('click', '.delete-purchase-btn', function () {
        let purchaseId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this purchase?')) return;

        $.ajax({
            url: window.BASE_URL + 'api/purchases.php',
            type: 'POST',
            dataType: 'json',
            data: { action:'delete_purchase', purchase_id:purchaseId, csrf_token:$('input[name="csrf_token"]').first().val() },
            success: function (response) {
                if (response.status === true) { showToastSafe('success', response.message || 'Purchase deleted.'); loadPurchases(); }
                else handleError(response);
            },
            error: function (xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); }
        });
    });

    function loadPurchases() {
        $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');
        $.ajax({
            url: window.BASE_URL + 'api/purchases.php',
            type: 'GET',
            dataType: 'json',
            data: { action:'list_purchases', search:$('#purchaseSearch').val(), status:$('#purchaseStatusFilter').val(), from_date:$('#fromDate').val(), to_date:$('#toDate').val() },
            success: function (response) {
                if (response.status === true) { renderRows(response.data.purchases || []); renderStats(response.data.stats || {}); }
                else $('#purchaseTableBody').html(`<tr><td colspan="10" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load purchases.')}</td></tr>`);
            },
            error: function (xhr) { console.log(xhr.responseText); $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>'); }
        });
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-muted">No purchases found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            let purchaseId = parseInt(row.id || 0);
            let supplierId = parseInt(row.supplier_id || 0);
            let dueAmount = parseFloat(row.due_amount || 0);

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><h6 class="mb-0">${escapeHtml(row.bill_no || '')}</h6><small class="text-muted">#${purchaseId}</small></td>
                    <td>${escapeHtml(row.purchase_date || '')}</td>
                    <td>${escapeHtml(row.supplier_name || '-')}</td>
                    <td>${parseInt(row.items_count || 0)}</td>
                    <td>₹${numberFormat(row.grand_total)}</td>
                    <td>₹${numberFormat(row.paid_amount)}</td>
                    <td>₹${numberFormat(row.due_amount)}</td>
                    <td>${statusBadge(row.status)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="${window.BASE_URL}pages/purchase-form.php?id=${purchaseId}" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>
                            <a href="${window.BASE_URL}pages/supplier-payments.php?supplier_id=${supplierId}&purchase_id=${purchaseId}" class="btn btn-outline-success ${dueAmount <= 0 ? 'disabled' : ''}" title="Supplier Payment"><i class="mdi mdi-cash"></i></a>
                            <button type="button" class="btn btn-outline-danger delete-purchase-btn" data-id="${purchaseId}" title="Delete"><i class="mdi mdi-delete"></i></button>
                        </div>
                    </td>
                </tr>`;
        });
        $('#purchaseTableBody').html(html);
    }

    function renderStats(stats) {
        $('#totalPurchasesCount').text(stats.total_purchases || 0);
        $('#totalAmount').text(numberFormat(stats.total_amount || 0));
        $('#paidAmount').text(numberFormat(stats.paid_amount || 0));
        $('#dueAmount').text(numberFormat(stats.due_amount || 0));
    }
    function statusBadge(status) { return parseInt(status) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Cancelled</span>'; }
    function numberFormat(value) { return parseFloat(value || 0).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
    function showToastSafe(type, message) { if (typeof showToast === 'function') showToast(type, message, 5000); else alert(message); }
    function handleError(response) { if (response && response.redirect) { window.location.href=response.redirect; return; } showToastSafe('error', response && response.message ? response.message : 'Something went wrong.'); }
    function escapeHtml(value) { return $('<div>').text(value === null || value === undefined ? '' : value).html(); }
});
