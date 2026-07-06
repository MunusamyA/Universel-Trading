$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let config = window.EXPENSE_FORM_CONFIG || {};
    let expenseId = parseInt(config.expense_id || $('#expense_id').val() || 0);
    let categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    let paymentModes = [];
    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_cancel: false,
        can_quick_add_category: false
    };
    let readOnlyMode = false;

    loadPageContext();

    $('.amount-field').on('input', recalculateTotals);

    $('#addSplitRowBtn').on('click', function () {
        if (readOnlyMode) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        addSplitRow('', '', '');
    });

    $(document).on('click', '.remove-split-row', function () {
        if (readOnlyMode) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $(this).closest('tr').remove();
        if ($('#splitRowsBody tr').length === 0) {
            addSplitRow('', '', '');
        }
        recalculateSplitTotal();
    });

    $(document).on('input change', '.split-mode, .split-amount, .split-reference', function () {
        recalculateSplitTotal();
    });

    $('#quickAddCategoryBtn').on('click', function () {
        if (!pageContext.can_quick_add_category) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $('#categoryForm')[0].reset();
        categoryModal.show();
    });

    $('#categoryForm').on('submit', function (e) {
        e.preventDefault();

        if (!pageContext.can_quick_add_category) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($.trim($('#quick_category_name').val()) === '') {
            showToastSafe('warning', 'Please enter category name.');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'POST',
            dataType: 'json',
            data: $('#categoryForm').serialize() + '&action=quick_add_category',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Category added.');
                    categoryModal.hide();
                    loadCategories(response.data.id || '');
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

    $('#expenseForm').on('submit', function (e) {
        e.preventDefault();

        if (expenseId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (expenseId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($('#expense_date').val() === '') {
            showToastSafe('warning', 'Please select expense date.');
            $('#expense_date').focus();
            return;
        }

        if ($('#category_id').val() === '') {
            showToastSafe('warning', 'Please select expense category.');
            $('#category_id').focus();
            return;
        }

        let totalAmount = parseFloat($('#total_amount').val() || 0);
        let splitTotal = getSplitTotal();

        if (totalAmount <= 0) {
            showToastSafe('warning', 'Expense total must be greater than zero.');
            return;
        }

        if (Math.abs(totalAmount - splitTotal) > 0.01) {
            showToastSafe('warning', 'Split payment total must match expense total.');
            return;
        }

        let splits = collectSplits();
        if (splits.length === 0) {
            showToastSafe('warning', 'Add at least one payment split.');
            return;
        }

        $('#splitPaymentsJson').val(JSON.stringify(splits));

        $('#saveExpenseBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'POST',
            dataType: 'json',
            data: $('#expenseForm').serialize() + '&action=save_expense',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Expense saved.');
                    setTimeout(function () {
                        window.location.href = response.data.redirect || (window.BASE_URL + 'pages/expenses.php');
                    }, 500);
                } else {
                    handleError(response);
                    $('#saveExpenseBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Expense');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveExpenseBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Expense');
            }
        });
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();

                    $('#expense_date').val(currentDate());

                    loadCategories();
                    loadPaymentModes(function () {
                        if (expenseId > 0) {
                            loadExpense(expenseId);
                        } else {
                            addSplitRow('', '', '');
                            recalculateTotals();
                        }
                    });
                } else {
                    handleError(response);
                    disableWholeForm();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                disableWholeForm();
            }
        });
    }

    function applyPageContext() {
        if (expenseId > 0) {
            readOnlyMode = !pageContext.can_edit;

            if (!pageContext.can_edit) {
                $('#saveExpenseBtn').addClass('d-none');
            }
        } else {
            readOnlyMode = !pageContext.can_add;

            if (!pageContext.can_add) {
                $('#saveExpenseBtn').addClass('d-none');
                disableWholeForm();
            }
        }

        if (pageContext.can_quick_add_category) {
            $('#quickAddCategoryBtn').removeClass('d-none');
        } else {
            $('#quickAddCategoryBtn').addClass('d-none');
        }

        if (readOnlyMode) {
            $('#expenseForm').find('input, select, textarea').not('#expense_id').prop('disabled', true);
            $('#addSplitRowBtn').prop('disabled', true);
        }
    }

    function disableWholeForm() {
        $('#expenseForm').find('input, select, textarea, button').prop('disabled', true);
        $('#saveExpenseBtn').addClass('d-none');
        $('#quickAddCategoryBtn').addClass('d-none');
    }

    function loadExpense(expenseId) {
        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_expense',
                expense_id: expenseId
            },
            success: function (response) {
                if (response.status === true) {
                    let expense = response.data.expense || {};
                    $('#expensePageTitle').text(pageContext.can_edit ? 'Edit Expense' : 'View Expense');
                    $('#expense_id').val(expense.id || '');
                    $('#expense_date').val(expense.expense_date || currentDate());
                    $('#vendor_name').val(expense.vendor_name || '');
                    $('#reference_no').val(expense.reference_no || '');
                    $('#taxable_amount').val(numPlain(expense.taxable_amount || 0));
                    $('#gst_amount').val(numPlain(expense.gst_amount || 0));
                    $('#notes').val(expense.notes || '');
                    $('#status1').val(expense.status || 1);
                    loadCategories(expense.category_id || '');

                    $('#splitRowsBody').html('');
                    let splits = response.data.splits || [];
                    if (splits.length > 0) {
                        $.each(splits, function (_, split) {
                            addSplitRow(split.payment_mode_id || '', split.amount || '', split.reference_no || '');
                        });
                    } else {
                        addSplitRow('', expense.total_amount || '', '');
                    }

                    recalculateTotals();
                    recalculateSplitTotal();

                    if (readOnlyMode) {
                        $('#expenseForm').find('input, select, textarea').not('#expense_id').prop('disabled', true);
                        $('#addSplitRowBtn').prop('disabled', true);
                        $('.remove-split-row').prop('disabled', true);
                    }
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

    function loadCategories(selectedId) {
        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_categories' },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="">Select Category</option>';
                    $.each(response.data.categories || [], function (_, category) {
                        let selected = parseInt(category.id) === parseInt(selectedId || 0) ? 'selected' : '';
                        html += `<option value="${category.id}" ${selected}>${escapeHtml(category.category_name)}</option>`;
                    });
                    $('#category_id').html(html);

                    if (readOnlyMode) {
                        $('#category_id').prop('disabled', true);
                    }
                }
            }
        });
    }

    function loadPaymentModes(callback) {
        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_payment_modes' },
            success: function (response) {
                if (response.status === true) {
                    paymentModes = response.data.payment_modes || [];
                }
                if (callback) callback();
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                if (callback) callback();
            }
        });
    }

    function addSplitRow(modeId, amount, referenceNo) {
        let modeHtml = '<option value="">Select Mode</option>';
        $.each(paymentModes, function (_, mode) {
            let selected = parseInt(mode.id) === parseInt(modeId || 0) ? 'selected' : '';
            modeHtml += `<option value="${mode.id}" ${selected}>${escapeHtml(mode.mode_name)}</option>`;
        });

        let disabledAttr = readOnlyMode ? 'disabled' : '';

        let html = `
            <tr>
                <td><select class="form-select split-mode" ${disabledAttr}>${modeHtml}</select></td>
                <td><input type="number" step="0.01" min="0" class="form-control split-amount" value="${amount !== '' ? numPlain(amount) : ''}" ${disabledAttr}></td>
                <td><input type="text" class="form-control split-reference" value="${escapeHtml(referenceNo || '')}" placeholder="Reference" ${disabledAttr}></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-split-row" ${disabledAttr}><i class="mdi mdi-delete"></i></button></td>
            </tr>
        `;
        $('#splitRowsBody').append(html);
        recalculateSplitTotal();
    }

    function collectSplits() {
        let splits = [];
        $('#splitRowsBody tr').each(function () {
            let modeId = parseInt($(this).find('.split-mode').val() || 0);
            let amount = parseFloat($(this).find('.split-amount').val() || 0);
            let reference = $.trim($(this).find('.split-reference').val() || '');

            if (modeId > 0 && amount > 0) {
                splits.push({
                    payment_mode_id: modeId,
                    amount: amount,
                    reference_no: reference
                });
            }
        });
        return splits;
    }

    function recalculateTotals() {
        let taxable = parseFloat($('#taxable_amount').val() || 0);
        let gst = parseFloat($('#gst_amount').val() || 0);
        let total = taxable + gst;
        $('#total_amount').val(numPlain(total));
    }

    function recalculateSplitTotal() {
        $('#splitTotalAmount').val(numPlain(getSplitTotal()));
    }

    function getSplitTotal() {
        let total = 0;
        $('.split-amount').each(function () {
            total += parseFloat($(this).val() || 0);
        });
        return total;
    }

    function currentDate() {
        let d = new Date();
        return d.toISOString().slice(0, 10);
    }

    function numPlain(value) {
        return parseFloat(value || 0).toFixed(2);
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') showToast(type, message, 5000);
        else alert(message);
    }

    function handleError(response) {
        if (response && response.redirect) { window.location.href = response.redirect; return; }
        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
