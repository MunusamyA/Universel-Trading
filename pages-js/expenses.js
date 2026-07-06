$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_cancel: false,
        can_export: false
    };

    loadPageContext();

    $('#refreshExpensesBtn, #filterExpensesBtn').on('click', loadExpenses);
    $('#expenseStatusFilter, #categoryFilter').on('change', loadExpenses);

    $('#expenseSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadExpenses, 400);
    });

    $(document).on('click', '.delete-expense-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let expenseId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this expense?')) return;

        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_expense',
                expense_id: expenseId,
                csrf_token: getCsrfToken()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Expense deleted.');
                    loadExpenses();
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

    $(document).on('click', '.cancel-expense-btn', function () {
        if (!pageContext.can_cancel) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let expenseId = $(this).data('id');
        if (!confirm('Are you sure you want to cancel this expense?')) return;

        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cancel_expense',
                expense_id: expenseId,
                csrf_token: getCsrfToken()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Expense cancelled.');
                    loadExpenses();
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
                    loadCategories();
                    loadExpenses();
                } else {
                    $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addExpenseBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
                $('#addExpenseBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        if (pageContext.can_add) {
            $('#addExpenseBtn').removeClass('d-none');
        } else {
            $('#addExpenseBtn').addClass('d-none');
        }

        if (!pageContext.can_view && !pageContext.can_list) {
            $('#filterExpensesBtn, #refreshExpensesBtn').prop('disabled', true);
        } else {
            $('#filterExpensesBtn, #refreshExpensesBtn').prop('disabled', false);
        }
    }

    function loadExpenses() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_expenses',
                search: $('#expenseSearch').val(),
                status: $('#expenseStatusFilter').val(),
                category_id: $('#categoryFilter').val(),
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderExpenseRows(response.data.expenses || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#expenseTableBody').html(`<tr><td colspan="11" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load expenses.')}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderExpenseRows(expenses) {
        if (!expenses || expenses.length === 0) {
            $('#expenseTableBody').html('<tr><td colspan="11" class="text-center text-muted">No expenses found.</td></tr>');
            return;
        }

        let html = '';
        $.each(expenses, function (index, expense) {
            let actionHtml = '';

            if (expense.can_edit || pageContext.can_edit) {
                actionHtml += `<a href="${window.BASE_URL}pages/expense-form.php?id=${expense.id}" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>`;
            }

            if ((expense.can_cancel || pageContext.can_cancel) && parseInt(expense.status || 0) === 1) {
                actionHtml += `<button type="button" class="btn btn-outline-warning cancel-expense-btn" data-id="${expense.id}" title="Cancel"><i class="mdi mdi-cancel"></i></button>`;
            }

            if (expense.can_delete || pageContext.can_delete) {
                actionHtml += `<button type="button" class="btn btn-outline-danger delete-expense-btn" data-id="${expense.id}" title="Delete"><i class="mdi mdi-delete"></i></button>`;
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            } else {
                actionHtml = '<div class="btn-group btn-group-sm">' + actionHtml + '</div>';
            }

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${formatDate(expense.expense_date)}</td>
                    <td>
                        <h6 class="mb-0">${escapeHtml(expense.expense_no || '')}</h6>
                        <small class="text-muted">${escapeHtml(expense.reference_no || '')}</small>
                    </td>
                    <td>${escapeHtml(expense.category_name || '-')}</td>
                    <td>${escapeHtml(expense.vendor_name || '-')}</td>
                    <td>₹${num(expense.taxable_amount)}</td>
                    <td>₹${num(expense.gst_amount)}</td>
                    <td><strong>₹${num(expense.total_amount)}</strong></td>
                    <td><small>${escapeHtml(expense.split_summary || '-')}</small></td>
                    <td>${statusBadge(expense.status)}</td>
                    <td>${actionHtml}</td>
                </tr>
            `;
        });

        $('#expenseTableBody').html(html);
    }

    function loadCategories() {
        $.ajax({
            url: window.BASE_URL + 'api/expenses.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_categories' },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="0">All Categories</option>';
                    $.each(response.data.categories || [], function (_, category) {
                        html += `<option value="${category.id}">${escapeHtml(category.category_name)}</option>`;
                    });
                    $('#categoryFilter').html(html);
                }
            }
        });
    }

    function renderStats(stats) {
        $('#totalExpensesCount').text(stats.total_expenses || 0);
        $('#activeExpensesCount').text(stats.active_expenses || 0);
        $('#cancelledExpensesCount').text(stats.cancelled_expenses || 0);
        $('#totalExpenseAmount').text(formatCurrency(stats.total_amount || 0));
    }

    function getCsrfToken() {
        return $('input[name="csrf_token"]').first().val() || '';
    }

    function formatDate(date) {
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length !== 3) return escapeHtml(date);
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function num(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatCurrency(value) {
        return '₹' + num(value);
    }

    function statusBadge(status) {
        return parseInt(status) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Cancelled</span>';
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
