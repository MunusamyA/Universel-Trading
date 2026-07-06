$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let selectedReport = '';
    let searchTimer = null;
    let lastReportData = null;

    const fallbackReports = [
        { group: 'Sales', key: 'sales_summary', title: 'Sales Summary', icon: 'mdi-chart-bar' },
        { group: 'Sales', key: 'sales_detailed', title: 'Sales Detailed', icon: 'mdi-file-document' },
        { group: 'Sales', key: 'customer_wise_sales', title: 'Customer-wise Sales', icon: 'mdi-account-cash' },
        { group: 'Sales', key: 'product_wise_sales', title: 'Product-wise Sales', icon: 'mdi-package-variant' },
        { group: 'Sales', key: 'sales_due', title: 'Sales Due', icon: 'mdi-calendar-alert' },
        { group: 'Sales', key: 'customer_payment', title: 'Customer Payment', icon: 'mdi-cash-plus' },

        { group: 'Purchase', key: 'purchase_summary', title: 'Purchase Summary', icon: 'mdi-chart-bar' },
        { group: 'Purchase', key: 'purchase_detailed', title: 'Purchase Detailed', icon: 'mdi-file-document' },
        { group: 'Purchase', key: 'supplier_wise_purchase', title: 'Supplier-wise Purchase', icon: 'mdi-truck' },
        { group: 'Purchase', key: 'product_wise_purchase', title: 'Product-wise Purchase', icon: 'mdi-package-down' },
        { group: 'Purchase', key: 'purchase_due', title: 'Purchase Due', icon: 'mdi-calendar-alert' },
        { group: 'Purchase', key: 'supplier_payment', title: 'Supplier Payment', icon: 'mdi-cash-minus' },

        { group: 'Expense', key: 'expense_summary', title: 'Expense Summary', icon: 'mdi-chart-pie' },
        { group: 'Expense', key: 'expense_detailed', title: 'Expense Detailed', icon: 'mdi-receipt' },
        { group: 'Expense', key: 'payment_mode_expense', title: 'Payment Mode-wise Expense', icon: 'mdi-bank' },

        { group: 'Outstanding', key: 'customer_outstanding', title: 'Customer Outstanding', icon: 'mdi-account-alert' },
        { group: 'Outstanding', key: 'supplier_outstanding', title: 'Supplier Outstanding', icon: 'mdi-truck-alert' },
        { group: 'Outstanding', key: 'customer_ageing', title: 'Customer Ageing', icon: 'mdi-timer-sand' },
        { group: 'Outstanding', key: 'supplier_ageing', title: 'Supplier Ageing', icon: 'mdi-timer-sand' },

        { group: 'Stock', key: 'current_stock', title: 'Current Stock', icon: 'mdi-warehouse' },
        { group: 'Stock', key: 'low_stock', title: 'Low Stock', icon: 'mdi-alert-box' },
        { group: 'Stock', key: 'batch_stock', title: 'Batch-wise Stock', icon: 'mdi-barcode' },
        { group: 'Stock', key: 'stock_movement', title: 'Stock Movement', icon: 'mdi-swap-horizontal' },
        { group: 'Stock', key: 'stock_valuation', title: 'Stock Valuation', icon: 'mdi-currency-inr' },

        { group: 'Profit', key: 'gross_profit', title: 'Gross Profit', icon: 'mdi-trending-up' },
        { group: 'Profit', key: 'net_profit', title: 'Net Profit', icon: 'mdi-finance' },
        { group: 'Profit', key: 'product_profit', title: 'Product Profit', icon: 'mdi-package-variant-closed' },

        { group: 'GST', key: 'sales_gst', title: 'Sales GST', icon: 'mdi-percent' },
        { group: 'GST', key: 'purchase_gst', title: 'Purchase GST', icon: 'mdi-percent' },
        { group: 'GST', key: 'gst_summary', title: 'GST Summary', icon: 'mdi-calculator' },

        { group: 'Master', key: 'customer_master', title: 'Customer Master', icon: 'mdi-account-group' },
        { group: 'Master', key: 'supplier_master', title: 'Supplier Master', icon: 'mdi-truck' },
        { group: 'Master', key: 'product_master', title: 'Product Master', icon: 'mdi-package' },

        { group: 'Admin', key: 'user_wise_sales', title: 'User-wise Sales', icon: 'mdi-account-star' },
        { group: 'Admin', key: 'cancelled_deleted', title: 'Cancelled / Deleted', icon: 'mdi-delete-alert' }
    ];

    let pageContext = {
        can_view: false,
        can_print: false,
        can_export: false,
        can_generate_report: false,
        reports: fallbackReports,
        default_report: 'sales_summary'
    };

    loadContext();

    $('#loadReportBtn, #fromDate, #toDate').on('click change', loadReport);

    $('#reportSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadReport, 400);
    });

    $('#printReportBtn').on('click', function () {
        if (!pageContext.can_print) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        window.print();
    });

    $('#exportReportBtn').on('click', function () {
        if (!pageContext.can_export) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        exportCurrentReport();
    });

    $(document).on('click', '.report-card', function () {
        selectedReport = $(this).data('key');
        $('.report-card').removeClass('active');
        $(this).addClass('active');
        loadReport();
    });

    function loadContext() {
        $.ajax({
            url: window.BASE_URL + 'api/all-reports.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_context' },
            success: function (response) {
                if (response.status === true) {
                    pageContext = $.extend(pageContext, response.data.context || {});
                    pageContext.reports = pageContext.reports && pageContext.reports.length ? pageContext.reports : fallbackReports;
                    selectedReport = pageContext.default_report || (pageContext.reports[0] ? pageContext.reports[0].key : '');
                    applyContext();
                    renderCards();
                    loadReport();
                } else {
                    showPermissionError(response.message || 'Permission denied.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);

                /*
                 * Fallback for old api/all-reports.php without get_context.
                 */
                pageContext.can_view = true;
                pageContext.can_print = true;
                pageContext.can_export = true;
                pageContext.can_generate_report = true;
                pageContext.reports = fallbackReports;
                selectedReport = 'sales_summary';
                applyContext();
                renderCards();
                loadReport();
            }
        });
    }

    function applyContext() {
        if (pageContext.can_print) {
            $('#printReportBtn').removeClass('d-none');
        } else {
            $('#printReportBtn').addClass('d-none');
        }

        if (pageContext.can_export) {
            $('#exportReportBtn').removeClass('d-none');
        } else {
            $('#exportReportBtn').addClass('d-none');
        }

        if (!pageContext.can_view && !pageContext.can_generate_report && (!pageContext.reports || pageContext.reports.length === 0)) {
            $('#loadReportBtn').prop('disabled', true);
        }
    }

    function showPermissionError(message) {
        $('#reportCards').html('');
        $('#reportTitle').text('Permission denied');
        $('#recordCount').text('0 Records');
        $('#reportPeriod').text('-');
        $('#summaryBox').html('');
        $('#reportHead').html('<tr><th>Error</th></tr>');
        $('#reportBody').html('<tr><td class="text-center text-danger">' + escapeHtml(message) + '</td></tr>');
        $('#reportFoot').html('');
        $('#loadReportBtn, #printReportBtn, #exportReportBtn').prop('disabled', true);
    }

    function renderCards() {
        let reports = pageContext.reports || [];
        let html = '';
        let currentGroup = '';

        if (!reports.length) {
            $('#reportCards').html('<div class="col-12"><div class="alert alert-warning">No reports available for this role.</div></div>');
            return;
        }

        $.each(reports, function (_, report) {
            if (currentGroup !== report.group) {
                currentGroup = report.group;
                html += `<div class="col-12"><div class="report-group-title">${escapeHtml(currentGroup)} Reports</div></div>`;
            }

            html += `
                <div class="col-md-4 col-xl-3">
                    <div class="card report-card ${report.key === selectedReport ? 'active' : ''}" data-key="${escapeHtml(report.key)}">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <span class="report-icon me-3">
                                    <i class="mdi ${escapeHtml(report.icon || 'mdi-file-chart-outline')} font-size-20"></i>
                                </span>
                                <div class="min-w-0">
                                    <h6 class="mb-1 text-truncate">${escapeHtml(report.title || '')}</h6>
                                    <small class="text-muted">${escapeHtml(report.group || '')}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#reportCards').html(html);
    }

    function loadReport() {
        if (!selectedReport) {
            showPermissionError('No report selected.');
            return;
        }

        $('#reportHead').html('<tr><th>Loading...</th></tr>');
        $('#reportBody').html('<tr><td class="text-center text-muted">Loading...</td></tr>');
        $('#reportFoot, #summaryBox').html('');

        $.ajax({
            url: window.BASE_URL + 'api/all-reports.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list',
                report_key: selectedReport,
                from_date: $('#fromDate').val(),
                to_date: $('#toDate').val(),
                search: $('#reportSearch').val()
            },
            success: function (response) {
                if (response.status === true) {
                    lastReportData = response.data || {};
                    if (lastReportData.context) {
                        pageContext.can_print = !!lastReportData.context.can_print;
                        pageContext.can_export = !!lastReportData.context.can_export;
                        applyContext();
                    }
                    renderReport(lastReportData);
                } else {
                    $('#reportHead').html('<tr><th>Error</th></tr>');
                    $('#reportBody').html(`<tr><td class="text-center text-danger">${escapeHtml(response.message || 'Error')}</td></tr>`);
                    $('#reportFoot, #summaryBox').html('');
                    showToastSafe('error', response.message || 'Error');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#reportHead').html('<tr><th>Error</th></tr>');
                $('#reportBody').html('<tr><td class="text-center text-danger">Server error.</td></tr>');
                $('#reportFoot, #summaryBox').html('');
            }
        });
    }

    function renderReport(data) {
        let rows = data.rows || [];
        let columns = data.columns || [];
        let filters = data.filters || {};

        $('#reportTitle').text(data.title || 'Report');
        $('#recordCount').text((data.count || rows.length || 0) + ' Records');

        let period = '';
        if (filters.from_date && filters.to_date) {
            period = filters.from_date === filters.to_date
                ? 'Date: ' + filters.from_date
                : 'Date: ' + filters.from_date + ' to ' + filters.to_date;
        }

        if (filters.search) {
            period += (period ? ' | ' : '') + 'Search: ' + filters.search;
        }

        $('#reportPeriod').text(period || '-');

        renderSummary(data.totals || {});
        renderTable(columns, rows, data.totals || {});
    }

    function renderSummary(totals) {
        let keys = Object.keys(totals || {});
        let html = '';

        $.each(keys, function (_, key) {
            html += `
                <div class="col-md-3 col-xl-2">
                    <div class="summary-tile">
                        <small class="text-muted">${label(key)}</small>
                        <h6 class="mb-0">${isMoneyKey(key) ? '₹' : ''}${formatNumber(totals[key])}</h6>
                    </div>
                </div>
            `;
        });

        $('#summaryBox').html(html);
    }

    function renderTable(columns, rows, totals) {
        let keys = rows.length ? Object.keys(rows[0]) : [];
        let displayColumns = columns && columns.length ? columns : keys.map(label);

        let head = '<tr><th style="width:60px;">#</th>';
        $.each(displayColumns, function (_, column) {
            head += `<th>${escapeHtml(column)}</th>`;
        });
        head += '</tr>';
        $('#reportHead').html(head);

        if (!rows.length) {
            $('#reportBody').html(`<tr><td colspan="${displayColumns.length + 1}" class="text-center text-muted">No records found.</td></tr>`);
            $('#reportFoot').html('');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            html += `<tr><td>${index + 1}</td>`;

            $.each(keys, function (_, key) {
                let value = row[key];

                if ($.isNumeric(value) && key !== 'id') {
                    html += `<td class="text-end">${isMoneyKey(key) ? '₹' : ''}${formatNumber(value)}</td>`;
                } else {
                    html += `<td>${escapeHtml(value)}</td>`;
                }
            });

            html += '</tr>';
        });

        $('#reportBody').html(html);

        let totalKeys = Object.keys(totals || {});
        if (!totalKeys.length) {
            $('#reportFoot').html('');
            return;
        }

        let foot = '<tr><th>Total</th>';
        $.each(keys, function (_, key) {
            if (Object.prototype.hasOwnProperty.call(totals, key)) {
                foot += `<th class="text-end">₹${formatNumber(totals[key])}</th>`;
            } else {
                foot += '<th></th>';
            }
        });
        foot += '</tr>';

        $('#reportFoot').html(foot);
    }

    function exportCurrentReport() {
        if (!lastReportData || !(lastReportData.rows || []).length) {
            showToastSafe('warning', 'No report data to export.');
            return;
        }

        let rows = lastReportData.rows || [];
        let keys = Object.keys(rows[0]);
        let columns = lastReportData.columns && lastReportData.columns.length ? lastReportData.columns : keys.map(label);

        let csv = [];
        csv.push(columns.map(csvEscape).join(','));

        $.each(rows, function (_, row) {
            csv.push(keys.map(function (key) {
                return csvEscape(row[key]);
            }).join(','));
        });

        let blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        let link = document.createElement('a');
        let title = (lastReportData.title || 'report').replace(/[^a-z0-9]+/gi, '_').toLowerCase();

        link.href = url;
        link.download = title + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function csvEscape(value) {
        value = value === null || value === undefined ? '' : String(value);
        return '"' + value.replace(/"/g, '""') + '"';
    }

    function isMoneyKey(key) {
        return /(amount|total|tax|gst|value|paid|due|rate|cost|profit|outstanding|opening|current|sales|purchase|expense|stock|price|retail|wholesale)/i.test(key);
    }

    function label(key) {
        return String(key || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function formatNumber(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        if (typeof toastr !== 'undefined') {
            toastr[type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'error'](message);
            return;
        }

        console.log(type + ': ' + message);
    }
});
