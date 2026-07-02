$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    loadCustomers();

    $('#filterLedgerBtn').on('click', loadLedger);

    $('#resetLedgerBtn').on('click', function () {
        $('#fromDate').val('');
        $('#toDate').val('');
        $('.ledger-doc-type').prop('checked', true);
        loadLedger();
    });

    $('#customerId').on('change', loadLedger);

    function loadCustomers() {
        $.ajax({
            url: window.BASE_URL + 'api/customer-ledger.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'search_customers' },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="">Select Customer</option>';
                    $.each(response.data.customers || [], function (_, customer) {
                        let label = customer.customer_name + (customer.mobile ? ' - ' + customer.mobile : '');
                        html += `<option value="${customer.id}">${escapeHtml(label)}</option>`;
                    });
                    $('#customerId').html(html);
                } else showToastSafe('error', response.message || 'Unable to load customers.');
            },
            error: function (xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); }
        });
    }

    function getCheckedTypes() {
        let types = [];
        $('.ledger-doc-type:checked').each(function () { types.push($(this).val()); });
        return types;
    }

    function loadLedger() {
        let customerId = $('#customerId').val();
        if (!customerId) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Select customer and filter ledger.</td></tr>');
            resetStats();
            return;
        }

        $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');
        $.ajax({
            url: window.BASE_URL + 'api/customer-ledger.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_ledger',
                customer_id: customerId,
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val(),
                document_types: getCheckedTypes()
            },
            success: function (response) {
                if (response.status === true) {
                    renderLedger(response.data.entries || []);
                    renderSummary(response.data.summary || {});
                    renderCustomerInfo(response.data.customer || {});
                } else {
                    $('#ledgerTableBody').html(`<tr><td colspan="8" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load ledger.')}</td></tr>`);
                    showToastSafe('error', response.message || 'Unable to load ledger.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderLedger(rows) {
        if (!rows || rows.length === 0) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">No ledger entries found.</td></tr>');
            return;
        }
        let html = '';
        $.each(rows, function (index, row) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${formatDate(row.entry_date)}</td>
                    <td><h6 class="mb-0">${escapeHtml(row.particular || '')}</h6></td>
                    <td>${escapeHtml(row.reference_no || '-')}</td>
                    <td>${typeBadge(row.document_type, row.document_label)}</td>
                    <td class="text-end text-danger">${parseFloat(row.debit || 0) > 0 ? formatCurrency(row.debit) : '-'}</td>
                    <td class="text-end text-success">${parseFloat(row.credit || 0) > 0 ? formatCurrency(row.credit) : '-'}</td>
                    <td class="text-end"><strong>${formatCurrency(row.balance || 0)}</strong></td>
                </tr>`;
        });
        $('#ledgerTableBody').html(html);
    }

    function renderSummary(summary) {
        $('#ledgerEntriesCount').text(summary.entry_count || 0);
        $('#ledgerDebitTotal').text(formatCurrency(summary.total_debit || 0));
        $('#ledgerCreditTotal').text(formatCurrency(summary.total_credit || 0));
        $('#ledgerClosingBalance').text(formatCurrency(summary.closing_balance || 0));
    }

    function resetStats() {
        $('#ledgerEntriesCount').text(0);
        $('#ledgerDebitTotal').text(formatCurrency(0));
        $('#ledgerCreditTotal').text(formatCurrency(0));
        $('#ledgerClosingBalance').text(formatCurrency(0));
        $('#ledgerCustomerName').text('Select customer');
        $('#ledgerCustomerInfo').text('Ledger statement');
    }

    function renderCustomerInfo(customer) {
        $('#ledgerCustomerName').text(customer.customer_name || '-');
        let info = [];
        if (customer.mobile) info.push(customer.mobile);
        if (customer.gst_number) info.push(customer.gst_number);
        $('#ledgerCustomerInfo').text(info.join(' | ') || 'Ledger statement');
    }

    function typeBadge(type, label) {
        type = parseInt(type || 0);
        if (type === 0) return '<span class="badge bg-secondary">Opening</span>';
        if (type === 1) return '<span class="badge bg-soft-primary text-primary">Quotation</span>';
        if (type === 2) return '<span class="badge bg-soft-info text-info">Proforma</span>';
        if (type === 3) return '<span class="badge bg-soft-warning text-warning">Sales Bill</span>';
        if (type === 4) return '<span class="badge bg-soft-success text-success">Direct Bill</span>';
        if (type === 5) return '<span class="badge bg-primary">Final Invoice</span>';
        if (type === 99) return '<span class="badge bg-success">Payment</span>';
        return `<span class="badge bg-light text-dark">${escapeHtml(label || 'Document')}</span>`;
    }

    function formatDate(date) {
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length !== 3) return escapeHtml(date);
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);
        return '₹' + numberValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') showToast(type, message, 5000);
        else alert(message);
    }

    function escapeHtml(value) { return $('<div>').text(value === null || value === undefined ? '' : value).html(); }
});
