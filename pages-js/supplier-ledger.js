$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let pageContext = {
        can_view: false,
        can_list: false,
        can_print: false,
        page_title: 'Supplier Ledger',
        page_note: 'Supplier debit / credit / balance statement'
    };

    loadPageContext();

    $('#supplier_id, #fromDate, #toDate').on('change', loadLedger);
    $('#refreshLedgerBtn').on('click', loadLedger);

    $('#printLedgerBtn').on('click', function () {
        if (!pageContext.can_print) {
            showLedgerToast('error', 'Permission denied.');
            return;
        }

        window.print();
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
                    loadSuppliers();
                } else {
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#ledgerFilterCard').addClass('d-none');
                    $('#printLedgerBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
                $('#ledgerFilterCard').addClass('d-none');
                $('#printLedgerBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Supplier Ledger');
        $('#pageNoteText').text(pageContext.page_note || '');

        if (pageContext.can_print) {
            $('#printLedgerBtn').removeClass('d-none');
        } else {
            $('#printLedgerBtn').addClass('d-none');
        }
    }

    function loadSuppliers() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_suppliers'
            },
            success: function (response) {
                let html = '<option value="">Select Supplier</option>';

                if (response.status === true) {
                    $.each(response.data.suppliers || [], function (_, supplier) {
                        let selected = parseInt(supplier.id) === parseInt(window.PRE_SUPPLIER_ID || 0) ? 'selected' : '';
                        html += '<option value="' + supplier.id + '" ' + selected + '>' + escapeHtml(supplier.supplier_name || '') + '</option>';
                    });
                }

                $('#supplier_id').html(html);

                if (parseInt(window.PRE_SUPPLIER_ID || 0) > 0) {
                    loadLedger();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadLedger() {
        if (!pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        let supplierId = parseInt($('#supplier_id').val() || 0);

        if (supplierId <= 0) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Select supplier.</td></tr>');
            renderSummary({});
            return;
        }

        $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_ledger',
                supplier_id: supplierId,
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderRows(response.data.entries || []);
                    renderSummary(response.data.summary || {});
                } else {
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load ledger.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">No ledger entries found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let balance = parseFloat(row.balance || 0);
            let balanceText = numberFormat(Math.abs(balance)) + (balance >= 0 ? ' Cr' : ' Dr');

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(row.display_date || row.entry_date || '') + '</td>';
            html += '<td>' + escapeHtml(row.particular || '') + '</td>';
            html += '<td>' + escapeHtml(row.reference_no || '-') + '</td>';
            html += '<td>' + escapeHtml(row.entry_type || '') + '</td>';
            html += '<td class="text-end">₹' + numberFormat(row.debit || 0) + '</td>';
            html += '<td class="text-end">₹' + numberFormat(row.credit || 0) + '</td>';
            html += '<td class="text-end fw-semibold">₹' + balanceText + '</td>';
            html += '</tr>';
        });

        $('#ledgerTableBody').html(html);
    }

    function renderSummary(summary) {
        $('#totalEntries').text(summary.total_entries || 0);
        $('#totalDebit').text(numberFormat(summary.total_debit || 0));
        $('#totalCredit').text(numberFormat(summary.total_credit || 0));

        let closing = parseFloat(summary.closing_balance || 0);
        $('#closingBalance').text(numberFormat(Math.abs(closing)) + (closing >= 0 ? ' Cr' : ' Dr'));
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showLedgerToast(type, message) {
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
