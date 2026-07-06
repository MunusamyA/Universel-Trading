$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let paymentModes = [];
    let suppliers = [];
    let pendingPurchases = [];
    let paymentSplits = [];
    let supplierSummary = {};
    let isEditMode = false;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_cancel: false,
        can_delete: false,
        can_ledger: false,
        can_back: false,
        page_title: 'Supplier Payments',
        page_note: '',
        ledger_url: '',
        back_url: ''
    };

    loadPageContext();

    $('#supplier_id').on('change', function () {
        isEditMode = false;
        applyPageContext();
        loadSupplierData(function () {
            applyPaymentTypeRules(true);
        });
    });

    $('#payment_type').on('change', function () {
        applyPaymentTypeRules(true);
    });

    $('#purchase_id').on('change', function () {
        applyPaymentTypeRules(true);
    });

    $('#total_amount').on('input change', function () {
        protectAmountLimit();
        syncSplitsWithAmount();
    });

    $('#addSplitBtn').on('click', addSplit);

    $(document).on('input change', '.split-calc', function () {
        updateSplitFromRow($(this).closest('tr').data('index'));
    });

    $(document).on('click', '.remove-split-btn', function () {
        let index = parseInt($(this).closest('tr').data('index'));
        paymentSplits.splice(index, 1);
        renderSplits();
    });

    $('#supplierPaymentForm').on('submit', function (e) {
        e.preventDefault();

        let paymentId = parseInt($('#payment_id').val() || 0);

        if (paymentId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (paymentId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (!validateForm()) {
            return;
        }

        $('#payment_splits_json').val(JSON.stringify(paymentSplits));
        $('#savePaymentBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'POST',
            dataType: 'json',
            data: $('#supplierPaymentForm').serialize() + '&action=save_payment',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Payment saved.');
                    resetForm(false);
                    loadSupplierData(function () {
                        applyPaymentTypeRules(true);
                    });
                    loadPayments();
                } else {
                    handleError(response);
                }
                $('#savePaymentBtn').prop('disabled', false).html('Save Payment');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#savePaymentBtn').prop('disabled', false).html('Save Payment');
            }
        });
    });

    $('#resetPaymentBtn').on('click', function () {
        resetForm(true);
    });

    $('#refreshPaymentsBtn, #statusFilter, #fromDate, #toDate').on('click change', loadPayments);

    $(document).on('click', '.edit-payment-btn', function () {
        if (!pageContext.can_edit || $(this).hasClass('disabled')) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        loadPaymentForEdit($(this).data('id'));
    });

    $(document).on('click', '.cancel-payment-btn', function () {
        if (!pageContext.can_cancel || $(this).hasClass('disabled')) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let id = $(this).data('id');
        if (!confirm('Cancel this payment?')) return;
        paymentAction('cancel_payment', id);
    });

    $(document).on('click', '.delete-payment-btn', function () {
        if (!pageContext.can_delete || $(this).hasClass('disabled')) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let id = $(this).data('id');
        if (!confirm('Delete this payment permanently?')) return;
        paymentAction('delete_payment', id);
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadSuppliers();
                    loadPaymentModes();
                } else {
                    $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#paymentEntryCard').addClass('d-none');
                    $('#ledgerBtn').addClass('d-none');
                    $('#backBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
                $('#paymentEntryCard').addClass('d-none');
                $('#ledgerBtn').addClass('d-none');
                $('#backBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Supplier Payments');
        $('#pageNoteText').text(pageContext.page_note || '');

        if (pageContext.ledger_url) {
            let ledgerUrl = pageContext.ledger_url;
            let supplierId = parseInt($('#supplier_id').val() || window.PRE_SUPPLIER_ID || 0);

            if (supplierId > 0) {
                ledgerUrl += '?supplier_id=' + supplierId;
            }

            $('#ledgerBtn').attr('href', ledgerUrl);
        }

        if (pageContext.back_url) {
            $('#backBtn').attr('href', pageContext.back_url);
        }

        if (pageContext.can_ledger) {
            $('#ledgerBtn').removeClass('d-none');
        } else {
            $('#ledgerBtn').addClass('d-none');
        }

        if (pageContext.can_back) {
            $('#backBtn').removeClass('d-none');
        } else {
            $('#backBtn').addClass('d-none');
        }

        if (pageContext.can_add || pageContext.can_edit) {
            $('#paymentEntryCard').removeClass('d-none');
        } else {
            $('#paymentEntryCard').addClass('d-none');
        }
    }

    function loadSuppliers() {
        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_suppliers' },
            success: function (response) {
                let html = '<option value="">Select Supplier</option>';
                if (response.status === true) {
                    suppliers = response.data.suppliers || [];
                    $.each(suppliers, function (_, supplier) {
                        let selected = parseInt(supplier.id) === parseInt(window.PRE_SUPPLIER_ID || 0) ? 'selected' : '';
                        html += `<option value="${supplier.id}" ${selected}>${escapeHtml(supplier.supplier_name || '')}</option>`;
                    });
                }
                $('#supplier_id').html(html);

                if (parseInt(window.PRE_SUPPLIER_ID || 0) > 0) {
                    loadSupplierData(function () {
                        if (parseInt(window.PRE_PURCHASE_ID || 0) > 0) {
                            $('#payment_type').val('2');
                            renderPurchaseBills();
                            $('#purchase_id').val(window.PRE_PURCHASE_ID);
                        }
                        applyPaymentTypeRules(true);
                    });
                } else {
                    applyPaymentTypeRules(true);
                    loadPayments();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadPaymentModes() {
        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_payment_modes' },
            success: function (response) {
                if (response.status === true) {
                    paymentModes = response.data.payment_modes || [];
                    renderSplits();
                    syncSplitsWithAmount();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadSupplierData(callback) {
        let supplierId = parseInt($('#supplier_id').val() || 0);

        if (supplierId <= 0) {
            supplierSummary = {};
            pendingPurchases = [];
            renderSummary({});
            renderPurchaseBills();
            loadPayments();
            if (typeof callback === 'function') callback();
            return;
        }

        let completed = 0;
        function done() {
            completed++;
            if (completed >= 2 && typeof callback === 'function') {
                callback();
            }
        }

        loadSummary(done);
        loadPendingPurchases(done);
        loadPayments();
    }

    function loadSummary(callback) {
        let supplierId = parseInt($('#supplier_id').val() || 0);
        if (supplierId <= 0) {
            renderSummary({});
            if (typeof callback === 'function') callback();
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_supplier_summary',
                supplier_id: supplierId
            },
            success: function (response) {
                if (response.status === true) {
                    supplierSummary = response.data.summary || {};
                    renderSummary(supplierSummary);
                } else {
                    supplierSummary = {};
                    renderSummary({});
                }
                if (typeof callback === 'function') callback();
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                if (typeof callback === 'function') callback();
            }
        });
    }

    function renderSummary(summary) {
        $('#openingDue').text(numberFormat(summary.opening_due || 0));
        $('#purchaseDue').text(numberFormat(summary.purchase_due || 0));
        $('#purchasePaid').text(numberFormat(summary.purchase_paid || 0));
        $('#totalPayable').text(numberFormat(summary.total_payable || 0));
    }

    function loadPendingPurchases(callback) {
        let supplierId = parseInt($('#supplier_id').val() || 0);
        if (supplierId <= 0) {
            pendingPurchases = [];
            renderPurchaseBills();
            if (typeof callback === 'function') callback();
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_pending_purchases',
                supplier_id: supplierId
            },
            success: function (response) {
                pendingPurchases = response.status === true ? (response.data.purchases || []) : [];
                renderPurchaseBills();
                if (typeof callback === 'function') callback();
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                if (typeof callback === 'function') callback();
            }
        });
    }

    function renderPurchaseBills() {
        let selected = $('#purchase_id').val();
        let html = '<option value="">Select Bill</option>';

        $.each(pendingPurchases, function (_, bill) {
            let isSelected = parseInt(bill.id) === parseInt(selected || 0) ? 'selected' : '';
            html += `<option value="${bill.id}" ${isSelected}>${escapeHtml(bill.bill_no || '')} | ${escapeHtml(bill.purchase_date || '')} | Due ₹${numberFormat(bill.due_amount || 0)}</option>`;
        });

        $('#purchase_id').html(html);
    }

    function applyPaymentTypeRules(autoFillAmount) {
        let supplierId = parseInt($('#supplier_id').val() || 0);
        let type = parseInt($('#payment_type').val() || 1);
        let amount = 0;
        let help = '';
        let limitHelp = '';
        let allowSubmit = true;

        if (type === 2) {
            $('#purchaseBillBox').show();
            let bill = getSelectedPurchase();
            amount = bill ? round2(parseFloat(bill.due_amount || 0)) : 0;
            help = 'Individual: selected purchase bill only.';
            limitHelp = bill ? `Selected bill due: ₹${numberFormat(amount)}` : 'Select a pending purchase bill.';
            allowSubmit = !!bill && amount > 0;
        } else {
            $('#purchaseBillBox').hide();
            $('#purchase_id').val('');

            if (type === 3) {
                amount = round2(parseFloat(supplierSummary.opening_due || 0));
                help = 'Opening Outstanding: only suppliers.opening_outstanding due will reduce. Purchase bills will not reduce.';
                limitHelp = `Opening outstanding due: ₹${numberFormat(amount)}`;
                allowSubmit = amount > 0;
            } else {
                amount = round2(parseFloat(supplierSummary.total_payable || 0));
                help = 'Overall: FIFO purchase bill due first, opening outstanding last.';
                limitHelp = `Total payable: ₹${numberFormat(amount)} | Purchase due: ₹${numberFormat(supplierSummary.purchase_due || 0)} | Opening due: ₹${numberFormat(supplierSummary.opening_due || 0)}`;
                allowSubmit = amount > 0;
            }
        }

        $('#paymentTypeHelp').text(help || 'Select payment type.');
        $('#amountLimitHelp').text(supplierId > 0 ? limitHelp : 'Select supplier first.');

        // Protected amount: amount is auto-filled and readonly.
        // This prevents paying excess/wrong amount for overall/opening/individual.
        $('#total_amount').prop('readonly', true);

        if (autoFillAmount || !isEditMode) {
            $('#total_amount').val(amount.toFixed(2));
            paymentSplits = [];
            if (amount > 0) {
                paymentSplits.push({
                    payment_mode_id: paymentModes.length ? parseInt(paymentModes[0].id) : 0,
                    amount: amount,
                    reference_no: ''
                });
            }
        }

        renderSplits();
        renderSplitTotals();

        $('#savePaymentBtn').prop('disabled', supplierId <= 0 || !allowSubmit || (!pageContext.can_add && !pageContext.can_edit));
    }

    function protectAmountLimit() {
        let max = getAllowedAmount();
        let value = round2(parseFloat($('#total_amount').val() || 0));

        if (value > max) {
            $('#total_amount').val(max.toFixed(2));
            showToastSafe('error', 'Amount cannot exceed payable balance.');
        }

        if (value < 0) {
            $('#total_amount').val('0.00');
        }
    }

    function getAllowedAmount() {
        let type = parseInt($('#payment_type').val() || 1);

        if (type === 2) {
            let bill = getSelectedPurchase();
            return bill ? round2(parseFloat(bill.due_amount || 0)) : 0;
        }

        if (type === 3) {
            return round2(parseFloat(supplierSummary.opening_due || 0));
        }

        return round2(parseFloat(supplierSummary.total_payable || 0));
    }

    function loadPayments() {
        if (!pageContext.can_list) {
            $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_payments',
                supplier_id: $('#supplier_id').val(),
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val(),
                status: $('#statusFilter').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderPayments(response.data.payments || []);
                } else {
                    $('#paymentsTableBody').html(`<tr><td colspan="10" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load payments.')}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderPayments(rows) {
        if (!rows || rows.length === 0) {
            $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-muted">No payments found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let isCancelled = parseInt(row.status || 0) === 2;
            let isPurchaseForm = (row.source_type || '') === 'purchase_form';

            let canEditRow = (row.can_edit === true || row.can_edit === 1 || row.can_edit === '1') && !isCancelled && !isPurchaseForm;
            let canCancelRow = (row.can_cancel === true || row.can_cancel === 1 || row.can_cancel === '1') && !isCancelled;
            let canDeleteRow = (row.can_delete === true || row.can_delete === 1 || row.can_delete === '1');

            let actionHtml = '';

            if (canEditRow) {
                actionHtml += '<button type="button" class="btn btn-outline-primary btn-sm edit-payment-btn" data-id="' + row.id + '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
            }

            if (canCancelRow) {
                actionHtml += '<button type="button" class="btn btn-outline-warning btn-sm cancel-payment-btn ms-1" data-id="' + row.id + '" title="Cancel"><i class="mdi mdi-cancel"></i></button>';
            }

            if (canDeleteRow) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm delete-payment-btn ms-1" data-id="' + row.id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><h6 class="mb-0">' + escapeHtml(row.payment_no || '') + '</h6><small class="text-muted">' + escapeHtml(row.source_type || '') + '</small></td>';
            html += '<td>' + escapeHtml(row.payment_date || '') + '</td>';
            html += '<td>' + escapeHtml(row.supplier_name || '') + '</td>';
            html += '<td>' + paymentTypeText(row.payment_type) + '</td>';
            html += '<td>₹' + numberFormat(row.total_amount || 0) + '</td>';
            html += '<td><small>' + escapeHtml(row.split_summary || '-') + '</small></td>';
            html += '<td><small>' + escapeHtml(row.allocation_summary || '-') + '</small></td>';
            html += '<td>' + (isCancelled ? '<span class="badge bg-danger">Cancelled</span>' : '<span class="badge bg-success">Active</span>') + '</td>';
            html += '<td><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#paymentsTableBody').html(html);
    }

    function loadPaymentForEdit(paymentId) {
        if (!pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_payment',
                payment_id: paymentId
            },
            success: function (response) {
                if (response.status === true) {
                    let payment = response.data.payment || {};
                    isEditMode = true;

                    $('#payment_id').val(payment.id || 0);
                    $('#supplier_id').val(payment.supplier_id);
                    $('#payment_date').val(payment.payment_date);
                    $('#payment_type').val(payment.payment_type);
                    $('#total_amount').val(parseFloat(payment.total_amount || 0).toFixed(2));
                    $('#notes').val(payment.notes || '');

                    paymentSplits = [];
                    $.each(response.data.splits || [], function (_, split) {
                        paymentSplits.push({
                            payment_mode_id: parseInt(split.payment_mode_id || 0),
                            amount: parseFloat(split.amount || 0),
                            reference_no: split.reference_no || ''
                        });
                    });

                    loadSupplierData(function () {
                        let alloc = (response.data.allocations || []).find(function (a) {
                            return parseInt(a.allocation_type || 0) === 1 && parseInt(a.purchase_id || 0) > 0;
                        });
                        if (alloc) {
                            $('#purchase_id').val(alloc.purchase_id);
                        }
                        applyPaymentTypeRules(false);
                        renderSplits();
                    });

                    $('html, body').animate({ scrollTop: $('#supplierPaymentForm').offset().top - 80 }, 300);
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

    function paymentAction(action, paymentId) {
        if (action === 'cancel_payment' && !pageContext.can_cancel) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (action === 'delete_payment' && !pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/supplier-payments.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: action,
                payment_id: paymentId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Updated.');
                    loadSupplierData(function () {
                        applyPaymentTypeRules(true);
                    });
                    loadPayments();
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

    function syncSplitsWithAmount() {
        let amount = round2(parseFloat($('#total_amount').val() || 0));

        if (amount <= 0) {
            paymentSplits = [];
            renderSplits();
            return;
        }

        if (paymentSplits.length === 0) {
            paymentSplits.push({
                payment_mode_id: paymentModes.length ? parseInt(paymentModes[0].id) : 0,
                amount: amount,
                reference_no: ''
            });
        } else if (paymentSplits.length === 1) {
            paymentSplits[0].amount = amount;
        }

        renderSplits();
    }

    function addSplit() {
        let amount = round2(parseFloat($('#total_amount').val() || 0));
        if (amount <= 0) {
            return warn('No payable amount available.', '#total_amount');
        }

        let balance = round2(amount - getSplitTotal());
        if (balance < 0) balance = 0;

        paymentSplits.push({
            payment_mode_id: paymentModes.length ? parseInt(paymentModes[0].id) : 0,
            amount: balance,
            reference_no: ''
        });

        renderSplits();
    }

    function renderSplits() {
        let amount = round2(parseFloat($('#total_amount').val() || 0));

        if (amount <= 0 || paymentSplits.length === 0) {
            $('#paymentSplitsBody').html('<tr><td colspan="4" class="text-center text-muted">No payable amount available.</td></tr>');
            renderSplitTotals();
            return;
        }

        let html = '';

        $.each(paymentSplits, function (index, split) {
            html += `
                <tr data-index="${index}">
                    <td>
                        <select class="form-select form-select-sm split-calc split-mode">
                            ${buildPaymentModeOptions(split.payment_mode_id)}
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end split-calc split-amount" value="${parseFloat(split.amount || 0).toFixed(2)}">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm split-calc split-ref" value="${escapeHtml(split.reference_no || '')}">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-split-btn">
                            <i class="mdi mdi-delete"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#paymentSplitsBody').html(html);
        renderSplitTotals();
    }

    function buildPaymentModeOptions(selectedId) {
        let html = '<option value="">Select</option>';
        $.each(paymentModes || [], function (_, mode) {
            let selected = parseInt(mode.id) === parseInt(selectedId || 0) ? 'selected' : '';
            html += `<option value="${mode.id}" ${selected}>${escapeHtml(mode.mode_name || '')}</option>`;
        });
        return html;
    }

    function updateSplitFromRow(index) {
        index = parseInt(index || 0);
        let row = $('#paymentSplitsBody tr[data-index="' + index + '"]');

        if (!paymentSplits[index] || row.length === 0) {
            return;
        }

        paymentSplits[index].payment_mode_id = parseInt(row.find('.split-mode').val() || 0);
        paymentSplits[index].amount = round2(parseFloat(row.find('.split-amount').val() || 0));
        paymentSplits[index].reference_no = row.find('.split-ref').val() || '';

        renderSplitTotals();
    }

    function getSplitTotal() {
        let total = 0;
        $.each(paymentSplits, function (_, split) {
            total += parseFloat(split.amount || 0);
        });
        return round2(total);
    }

    function renderSplitTotals() {
        let amount = round2(parseFloat($('#total_amount').val() || 0));
        let total = getSplitTotal();
        let balance = round2(amount - total);

        $('#splitTotal').text(total.toFixed(2));
        $('#splitBalance').text(balance.toFixed(2));
    }

    function validateForm() {
        let supplierId = parseInt($('#supplier_id').val() || 0);
        let amount = round2(parseFloat($('#total_amount').val() || 0));
        let allowedAmount = getAllowedAmount();
        let paymentType = parseInt($('#payment_type').val() || 1);

        if (supplierId <= 0) {
            return warn('Please select supplier.', '#supplier_id');
        }

        if ($('#payment_date').val() === '') {
            return warn('Please select payment date.', '#payment_date');
        }

        if (paymentType === 2 && parseInt($('#purchase_id').val() || 0) <= 0) {
            return warn('Please select purchase bill.', '#purchase_id');
        }

        if (amount <= 0) {
            return warn('No payable amount available for this payment type.', '#total_amount');
        }

        if (amount > allowedAmount + 0.01) {
            return warn('Amount cannot exceed payable balance.', '#total_amount');
        }

        if (paymentSplits.length === 0) {
            return warn('Please add payment split.', '#total_amount');
        }

        for (let i = 0; i < paymentSplits.length; i++) {
            if (parseInt(paymentSplits[i].payment_mode_id || 0) <= 0) {
                return warn('Please select payment mode in split row ' + (i + 1), '#paymentSplitsBody');
            }

            if (parseFloat(paymentSplits[i].amount || 0) <= 0) {
                return warn('Please enter split amount in row ' + (i + 1), '#paymentSplitsBody');
            }
        }

        let splitTotal = getSplitTotal();
        if (Math.abs(splitTotal - amount) > 0.01) {
            return warn('Split total must match payment amount.', '#total_amount');
        }

        return true;
    }

    function getSelectedPurchase() {
        let purchaseId = parseInt($('#purchase_id').val() || 0);
        return pendingPurchases.find(function (p) {
            return parseInt(p.id || 0) === purchaseId;
        });
    }

    function resetForm(clearSupplier) {
        isEditMode = false;
        $('#payment_id').val('0');
        $('#payment_date').val(new Date().toISOString().slice(0, 10));
        $('#payment_type').val('1');
        $('#purchase_id').val('');
        $('#notes').val('');
        paymentSplits = [];

        if (clearSupplier === true) {
            $('#supplier_id').val('');
            supplierSummary = {};
            pendingPurchases = [];
            renderSummary({});
            renderPurchaseBills();
        }

        applyPaymentTypeRules(true);
    }

    function paymentTypeText(type) {
        type = parseInt(type || 1);
        if (type === 2) {
            return 'Individual';
        }
        if (type === 3) {
            return 'Opening Outstanding';
        }
        return 'Overall';
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function round2(value) {
        return Math.round((parseFloat(value || 0) + Number.EPSILON) * 100) / 100;
    }

    function warn(message, selector) {
        showToastSafe('error', message);
        if (selector) {
            $(selector).focus();
        }
        return false;
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
