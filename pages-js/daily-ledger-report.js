$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    loadDailyLedgerReport();

    $('#loadReportBtn, #fromDate, #toDate').on('click change', loadDailyLedgerReport);

    $(document).on('change', '.ledger-type-check', function () {
        syncTypeCheckboxes($(this));
        loadDailyLedgerReport();
    });

    $('#reportSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadDailyLedgerReport, 400);
    });

    function loadDailyLedgerReport() {
        $('#ledgerReportBody').html('<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>');
        $('#dateSummaryBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/daily-ledger-report.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_report',
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val(),
                entry_types: getSelectedTypes().join(','),
                search: $('#reportSearch').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderSummary(response.data.summary || {});
                    renderDateSummary(response.data.date_summary || []);
                    renderRows(response.data.rows || []);
                    renderPeriod(response.data.filters || {});
                } else {
                    $('#ledgerReportBody').html(`<tr><td colspan="11" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load report.')}</td></tr>`);
                    $('#dateSummaryBody').html('<tr><td colspan="8" class="text-center text-muted">No data.</td></tr>');
                    showToastSafe('error', response.message || 'Unable to load report.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerReportBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
                $('#dateSummaryBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderSummary(summary) {
        $('#entryCount').text(summary.entry_count || 0);
        $('#salesTotal').text(numberFormat(summary.sales_total || 0));
        $('#customerPaymentTotal').text(numberFormat(summary.customer_payment_total || 0));
        $('#purchaseTotal').text(numberFormat(summary.purchase_total || 0));
        $('#supplierPaymentTotal').text(numberFormat(summary.supplier_payment_total || 0));
        $('#expenseTotal').text(numberFormat(summary.expense_total || 0));
        $('#footerDebit').text(numberFormat(summary.debit_total || 0));
        $('#footerCredit').text(numberFormat(summary.credit_total || 0));
        $('#printDebitCredit').text('Debit: ₹' + numberFormat(summary.debit_total || 0) + ' | Credit: ₹' + numberFormat(summary.credit_total || 0));
    }

    function renderPeriod(filters) {
        let fromDate = filters.from_date || $('#fromDate').val();
        let toDate = filters.to_date || $('#toDate').val();
        let typeLabelText = filters.entry_type_label || typeLabel(getSelectedTypes().join(','));

        let label = fromDate === toDate ? `Date: ${fromDate}` : `Date: ${fromDate} to ${toDate}`;
        label += ` | Type: ${typeLabelText}`;

        $('#reportPeriod').text(label);
    }

    function renderDateSummary(rows) {
        if (!rows || rows.length === 0) {
            $('#dateSummaryBody').html('<tr><td colspan="8" class="text-center text-muted">No date summary found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (_, row) {
            html += `
                <tr>
                    <td>${escapeHtml(row.entry_date || '')}</td>
                    <td class="text-end">₹${numberFormat(row.sales_total || 0)}</td>
                    <td class="text-end">₹${numberFormat(row.customer_payment_total || 0)}</td>
                    <td class="text-end">₹${numberFormat(row.purchase_total || 0)}</td>
                    <td class="text-end">₹${numberFormat(row.supplier_payment_total || 0)}</td>
                    <td class="text-end">₹${numberFormat(row.expense_total || 0)}</td>
                    <td class="text-end fw-semibold">₹${numberFormat(row.debit_total || 0)}</td>
                    <td class="text-end fw-semibold">₹${numberFormat(row.credit_total || 0)}</td>
                </tr>
            `;
        });

        $('#dateSummaryBody').html(html);
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            $('#ledgerReportBody').html('<tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(row.entry_date || '')}</td>
                    <td>${typeBadge(row.entry_type, row.type_label)}</td>
                    <td>${escapeHtml(row.reference_no || '-')}</td>
                    <td>${escapeHtml(row.party_name || '-')}</td>
                    <td>${escapeHtml(row.debit_account || '-')}</td>
                    <td>${escapeHtml(row.credit_account || '-')}</td>
                    <td>${escapeHtml(row.payment_mode || '-')}</td>
                    <td>${escapeHtml(row.description || '-')}</td>
                    <td class="text-end">₹${numberFormat(row.debit_amount || row.amount || 0)}</td>
                    <td class="text-end">₹${numberFormat(row.credit_amount || row.amount || 0)}</td>
                </tr>
            `;
        });

        $('#ledgerReportBody').html(html);
    }

    function typeBadge(type, label) {
        if (type === 'sales') {
            return `<span class="badge bg-success">${escapeHtml(label || 'Sales')}</span>`;
        }

        if (type === 'customer_payment') {
            return `<span class="badge bg-info">${escapeHtml(label || 'Customer Payment')}</span>`;
        }

        if (type === 'purchase') {
            return `<span class="badge bg-danger">${escapeHtml(label || 'Purchase')}</span>`;
        }

        if (type === 'supplier_payment') {
            return `<span class="badge bg-primary">${escapeHtml(label || 'Supplier Payment')}</span>`;
        }

        if (type === 'expense') {
            return `<span class="badge bg-warning text-dark">${escapeHtml(label || 'Expense')}</span>`;
        }

        return `<span class="badge bg-secondary">${escapeHtml(label || type || '')}</span>`;
    }

    function getSelectedTypes() {
        let selected = [];

        $('.ledger-type-item:checked').each(function () {
            selected.push($(this).val());
        });

        if (selected.length === 0) {
            // Keep report safe: if user removes all, auto select all.
            $('#typeAll').prop('checked', true);
            $('.ledger-type-item').prop('checked', true);
            selected = ['sales', 'customer_payment', 'purchase', 'supplier_payment', 'expense'];
        }

        return selected;
    }

    function syncTypeCheckboxes(changedBox) {
        if (changedBox.attr('id') === 'typeAll') {
            let checked = changedBox.is(':checked');
            $('.ledger-type-item').prop('checked', checked);

            if (!checked) {
                // Prevent blank report by keeping all selected.
                $('#typeAll').prop('checked', true);
                $('.ledger-type-item').prop('checked', true);
            }
            return;
        }

        let totalItems = $('.ledger-type-item').length;
        let checkedItems = $('.ledger-type-item:checked').length;

        if (checkedItems === 0) {
            changedBox.prop('checked', true);
            checkedItems = 1;
        }

        $('#typeAll').prop('checked', checkedItems === totalItems);
    }

    function typeLabel(type) {
        if (!type || type === 'all') return 'All';

        let parts = String(type).split(',');
        let labels = [];

        $.each(parts, function (_, item) {
            if (item === 'sales') labels.push('Sales');
            if (item === 'customer_payment') labels.push('Customer Payment');
            if (item === 'purchase') labels.push('Purchase');
            if (item === 'supplier_payment') labels.push('Supplier Payment');
            if (item === 'expense') labels.push('Expense');
        });

        return labels.length ? labels.join(', ') : 'All';
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

        console.log(type + ': ' + message);
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
