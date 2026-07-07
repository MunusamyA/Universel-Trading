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

    $(document).on('click', '.view-purchase-btn', function () {
        openPurchaseView($(this).data('id'));
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

            if (pageContext.can_view || pageContext.can_list) {
                actionHtml += '<button type="button" class="btn btn-outline-info btn-sm view-purchase-btn" data-id="' + purchaseId + '" title="View"><i class="mdi mdi-eye"></i></button>';
            }

            if (row.can_edit) {
                actionHtml += '<a href="' + window.BASE_URL + 'pages/purchase-form.php?id=' + purchaseId + '" class="btn btn-outline-primary btn-sm ms-1" title="Edit"><i class="mdi mdi-pencil"></i></a>';
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

    function openPurchaseView(purchaseId) {
        purchaseId = parseInt(purchaseId || 0);
        if (purchaseId <= 0) {
            showToastSafe('error', 'Invalid purchase.');
            return;
        }

        $('#purchaseViewBody').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Loading...</div>');
        showBootstrapModal('purchaseViewModal');

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
                    renderPurchaseView(response.data.purchase || {}, response.data.items || []);
                } else {
                    $('#purchaseViewBody').html('<div class="alert alert-danger mb-0">' + escapeHtml(response.message || 'Unable to load purchase.') + '</div>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#purchaseViewBody').html('<div class="alert alert-danger mb-0">Server error.</div>');
            }
        });
    }

    function renderPurchaseView(purchase, items) {
        let html = '';
        html += '<div class="d-flex align-items-start justify-content-between mb-3">';
        html += '<div><h5 class="mb-1">Bill No: ' + escapeHtml(purchase.bill_no || '-') + '</h5><div class="text-muted">Batch: ' + escapeHtml(purchase.batch_no || '-') + '</div></div>';
        html += statusBadge(purchase.status);
        html += '</div>';

        html += '<div class="row g-3 mb-3">';
        html += viewInfoBox('Purchase Date', escapeHtml(purchase.purchase_date || '-'));
        html += viewInfoBox('Due Date', escapeHtml(purchase.due_date || '-'));
        html += viewInfoBox('Supplier', escapeHtml(purchase.supplier_name || '-'));
        html += viewInfoBox('Items', parseInt(items.length || 0));
        let totalInfo = calculatePurchaseViewTotals(purchase, items);

        html += viewInfoBox('Gross Sub Total', '₹' + numberFormat(totalInfo.gross_sub_total));
        html += viewInfoBox('Scheme Amount', '₹' + numberFormat(totalInfo.scheme_amount));
        html += viewInfoBox('Sub Total', '₹' + numberFormat(totalInfo.sub_total_after_scheme));
        html += viewInfoBox('Tax Amount', '₹' + numberFormat(purchase.tax_amount));
        html += viewInfoBox('Grand Total', '₹' + numberFormat(purchase.grand_total));
        html += viewInfoBox('Paid / Due', '₹' + numberFormat(purchase.paid_amount) + ' / ₹' + numberFormat(purchase.due_amount));
        html += '</div>';

        if (purchase.notes) {
            html += '<div class="alert alert-light border"><strong>Notes:</strong> ' + escapeHtml(purchase.notes) + '</div>';
        }

        html += '<h6 class="mb-2">Purchased Items</h6>';

        if (!items || items.length === 0) {
            html += '<div class="alert alert-warning mb-0">No items found.</div>';
            $('#purchaseViewBody').html(html);
            return;
        }

        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered table-centered mb-0">';
        html += '<thead class="table-light"><tr>';
        html += '<th>#</th><th>Product</th><th>HSN</th><th>Qty</th><th>Stock</th><th>Sold</th><th>Available</th><th>Rate</th><th>GST</th><th>Total</th><th>Expiry</th>';
        html += '</tr></thead><tbody>';

        $.each(items, function (index, item) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(item.product_name || '-') + '</strong><br><small class="text-muted">' + escapeHtml(item.product_code || '') + '</small></td>';
            html += '<td>' + escapeHtml(item.hsn_code || '-') + '</td>';
            html += '<td>' + numberFormat(item.qty) + '</td>';
            html += '<td>' + numberFormat(item.stock_qty) + '</td>';
            html += '<td>' + numberFormat(item.sold_qty) + '</td>';
            html += '<td><span class="badge bg-success">' + numberFormat(item.available_qty) + '</span></td>';
            html += '<td>₹' + numberFormat(item.purchase_price) + '</td>';
            html += '<td>' + numberFormat(item.gst_percentage) + '%</td>';
            html += '<td>₹' + numberFormat(item.line_total) + '</td>';
            html += '<td>' + escapeHtml(item.expiry_date || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        $('#purchaseViewBody').html(html);
    }

    function calculatePurchaseViewTotals(purchase, items) {
        let grossSubTotal = parseFloat(purchase.sub_total || 0);
        let schemeAmount = 0;
        let taxableSubTotal = 0;
        let rateQtySubTotal = 0;

        $.each(items || [], function (_, item) {
            let qty = parseFloat(item.qty || 0);
            let rate = parseFloat(item.purchase_price || 0);
            let taxableAmount = parseFloat(item.taxable_amount || 0);
            let discountAmount = parseFloat(item.discount_amount || 0);

            schemeAmount += discountAmount;
            rateQtySubTotal += (qty * rate);

            if (taxableAmount > 0) {
                taxableSubTotal += taxableAmount;
            }
        });

        /*
         * Sub Total should be AFTER scheme discount.
         * Priority:
         * 1) taxable_amount from purchase_items, because it is already after scheme and before GST.
         * 2) purchase_price * qty, because purchase_price is stored after scheme discount.
         * 3) purchase.sub_total - schemeAmount fallback.
         */
        let subTotalAfterScheme = taxableSubTotal > 0
            ? taxableSubTotal
            : (rateQtySubTotal > 0 ? rateQtySubTotal : (grossSubTotal - schemeAmount));

        if (grossSubTotal <= 0 && subTotalAfterScheme > 0) {
            grossSubTotal = subTotalAfterScheme + schemeAmount;
        }

        if (subTotalAfterScheme < 0) {
            subTotalAfterScheme = 0;
        }

        return {
            gross_sub_total: round2(grossSubTotal),
            scheme_amount: round2(schemeAmount),
            sub_total_after_scheme: round2(subTotalAfterScheme)
        };
    }

    function round2(value) {
        return Math.round((parseFloat(value || 0) + Number.EPSILON) * 100) / 100;
    }

    function viewInfoBox(label, value) {
        return '<div class="col-md-3 col-sm-6"><div class="border rounded p-2 h-100"><small class="text-muted d-block">' + escapeHtml(label) + '</small><div class="fw-semibold">' + value + '</div></div></div>';
    }

    function showBootstrapModal(id) {
        let el = document.getElementById(id);
        if (!el || !window.bootstrap || !bootstrap.Modal) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(el).show();
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
