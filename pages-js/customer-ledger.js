$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let initialCustomerId = parseInt(new URLSearchParams(window.location.search).get('customer_id') || new URLSearchParams(window.location.search).get('id') || 0);
    let currentEntries = [];
    let currentCustomer = {};
    let currentSummary = {};

    let pageContext = {
        can_view: false,
        can_list: false,
        can_print: false,
        can_export: false,
        can_customers: false,
        page_title: 'Customer Ledger',
        page_note: 'Customer debit / credit / balance statement',
        customers_url: ''
    };

    loadPageContext();

    $('#filterLedgerBtn').on('click', loadLedger);

    $('#resetLedgerBtn').on('click', function () {
        $('#fromDate').val('');
        $('#toDate').val('');
        $('.ledger-doc-type').prop('checked', true);
        loadLedger();
    });

    $('#customerId').on('change', loadLedger);

    $(document).on('click', '.print-ledger-option', function () {
        let size = $(this).data('size') || 'a4';
        let orientation = $(this).data('orientation') || 'landscape';
        printLedger(size, orientation);
    });

    $('#exportExcelBtn').on('click', exportExcel);
    $('#exportCsvBtn').on('click', exportCsv);

    $(document).on('click', '.pdf-ledger-option', function () {
        let orientation = $(this).data('orientation') || 'portrait';
        exportPdf(orientation);
    });

    window.addEventListener('afterprint', function () {
        $('body').removeClass('ledger-print-a4 ledger-print-a3 ledger-print-portrait ledger-print-landscape');
        $('#dynamicLedgerPrintStyle').remove();
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_page_context' },
            success: function (response) {
                if (response.status === true) {
                    pageContext = $.extend(pageContext, response.data.context || {});
                    applyPageContext();
                    loadCustomers();
                } else {
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#ledgerFilterCard').addClass('d-none');
                    $('#customersBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
                $('#ledgerFilterCard').addClass('d-none');
                $('#customersBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Customer Ledger');
        $('#pageNoteText').text(pageContext.page_note || 'Customer debit / credit / balance statement');

        if (pageContext.customers_url) {
            $('#customersBtn').attr('href', pageContext.customers_url);
        }

        toggleAction('#customersBtn', pageContext.can_customers);
        toggleAction('#printLedgerDropdown', pageContext.can_print);
        toggleAction('#exportExcelBtn', pageContext.can_export);
        toggleAction('#exportCsvBtn', pageContext.can_export);
        toggleAction('#exportPdfDropdown', pageContext.can_export);
    }

    function toggleAction(selector, allowed) {
        if (allowed) {
            $(selector).removeClass('d-none');
        } else {
            $(selector).addClass('d-none');
        }
    }

    function loadCustomers() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'search_customers' },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="">Select Customer</option>';

                    $.each(response.data.customers || [], function (_, customer) {
                        let label = customer.customer_name + (customer.mobile ? ' - ' + customer.mobile : '');
                        html += '<option value="' + customer.id + '">' + escapeHtml(label) + '</option>';
                    });

                    $('#customerId').html(html);

                    if (initialCustomerId > 0 && $('#customerId option[value="' + initialCustomerId + '"]').length) {
                        $('#customerId').val(String(initialCustomerId));
                        initialCustomerId = 0;
                        loadLedger();
                    }
                } else {
                    showToastSafe('error', response.message || 'Unable to load customers.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    }

    function getCheckedTypes() {
        let types = [];
        $('.ledger-doc-type:checked').each(function () {
            types.push($(this).val());
        });
        return types;
    }

    function loadLedger() {
        if (!pageContext.can_list) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        let customerId = $('#customerId').val();

        if (!customerId) {
            $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Select customer and filter ledger.</td></tr>');
            currentEntries = [];
            currentCustomer = {};
            currentSummary = {};
            resetStats();
            return;
        }

        $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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
                    currentEntries = response.data.entries || [];
                    currentCustomer = response.data.customer || {};
                    currentSummary = response.data.summary || {};
                    renderLedger(currentEntries);
                    renderSummary(currentSummary);
                    renderCustomerInfo(currentCustomer);
                } else {
                    $('#ledgerTableBody').html('<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load ledger.') + '</td></tr>');
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
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + formatDate(row.entry_date) + '</td>';
            html += '<td class="particular-cell"><h6 class="mb-0">' + escapeHtml(row.particular || '') + '</h6></td>';
            html += '<td>' + escapeHtml(row.reference_no || '-') + '</td>';
            html += '<td>' + typeBadge(row.document_type, row.document_label) + '</td>';
            html += '<td class="text-end text-danger">' + (parseFloat(row.debit || 0) > 0 ? formatCurrency(row.debit) : '-') + '</td>';
            html += '<td class="text-end text-success">' + (parseFloat(row.credit || 0) > 0 ? formatCurrency(row.credit) : '-') + '</td>';
            html += '<td class="text-end"><strong>' + formatBalance(row.balance || 0) + '</strong></td>';
            html += '</tr>';
        });

        $('#ledgerTableBody').html(html);
    }

    function renderSummary(summary) {
        $('#ledgerEntriesCount').text(summary.entry_count || 0);
        $('#ledgerDebitTotal').text(formatCurrency(summary.total_debit || 0));
        $('#ledgerCreditTotal').text(formatCurrency(summary.total_credit || 0));
        $('#ledgerClosingBalance').text(formatBalance(summary.closing_balance || 0));
        $('#ledgerPeriodBadge').text(dateRangeText());
    }

    function resetStats() {
        $('#ledgerEntriesCount').text(0);
        $('#ledgerDebitTotal').text(formatCurrency(0));
        $('#ledgerCreditTotal').text(formatCurrency(0));
        $('#ledgerClosingBalance').text(formatBalance(0));
        $('#ledgerCustomerName').text('Select customer');
        $('#ledgerCustomerInfo').text('Ledger statement');
        $('#ledgerPeriodBadge').text('All Period');
    }

    function renderCustomerInfo(customer) {
        $('#ledgerCustomerName').text(customer.customer_name || '-');

        let info = [];
        if (customer.mobile) info.push(customer.mobile);
        if (customer.gst_number) info.push(customer.gst_number);

        $('#ledgerCustomerInfo').text(info.join(' | ') || 'Ledger statement');
    }

    function ensureLedgerData() {
        if (!$('#customerId').val()) {
            showToastSafe('warning', 'Please select customer.');
            $('#customerId').focus();
            return false;
        }

        if (!currentEntries || currentEntries.length === 0) {
            showToastSafe('warning', 'No ledger data to export or print.');
            return false;
        }

        return true;
    }

    function printLedger(size, orientation) {
        if (!pageContext.can_print) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (!ensureLedgerData()) {
            return;
        }

        size = size === 'a3' ? 'a3' : 'a4';
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        renderPrintArea(size, orientation);
        $('#dynamicLedgerPrintStyle').remove();
        $('head').append('<style id="dynamicLedgerPrintStyle">@media print { @page { size: ' + (size === 'a3' ? 'A3' : 'A4') + ' ' + orientation + '; margin: ' + (orientation === 'portrait' ? '10mm' : '8mm') + '; } }</style>');
        $('body')
            .removeClass('ledger-print-a4 ledger-print-a3 ledger-print-portrait ledger-print-landscape')
            .addClass((size === 'a3' ? 'ledger-print-a3' : 'ledger-print-a4') + ' ledger-print-' + orientation);

        setTimeout(function () {
            window.print();
        }, 150);
    }

    function renderPrintArea(size, orientation) {
        size = size === 'a3' ? 'a3' : 'a4';
        orientation = orientation === 'portrait' ? 'portrait' : 'landscape';
        let rowsHtml = '';
        $.each(currentEntries || [], function (index, row) {
            rowsHtml += '<tr>';
            rowsHtml += '<td style="width:4%;text-align:center;">' + (index + 1) + '</td>';
            rowsHtml += '<td style="width:10%;">' + escapeHtml(formatDate(row.entry_date)) + '</td>';
            rowsHtml += '<td style="width:26%;">' + escapeHtml(row.particular || '') + '</td>';
            rowsHtml += '<td style="width:14%;">' + escapeHtml(row.reference_no || '-') + '</td>';
            rowsHtml += '<td style="width:12%;">' + escapeHtml(row.document_label || typeText(row.document_type)) + '</td>';
            rowsHtml += '<td style="width:11%;text-align:right;">' + (parseFloat(row.debit || 0) > 0 ? escapeHtml(formatCurrency(row.debit)) : '-') + '</td>';
            rowsHtml += '<td style="width:11%;text-align:right;">' + (parseFloat(row.credit || 0) > 0 ? escapeHtml(formatCurrency(row.credit)) : '-') + '</td>';
            rowsHtml += '<td style="width:12%;text-align:right;font-weight:700;">' + escapeHtml(formatBalance(row.balance || 0)) + '</td>';
            rowsHtml += '</tr>';
        });

        let customerDetails = customerInfoForPrint();
        let documentTypes = selectedDocumentTypeText();
        let generatedAt = new Date().toLocaleString('en-IN');

        $('#ledgerPrintArea').html(
            '<div class="print-statement">' +
                '<div class="print-header">' +
                    '<div>' +
                        '<div class="print-branch">' + escapeHtml(window.BRANCH_NAME || 'Branch') + '</div>' +
                        '<div class="print-meta">Customer Ledger Statement</div>' +
                    '</div>' +
                    '<div style="text-align:right;">' +
                        '<h1 class="print-title">CUSTOMER LEDGER</h1>' +
                        '<div class="print-meta">Format: ' + escapeHtml(String(size || 'A4').toUpperCase()) + ' ' + escapeHtml(titleCase(orientation)) + '</div>' +
                    '</div>' +
                '</div>' +

                '<div class="print-grid">' +
                    '<div class="print-box">' +
                        '<div class="print-box-title">Customer Details</div>' +
                        customerDetails +
                    '</div>' +
                    '<div class="print-box">' +
                        '<div class="print-box-title">Statement Details</div>' +
                        '<div><strong>Period:</strong> ' + escapeHtml(dateRangeText()) + '</div>' +
                        '<div><strong>Document Type:</strong> ' + escapeHtml(documentTypes) + '</div>' +
                        '<div><strong>Generated:</strong> ' + escapeHtml(generatedAt) + '</div>' +
                    '</div>' +
                '</div>' +

                '<div class="print-summary-grid">' +
                    '<div class="print-summary-item"><div>Total Entries</div><div>' + escapeHtml(currentSummary.entry_count || 0) + '</div></div>' +
                    '<div class="print-summary-item"><div>Total Debit</div><div>' + escapeHtml(formatCurrency(currentSummary.total_debit || 0)) + '</div></div>' +
                    '<div class="print-summary-item"><div>Total Credit</div><div>' + escapeHtml(formatCurrency(currentSummary.total_credit || 0)) + '</div></div>' +
                    '<div class="print-summary-item"><div>Closing Balance</div><div>' + escapeHtml(formatBalance(currentSummary.closing_balance || 0)) + '</div></div>' +
                '</div>' +

                '<table class="print-ledger-table">' +
                    '<thead><tr>' +
                        '<th>#</th><th>Date</th><th>Particular</th><th>Reference</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th>' +
                    '</tr></thead>' +
                    '<tbody>' + rowsHtml + '</tbody>' +
                '</table>' +

                '<div class="print-footer">' +
                    '<div>This is a system generated customer ledger statement.</div>' +
                    '<div>Printed by ' + escapeHtml(window.BRANCH_NAME || 'Branch') + '</div>' +
                '</div>' +
            '</div>'
        );
    }

    function exportExcel() {
        if (!pageContext.can_export) {
            showToastSafe('error', 'Permission denied.');
            return;
        }
        if (!ensureLedgerData()) return;

        let html = '<html><head><meta charset="UTF-8"></head><body>';
        html += '<h2>' + escapeHtml(window.BRANCH_NAME || 'Branch') + '</h2>';
        html += '<h3>Customer Ledger Statement</h3>';
        html += '<p><b>Customer:</b> ' + escapeHtml(currentCustomer.customer_name || '-') + '</p>';
        html += '<p><b>Period:</b> ' + escapeHtml(dateRangeText()) + '</p>';
        html += '<p><b>Document Type:</b> ' + escapeHtml(selectedDocumentTypeText()) + '</p>';
        html += '<table border="1"><thead><tr><th>#</th><th>Date</th><th>Particular</th><th>Reference</th><th>Type</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead><tbody>';

        $.each(currentEntries, function (index, row) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(formatDate(row.entry_date)) + '</td>';
            html += '<td>' + escapeHtml(row.particular || '') + '</td>';
            html += '<td>' + escapeHtml(row.reference_no || '-') + '</td>';
            html += '<td>' + escapeHtml(row.document_label || typeText(row.document_type)) + '</td>';
            html += '<td>' + numberFormat(row.debit || 0) + '</td>';
            html += '<td>' + numberFormat(row.credit || 0) + '</td>';
            html += '<td>' + balancePlain(row.balance || 0) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></body></html>';
        downloadBlob(html, fileBaseName() + '.xls', 'application/vnd.ms-excel');
    }

    function exportCsv() {
        if (!pageContext.can_export) {
            showToastSafe('error', 'Permission denied.');
            return;
        }
        if (!ensureLedgerData()) return;

        let lines = [];
        lines.push(['Branch', window.BRANCH_NAME || 'Branch']);
        lines.push(['Customer', currentCustomer.customer_name || '-']);
        lines.push(['Period', dateRangeText()]);
        lines.push(['Document Type', selectedDocumentTypeText()]);
        lines.push([]);
        lines.push(['#', 'Date', 'Particular', 'Reference', 'Type', 'Debit', 'Credit', 'Balance']);

        $.each(currentEntries, function (index, row) {
            lines.push([
                index + 1,
                formatDate(row.entry_date),
                row.particular || '',
                row.reference_no || '-',
                row.document_label || typeText(row.document_type),
                numberFormat(row.debit || 0),
                numberFormat(row.credit || 0),
                balancePlain(row.balance || 0)
            ]);
        });

        let csv = lines.map(function (line) {
            return line.map(csvEscape).join(',');
        }).join('\n');

        downloadBlob(csv, fileBaseName() + '.csv', 'text/csv;charset=utf-8;');
    }

    function exportPdf(orientation) {
        if (!pageContext.can_export) {
            showToastSafe('error', 'Permission denied.');
            return;
        }
        if (!ensureLedgerData()) return;

        if (!window.jspdf || !window.jspdf.jsPDF) {
            showToastSafe('error', 'PDF library not loaded. Please check internet connection.');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: orientation, unit: 'pt', format: 'a4' });
        const pageWidth = doc.internal.pageSize.getWidth();
        const margin = 28;
        const tableFont = orientation === 'portrait' ? 7 : 8;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.text(window.BRANCH_NAME || 'Branch', margin, 30);

        doc.setFontSize(13);
        doc.text('CUSTOMER LEDGER STATEMENT', pageWidth - margin, 30, { align: 'right' });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text('Customer: ' + (currentCustomer.customer_name || '-'), margin, 48);
        doc.text('Period: ' + dateRangeText(), margin, 62);
        doc.text('Document Type: ' + selectedDocumentTypeText(), margin, 76);
        doc.text('Generated: ' + new Date().toLocaleString('en-IN'), pageWidth - margin, 48, { align: 'right' });

        const summaryY = 96;
        doc.setFont('helvetica', 'bold');
        doc.text('Entries: ' + (currentSummary.entry_count || 0), margin, summaryY);
        doc.text('Debit: ' + formatPdfAmount(currentSummary.total_debit || 0), margin + 120, summaryY);
        doc.text('Credit: ' + formatPdfAmount(currentSummary.total_credit || 0), margin + 260, summaryY);
        doc.text('Closing: ' + balancePdf(currentSummary.closing_balance || 0), margin + 400, summaryY);

        const body = (currentEntries || []).map(function (row, index) {
            return [
                index + 1,
                formatDate(row.entry_date),
                row.particular || '',
                row.reference_no || '-',
                row.document_label || typeText(row.document_type),
                parseFloat(row.debit || 0) > 0 ? formatPdfAmount(row.debit) : '-',
                parseFloat(row.credit || 0) > 0 ? formatPdfAmount(row.credit) : '-',
                balancePdf(row.balance || 0)
            ];
        });

        doc.autoTable({
            startY: 112,
            head: [['#', 'Date', 'Particular', 'Reference', 'Type', 'Debit', 'Credit', 'Balance']],
            body: body,
            theme: 'grid',
            styles: { fontSize: tableFont, cellPadding: 3, overflow: 'linebreak' },
            headStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
            columnStyles: {
                0: { cellWidth: 26, halign: 'center' },
                1: { cellWidth: 62 },
                5: { halign: 'right' },
                6: { halign: 'right' },
                7: { halign: 'right' }
            },
            margin: { left: margin, right: margin },
            didDrawPage: function () {
                let pageHeight = doc.internal.pageSize.getHeight();
                doc.setFontSize(8);
                doc.setFont('helvetica', 'normal');
                doc.text('This is a system generated customer ledger statement.', margin, pageHeight - 16);
                doc.text('Printed by ' + (window.BRANCH_NAME || 'Branch'), pageWidth - margin, pageHeight - 16, { align: 'right' });
            }
        });

        doc.save(fileBaseName() + '-' + orientation + '.pdf');
    }

    function customerInfoForPrint() {
        let info = [];
        info.push('<div><strong>Name:</strong> ' + escapeHtml(currentCustomer.customer_name || '-') + '</div>');
        info.push('<div><strong>Mobile:</strong> ' + escapeHtml(currentCustomer.mobile || '-') + '</div>');
        info.push('<div><strong>Email:</strong> ' + escapeHtml(currentCustomer.email || '-') + '</div>');
        info.push('<div><strong>GST:</strong> ' + escapeHtml(currentCustomer.gst_number || '-') + '</div>');
        info.push('<div><strong>Address:</strong> ' + escapeHtml(formatAddress(currentCustomer) || '-') + '</div>');
        return info.join('');
    }

    function formatAddress(customer) {
        let parts = [];
        if (customer.address) parts.push(customer.address);
        if (customer.city) parts.push(customer.city);
        if (customer.state) parts.push(customer.state);
        if (customer.pincode) parts.push(customer.pincode);
        return parts.join(', ');
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
        return '<span class="badge bg-light text-dark">' + escapeHtml(label || 'Document') + '</span>';
    }

    function typeText(type) {
        type = parseInt(type || 0);
        let map = {
            0: 'Opening',
            1: 'Quotation',
            2: 'Proforma Bill',
            3: 'Sales Bill',
            4: 'Direct Bill',
            5: 'Final Invoice',
            99: 'Payment'
        };
        return map[type] || 'Document';
    }

    function selectedDocumentTypeText() {
        let labels = [];
        $('.ledger-doc-type:checked').each(function () {
            labels.push(typeText($(this).val()));
        });
        return labels.length ? labels.join(', ') : 'All';
    }

    function dateRangeText() {
        let from = $('#fromDate').val();
        let to = $('#toDate').val();
        if (from && to) return formatDate(from) + ' to ' + formatDate(to);
        if (from) return 'From ' + formatDate(from);
        if (to) return 'Up to ' + formatDate(to);
        return 'All Period';
    }

    function fileBaseName() {
        let customer = (currentCustomer.customer_name || 'customer').toString().replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
        if (!customer) customer = 'customer';
        return 'customer-ledger-' + customer + '-' + new Date().toISOString().slice(0, 10);
    }

    function downloadBlob(content, filename, mimeType) {
        let blob = new Blob([content], { type: mimeType });
        let url = URL.createObjectURL(blob);
        let link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function csvEscape(value) {
        value = value === null || value === undefined ? '' : String(value);
        return '"' + value.replace(/"/g, '""') + '"';
    }

    function titleCase(value) {
        value = String(value || '');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function formatDate(date) {
        if (!date) return '-';
        let parts = String(date).split('-');
        if (parts.length !== 3) return escapeHtml(date);
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);
        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatBalance(value) {
        let numberValue = parseFloat(value || 0);
        let suffix = numberValue >= 0 ? ' Dr' : ' Cr';
        return '₹' + Math.abs(numberValue).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + suffix;
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function balancePlain(value) {
        let numberValue = parseFloat(value || 0);
        return numberFormat(Math.abs(numberValue)) + (numberValue >= 0 ? ' Dr' : ' Cr');
    }

    function formatPdfAmount(value) {
        return 'Rs. ' + numberFormat(value || 0);
    }

    function balancePdf(value) {
        let numberValue = parseFloat(value || 0);
        return 'Rs. ' + numberFormat(Math.abs(numberValue)) + (numberValue >= 0 ? ' Dr' : ' Cr');
    }

    function showToastSafe(type, message) {
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
