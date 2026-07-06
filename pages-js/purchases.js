$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_supplier_payment: false,
        can_supplier_ledger: false,
        page_title: 'Purchases',
        page_note: '',
        add_button_label: 'Add Purchase',
        form_url: '',
        supplier_payment_url: '',
        supplier_ledger_url: ''
    };

    loadPageContext();

    $('#refreshPurchasesBtn').on('click', loadPurchases);
    $('#purchaseStatusFilter, #fromDate, #toDate').on('change', loadPurchases);

    $('#purchaseSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadPurchases, 400);
    });

    $(document).on('click', '.delete-purchase-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let purchaseId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this purchase?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_purchase',
                purchase_id: purchaseId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Purchase deleted.');
                    loadPurchases();
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
                    loadPurchases();
                } else {
                    $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#supplierPaymentBtn').addClass('d-none');
                    $('#supplierLedgerBtn').addClass('d-none');
                    $('#addPurchaseBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
                $('#supplierPaymentBtn').addClass('d-none');
                $('#supplierLedgerBtn').addClass('d-none');
                $('#addPurchaseBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Purchases');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addPurchaseBtnText').text(pageContext.add_button_label || 'Add Purchase');

        if (pageContext.form_url) {
            $('#addPurchaseBtn').attr('href', pageContext.form_url);
        }

        if (pageContext.supplier_payment_url) {
            $('#supplierPaymentBtn').attr('href', pageContext.supplier_payment_url);
        }

        if (pageContext.supplier_ledger_url) {
            $('#supplierLedgerBtn').attr('href', pageContext.supplier_ledger_url);
        }

        if (pageContext.can_add) {
            $('#addPurchaseBtn').removeClass('d-none');
        } else {
            $('#addPurchaseBtn').addClass('d-none');
        }

        if (pageContext.can_supplier_payment) {
            $('#supplierPaymentBtn').removeClass('d-none');
        } else {
            $('#supplierPaymentBtn').addClass('d-none');
        }

        if (pageContext.can_supplier_ledger) {
            $('#supplierLedgerBtn').removeClass('d-none');
        } else {
            $('#supplierLedgerBtn').addClass('d-none');
        }
    }

    function loadPurchases() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_purchases',
                search: $('#purchaseSearch').val(),
                status: $('#purchaseStatusFilter').val(),
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderRows(response.data.purchases || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load purchases.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#purchaseTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
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

            let actionHtml = '';

            if (row.can_edit) {
                actionHtml += '<a href="' + window.BASE_URL + 'pages/purchase-form.php?id=' + purchaseId + '" class="btn btn-outline-primary btn-sm" title="Edit"><i class="mdi mdi-pencil"></i></a>';
            }

            if (row.can_supplier_payment && dueAmount > 0 && supplierId > 0) {
                actionHtml += '<a href="' + supplierPaymentUrl(supplierId, purchaseId) + '" class="btn btn-outline-success btn-sm ms-1" title="Supplier Payment"><i class="mdi mdi-cash"></i></a>';
            }

            if (row.can_delete) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm delete-purchase-btn ms-1" data-id="' + purchaseId + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><h6 class="mb-0">' + escapeHtml(row.bill_no || '') + '</h6><small class="text-muted">#' + purchaseId + '</small></td>';
            html += '<td>' + escapeHtml(row.purchase_date || '') + '</td>';
            html += '<td>' + escapeHtml(row.supplier_name || '-') + '</td>';
            html += '<td>' + parseInt(row.items_count || 0) + '</td>';
            html += '<td>₹' + numberFormat(row.grand_total) + '</td>';
            html += '<td>₹' + numberFormat(row.paid_amount) + '</td>';
            html += '<td>₹' + numberFormat(row.due_amount) + '</td>';
            html += '<td>' + statusBadge(row.status) + '</td>';
            html += '<td><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#purchaseTableBody').html(html);
    }

    function supplierPaymentUrl(supplierId, purchaseId) {
        let url = pageContext.supplier_payment_url || (window.BASE_URL + 'pages/supplier-payments.php');
        return url + '?supplier_id=' + supplierId + '&purchase_id=' + purchaseId;
    }

    function renderStats(stats) {
        $('#totalPurchasesCount').text(stats.total_purchases || 0);
        $('#totalAmount').text(numberFormat(stats.total_amount || 0));
        $('#paidAmount').text(numberFormat(stats.paid_amount || 0));
        $('#dueAmount').text(numberFormat(stats.due_amount || 0));
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Cancelled</span>';
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
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

        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

});
