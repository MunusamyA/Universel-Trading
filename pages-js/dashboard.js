$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let currentBusinessId = null;
    let currentBranchId = null;
    let currentIsPlatform = false;
    let currentShowOperations = true;
    let areaChart = null;
    let donutChart = null;
    let barChart = null;
    let stackedAreaChart = null;

    function money(value) {
        return '₹' + Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function numberFormat(value, decimals) {
        decimals = decimals || 0;
        return Number(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[match];
        });
    }

    function showLoading() {
        $('#dashboardLoading').removeClass('d-none');
        $('#dashboardContent').addClass('d-none');
    }

    function hideLoading() {
        $('#dashboardLoading').addClass('d-none');
        $('#dashboardContent').removeClass('d-none');
    }

    function renderBranchContext(context) {
        context = context || {};
        currentBusinessId = parseInt(context.business_id || 0, 10);
        currentBranchId = parseInt(context.branch_id || 0, 10);
        currentIsPlatform = parseInt(context.is_platform || 0, 10) === 1;
        currentShowOperations = parseInt(context.show_operations || 0, 10) === 1;

        if (currentShowOperations) {
            $('.business-only-action').removeClass('d-none');
            $('#quickActionsCardWrap, #topDueCustomersCardWrap, #recentSalesCardWrap').removeClass('d-none');
        } else {
            $('.business-only-action').addClass('d-none');
            $('#quickActionsCardWrap, #topDueCustomersCardWrap, #recentSalesCardWrap').addClass('d-none');
            $('#recentSalesTable').html('');
        }

        let scopeName = context.scope_name || context.branch_name || context.business_name || 'Dashboard';
        $('#dashboardBranchName').text(scopeName);

        if (!currentIsPlatform && parseInt(context.can_switch_business || 0, 10) === 1) {
            let businessOptions = '';
            (context.businesses || []).forEach(function (business) {
                let businessId = parseInt(business.id || 0, 10);
                let selected = businessId === currentBusinessId ? ' selected' : '';
                businessOptions += '<option value="' + businessId + '"' + selected + '>' + escapeHtml(business.label) + '</option>';
            });

            $('#dashboardBusinessSelect').html(businessOptions);
            $('#dashboardBusinessWrap').removeClass('d-none');
        } else {
            $('#dashboardBusinessSelect').html('');
            $('#dashboardBusinessWrap').addClass('d-none');
        }

        if (!currentIsPlatform && parseInt(context.can_switch_branch || 0, 10) === 1 && (context.branches || []).length > 0) {
            let branchOptions = '';
            (context.branches || []).forEach(function (branch) {
                let branchId = parseInt(branch.id || 0, 10);
                let selected = branchId === currentBranchId ? ' selected' : '';
                branchOptions += '<option value="' + branchId + '"' + selected + '>' + escapeHtml(branch.label) + '</option>';
            });

            $('#dashboardBranchSelect').html(branchOptions);
            $('#dashboardBranchWrap').removeClass('d-none');
        } else {
            $('#dashboardBranchWrap').addClass('d-none');
        }

        $('#dashboardBranchBadge').removeClass('d-none');
    }

    function renderMetrics(metrics, pipeline) {
        $('#todaySales').text(money(metrics.todaySales));
        $('#todaySalesCount').text(numberFormat(metrics.todaySalesCount));
        $('#monthSales').text(money(metrics.monthSales));
        $('#monthSalesCount').text(numberFormat(metrics.monthSalesCount));
        $('#pendingReceivable').text(money(metrics.pendingReceivable));
        $('#todayCollection').text(money(metrics.todayCollection));

        $('#areaMonthSales').text(money(metrics.monthSales));
        $('#areaTodaySales').text(money(metrics.todaySales));
        $('#areaReceivable').text(money(metrics.pendingReceivable));

        $('#monthPurchase').text(money(metrics.monthPurchase));
        $('#monthExpense').text(money(metrics.monthExpense));
        $('#stockValue').text(money(metrics.stockValue));

        $('#stackTodayCollection').text(money(metrics.todayCollection));
        $('#quotationValue').text(money(metrics.quotationValue));
        $('#finalInvoiceValue').text(money(metrics.finalInvoiceValue));

        $('#quotationCount').text(numberFormat(pipeline.quotationCount));
        $('#salesBillCount').text(numberFormat(pipeline.salesBillCount));
        $('#finalInvoiceCount').text(numberFormat(pipeline.finalInvoiceCount));
    }

    function renderCharts(charts, metrics) {
        if (typeof c3 === 'undefined') {
            showToast('error', 'C3 chart library missing.', 5000);
            return;
        }

        $('#areaChart, #donutChart, #barChart, #stackedAreaChart').empty();

        let salesLabels = charts.salesLabels || ['No Data'];
        let salesData = charts.salesData || [0];
        let collectionData = charts.collectionData || [0];
        let docLabels = charts.docLabels || [];
        let docCounts = charts.docCounts || [];
        let splitLabels = charts.splitLabels || ['Sales', 'Purchase', 'Expense'];
        let splitData = charts.splitData || [0, 0, 0];

        if (areaChart) { areaChart.destroy(); }
        if (donutChart) { donutChart.destroy(); }
        if (barChart) { barChart.destroy(); }
        if (stackedAreaChart) { stackedAreaChart.destroy(); }

        areaChart = c3.generate({
            bindto: '#areaChart',
            size: {height: 270},
            data: {
                x: 'x',
                columns: [
                    ['x'].concat(salesLabels),
                    ['Sales'].concat(salesData),
                    ['Invoice'].concat(salesData.map(function (v) { return Math.round(Number(v || 0) * 0.78); }))
                ],
                types: {
                    Sales: 'area-spline',
                    Invoice: 'area-spline'
                }
            },
            color: { pattern: ['#51b4d4', '#5b8ff9'] },
            axis: {
                x: { type: 'category', tick: { culling: { max: 8 } } },
                y: { tick: { count: 5, format: function (d) { return '₹' + Number(d || 0).toLocaleString('en-IN'); } } }
            },
            point: { show: false },
            legend: { show: false },
            grid: { y: { show: true } },
            transition: { duration: 0 },
            tooltip: { format: { value: function (v) { return money(v); } } },
            padding: { left: 78, right: 24 }
        });

        let donutColumns = [];
        for (let i = 0; i < docLabels.length; i++) {
            donutColumns.push([docLabels[i], Number(docCounts[i] || 0)]);
        }
        if (!donutColumns.length) {
            donutColumns = [['No Data', 1]];
        }

        donutChart = c3.generate({
            bindto: '#donutChart',
            size: {height: 270},
            data: {
                columns: donutColumns,
                type: 'donut'
            },
            donut: {
                title: 'Documents ' + numberFormat(metrics.totalPipelineCount),
                width: 22,
                label: { show: false }
            },
            color: { pattern: ['#3a8dd5', '#4db0c6', '#d8dde4', '#58c4e1', '#7e67c6'] },
            legend: { position: 'bottom' },
            transition: { duration: 0 },
            padding: { top: 5, right: 20, bottom: 5, left: 20 },
            tooltip: {
                format: {
                    value: function (value) {
                        return Number(value || 0).toLocaleString('en-IN') + ' docs';
                    }
                }
            }
        });

        barChart = c3.generate({
            bindto: '#barChart',
            size: {height: 240},
            data: {
                x: 'x',
                columns: [
                    ['x'].concat(splitLabels),
                    ['Amount'].concat(splitData)
                ],
                type: 'bar'
            },
            color: { pattern: ['#51b4d4'] },
            bar: { width: { ratio: 0.45 } },
            axis: {
                x: { type: 'category' },
                y: { tick: { count: 5, format: function (d) { return '₹' + Number(d || 0).toLocaleString('en-IN'); } } }
            },
            legend: { show: false },
            grid: { y: { show: true } },
            transition: { duration: 0 },
            tooltip: { format: { value: function (v) { return money(v); } } },
            padding: { left: 78, right: 24 }
        });

        stackedAreaChart = c3.generate({
            bindto: '#stackedAreaChart',
            size: {height: 240},
            data: {
                x: 'x',
                columns: [
                    ['x'].concat(salesLabels),
                    ['Revenue'].concat(salesData),
                    ['Collection'].concat(collectionData)
                ],
                types: {
                    Revenue: 'area-spline',
                    Collection: 'area-spline'
                },
                groups: [['Revenue', 'Collection']]
            },
            color: { pattern: ['#e4e9ef', '#51b4d4'] },
            axis: {
                x: { type: 'category', tick: { culling: { max: 8 } } },
                y: { tick: { count: 6, format: function (d) { return '₹' + Number(d || 0).toLocaleString('en-IN'); } } }
            },
            point: { r: 3 },
            legend: { position: 'bottom' },
            grid: { y: { show: true } },
            transition: { duration: 0 },
            tooltip: { format: { value: function (v) { return money(v); } } },
            padding: { left: 78, right: 24 }
        });

        setTimeout(function () {
            if (areaChart) { areaChart.flush(); }
            if (donutChart) { donutChart.flush(); }
            if (barChart) { barChart.flush(); }
            if (stackedAreaChart) { stackedAreaChart.flush(); }
        }, 120);
    }

    function renderListRows(target, rows, type) {
        let html = '';

        if (currentIsPlatform && type === 'due') {
            $(target).html('');
            $('#topDueCustomersCardWrap').addClass('d-none');
            return;
        }

        if (!rows || !rows.length) {
            html = '<p class="text-muted py-3 mb-0">No data found.</p>';
            $(target).html(html);
            return;
        }

        rows.forEach(function (row) {
            if (type === 'due') {
                html += '<div class="list-row">' +
                    '<div class="text-clip"><strong>' + escapeHtml(row.customer_name) + '</strong><br><small class="text-muted">' + escapeHtml(row.mobile || '') + '</small></div>' +
                    '<span class="text-danger fw-bold">' + money(row.current_outstanding) + '</span>' +
                    '</div>';
            } else {
                html += '<div class="list-row">' +
                    '<div class="text-clip"><strong>' + escapeHtml(row.product_name) + '</strong><br><small class="text-muted">' + escapeHtml(row.product_code || '') + '</small></div>' +
                    '<span class="text-danger fw-bold">' + numberFormat(row.current_stock, 2) + '</span>' +
                    '</div>';
            }
        });

        $(target).html(html);
    }

    function renderQuickLinks(rows) {
        let html = '';

        if (!currentShowOperations) {
            $('#quickActions').html('');
            $('#quickActionsCardWrap').addClass('d-none');
            return;
        }

        (rows || []).forEach(function (link) {
            html += '<a href="' + window.BASE_URL + escapeHtml(link.url) + '" class="btn ' + escapeHtml(link.class) + ' quick-btn">' +
                '<i class="' + escapeHtml(link.icon) + ' font-size-22"></i>' +
                '<span>' + escapeHtml(link.label) + '</span>' +
                '</a>';
        });

        $('#quickActions').html(html || '<p class="text-muted mb-0">No actions found.</p>');
    }

    function renderRecentSales(rows) {
        let html = '';

        if (!currentShowOperations) {
            $('#recentSalesCardWrap').addClass('d-none');
            $('#recentSalesTable').html('');
            return;
        }

        if (!rows || !rows.length) {
            $('#recentSalesTable').html('<tr><td colspan="7" class="text-center text-muted py-4">No sales documents found.</td></tr>');
            return;
        }

        rows.forEach(function (row) {
            let actionHtml = '<span class="text-muted small">View only</span>';
            if (currentShowOperations) {
                actionHtml = '<a class="btn btn-sm btn-outline-primary" href="' + window.BASE_URL + 'pages/sales.php?id=' + parseInt(row.id, 10) + '&mode=edit" title="Edit"><i class="mdi mdi-pencil"></i></a> ' +
                    '<a class="btn btn-sm btn-outline-danger" target="_blank" href="' + window.BASE_URL + 'pages/sales-print.php?id=' + parseInt(row.id, 10) + '&print=1" title="Print"><i class="mdi mdi-file-pdf-box"></i></a>';
            }

            html += '<tr>' +
                '<td><strong>' + escapeHtml(row.sales_no) + '</strong></td>' +
                '<td><span class="badge badge-soft">' + escapeHtml(row.document_label) + '</span></td>' +
                '<td>' + escapeHtml(row.customer_name) + '</td>' +
                '<td>' + escapeHtml(row.sales_date) + '</td>' +
                '<td class="text-end">' + money(row.grand_total) + '</td>' +
                '<td class="text-end text-danger">' + money(row.due_amount) + '</td>' +
                '<td class="text-center">' + actionHtml + '</td>' +
                '</tr>';
        });

        $('#recentSalesTable').html(html);
    }

    function loadDashboard(businessId, branchId) {
        showLoading();

        let requestData = {
            action: 'load_dashboard'
        };

        if (businessId !== null && businessId !== undefined && businessId !== '') {
            requestData.business_id = businessId;
        }

        if (branchId !== null && branchId !== undefined && branchId !== '') {
            requestData.branch_id = branchId;
        }

        $.ajax({
            url: window.BASE_URL + 'api/dashboard.php',
            type: 'GET',
            dataType: 'json',
            data: requestData,
            success: function (response) {
                if (response.status === true) {
                    let data = response.data || {};

                    renderBranchContext(data.context || {});
                    renderMetrics(data.metrics || {}, data.pipeline || {});
                    renderQuickLinks(data.quickLinks || []);
                    renderListRows('#topDueCustomers', data.topDueCustomers || [], 'due');
                    renderListRows('#lowStockProducts', data.lowStockProducts || [], 'stock');
                    renderRecentSales(data.recentSales || []);

                    // Important: show the dashboard before drawing C3 charts.
                    // C3 calculates wrong width when parent is display:none.
                    hideLoading();

                    setTimeout(function () {
                        renderCharts(data.charts || {}, data.metrics || {});
                    }, 80);
                } else {
                    $('#dashboardLoading').html('<div class="dashboard-loading text-danger">' + escapeHtml(response.message || 'Unable to load dashboard.') + '</div>');
                    if (typeof handleApiError === 'function') {
                        handleApiError(response);
                    }
                }
            },
            error: function () {
                $('#dashboardLoading').html('<div class="dashboard-loading text-danger">Server error. Unable to load dashboard.</div>');
                if (typeof showToast === 'function') {
                    showToast('error', 'Server error. Unable to load dashboard.', 5000);
                }
            }
        });
    }

    $('#dashboardBusinessSelect').on('change', function () {
        loadDashboard($(this).val(), 0);
    });

    $('#dashboardBranchSelect').on('change', function () {
        loadDashboard(currentBusinessId, $(this).val());
    });

    $('#refreshDashboardBtn').on('click', function () {
        loadDashboard(currentBusinessId, currentBranchId);
    });

    let chartResizeTimer = null;
    $(window).on('resize', function () {
        clearTimeout(chartResizeTimer);
        chartResizeTimer = setTimeout(function () {
            if (areaChart) { areaChart.flush(); }
            if (donutChart) { donutChart.flush(); }
            if (barChart) { barChart.flush(); }
            if (stackedAreaChart) { stackedAreaChart.flush(); }
        }, 200);
    });

    loadDashboard(null, null);
});
