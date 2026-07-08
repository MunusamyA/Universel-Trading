<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Dashboard | Universal Trading';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <link href="<?= BASE_URL; ?>assets/libs/c3/c3.min.css?v=<?= time(); ?>" rel="stylesheet" type="text/css" />
    <style>
        :root{
            --dash-radius: 16px;
            --dash-shadow: 0 8px 24px rgba(15,23,42,.08);
            --dash-border: 1px solid rgba(148,163,184,.16);
        }
        .dashboard-card{
            border: var(--dash-border);
            box-shadow: var(--dash-shadow);
            border-radius: var(--dash-radius);
            overflow:hidden;
            background:#fff;
            margin-bottom:16px;
            height:100%;
        }
        .dashboard-card .card-body{padding:18px;}
        .kpi-card .kpi-title{
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.05em;
            color:#64748b;
            font-weight:800;
            margin-bottom:6px;
        }
        .kpi-card .kpi-value{
            font-size:22px;
            font-weight:800;
            color:#0f172a;
            line-height:1.15;
        }
        .kpi-card .kpi-sub{
            color:#94a3b8;
            font-size:12px;
            margin-top:6px;
        }
        .kpi-icon{
            width:46px;height:46px;border-radius:14px;
            display:inline-flex;align-items:center;justify-content:center;
            font-size:24px;flex-shrink:0;
        }
        .bg-soft-primary{background:rgba(85,110,230,.14);color:#556ee6;}
        .bg-soft-success{background:rgba(52,195,143,.14);color:#34c38f;}
        .bg-soft-warning{background:rgba(241,180,76,.16);color:#f1b44c;}
        .bg-soft-info{background:rgba(80,165,241,.14);color:#50a5f1;}
        .chart-card-title{
            font-size:14px;
            font-weight:800;
            color:#111827;
            margin-bottom:0;
        }
        .chart-card-subtitle{
            color:#94a3b8;
            font-size:12px;
            margin-top:3px;
            margin-bottom:0;
        }
        .chart-stat{text-align:center;margin-bottom:12px;}
        .chart-stat .value{font-size:18px;font-weight:800;color:#0f172a;line-height:1.1;}
        .chart-stat .label{color:#64748b;font-size:11px;margin-top:3px;}
        .chart-wrap{height:270px;min-height:270px;overflow:hidden;}
        .chart-wrap-sm{height:240px;min-height:240px;overflow:hidden;}
        .chart-wrap > div, .chart-wrap-sm > div{width:100%;height:100%;min-width:0;}
        .c3{max-width:100%;}
        .c3 svg{max-width:100%;}
        .c3 svg{font-family:inherit;font-size:11px;}
        .c3-tooltip-container{z-index:50;}
        .quick-grid{
            display:grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap:10px;
        }
        .quick-btn{
            min-height:72px;border-radius:14px;display:flex;flex-direction:column;
            align-items:center;justify-content:center;gap:5px;font-weight:700;text-align:center;
        }
        .list-row{
            display:flex;justify-content:space-between;align-items:center;gap:12px;
            padding:10px 0;border-bottom:1px solid #eef2f7;
        }
        .list-row:last-child{border-bottom:0;padding-bottom:0;}
        .text-clip{
            max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
        }
        .table > :not(caption) > * > *{vertical-align:middle;padding:.64rem .75rem;}
        .table thead th{
            font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#64748b;
        }
        .badge-soft{background:#f8fafc;color:#334155;border:1px solid #e2e8f0;}
        .dashboard-loading{
            min-height: 120px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#64748b;
        }
        @media (max-width:767px){
            .kpi-card .kpi-value{font-size:18px;}
            .chart-wrap{height:240px;min-height:240px;}
            .chart-wrap-sm{height:220px;min-height:220px;}
            .quick-grid{grid-template-columns:1fr;}
        }
    </style>
</head>

<body data-sidebar="dark">
<?php include BASE_PATH . 'includes/pre-loader.php'; ?>

<div id="layout-wrapper">
    <?php include BASE_PATH . 'includes/topbar.php'; ?>

    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include BASE_PATH . 'includes/sidebar.php'; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-0">Dashboard</h4>
                                <p class="text-muted mb-0 mt-1">
                                    API based dashboard with branch wise and overall business data.
                                </p>
                            </div>

                            <div class="page-title-right">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div id="dashboardBusinessWrap" class="d-none">
                                        <div class="input-group input-group-sm" style="width: 285px;">
                                            <span class="input-group-text"><i class="mdi mdi-office-building"></i></span>
                                            <select class="form-select" id="dashboardBusinessSelect">
                                                <option value="">Loading...</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="dashboardBranchWrap" class="d-none">
                                        <div class="input-group input-group-sm" style="width: 275px;">
                                            <span class="input-group-text"><i class="mdi mdi-source-branch"></i></span>
                                            <select class="form-select" id="dashboardBranchSelect">
                                                <option value="">Loading...</option>
                                            </select>
                                        </div>
                                    </div>

                                    <span id="dashboardBranchBadge" class="badge bg-light text-dark border px-3 py-2 d-none">
                                        <i class="mdi mdi-source-branch me-1"></i> <span id="dashboardBranchName">Branch</span>
                                    </span>

                                    <button type="button" class="btn btn-light" id="refreshDashboardBtn">
                                        <i class="mdi mdi-refresh me-1"></i> Refresh
                                    </button>

                                    <a href="<?= BASE_URL; ?>pages/sales.php" class="btn btn-primary business-only-action" id="dashboardNewSaleBtn">
                                        <i class="mdi mdi-cart-plus me-1"></i> New Sale
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="dashboardLoading" class="card dashboard-card">
                    <div class="dashboard-loading">
                        <div>
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading dashboard...
                        </div>
                    </div>
                </div>

                <div id="dashboardContent" class="d-none">

                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card dashboard-card kpi-card mb-0">
                                <div class="card-body d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-title">Today Sales</div>
                                        <div class="kpi-value" id="todaySales">₹0.00</div>
                                        <div class="kpi-sub"><span id="todaySalesCount">0</span> final/direct bills</div>
                                    </div>
                                    <div class="kpi-icon bg-soft-primary"><i class="mdi mdi-cash-register"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card dashboard-card kpi-card mb-0">
                                <div class="card-body d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-title">Month Sales</div>
                                        <div class="kpi-value" id="monthSales">₹0.00</div>
                                        <div class="kpi-sub"><span id="monthSalesCount">0</span> sales documents</div>
                                    </div>
                                    <div class="kpi-icon bg-soft-success"><i class="mdi mdi-chart-line"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card dashboard-card kpi-card mb-0">
                                <div class="card-body d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-title">Receivable Due</div>
                                        <div class="kpi-value" id="pendingReceivable">₹0.00</div>
                                        <div class="kpi-sub">Pending customer amount</div>
                                    </div>
                                    <div class="kpi-icon bg-soft-warning"><i class="mdi mdi-account-cash"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <div class="card dashboard-card kpi-card mb-0">
                                <div class="card-body d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="kpi-title">Today Collection</div>
                                        <div class="kpi-value" id="todayCollection">₹0.00</div>
                                        <div class="kpi-sub">Received today</div>
                                    </div>
                                    <div class="kpi-icon bg-soft-info"><i class="mdi mdi-wallet-plus"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-6">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <h5 class="chart-card-title">Area Chart</h5>
                                        <p class="chart-card-subtitle">Sales trend overview</p>
                                    </div>
                                    <div class="row">
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="areaMonthSales">₹0.00</div><div class="label">Month Sales</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="areaTodaySales">₹0.00</div><div class="label">Today Sales</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="areaReceivable">₹0.00</div><div class="label">Receivable</div></div></div>
                                    </div>
                                    <div class="chart-wrap"><div id="areaChart"></div></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <h5 class="chart-card-title">Donut Chart</h5>
                                        <p class="chart-card-subtitle">Document distribution</p>
                                    </div>
                                    <div class="row">
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="quotationCount">0</div><div class="label">Quotation</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="salesBillCount">0</div><div class="label">Sales Bill</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="finalInvoiceCount">0</div><div class="label">Final Invoice</div></div></div>
                                    </div>
                                    <div class="chart-wrap"><div id="donutChart"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-6">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <h5 class="chart-card-title">Bar Chart</h5>
                                        <p class="chart-card-subtitle">Monthly value split</p>
                                    </div>
                                    <div class="row">
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="monthPurchase">₹0.00</div><div class="label">Purchase</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="monthExpense">₹0.00</div><div class="label">Expense</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="stockValue">₹0.00</div><div class="label">Stock Value</div></div></div>
                                    </div>
                                    <div class="chart-wrap-sm"><div id="barChart"></div></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <div class="mb-2">
                                        <h5 class="chart-card-title">Stacked Area Chart</h5>
                                        <p class="chart-card-subtitle">Sales vs collection trend</p>
                                    </div>
                                    <div class="row">
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="stackTodayCollection">₹0.00</div><div class="label">Collection</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="quotationValue">₹0.00</div><div class="label">Quotation Value</div></div></div>
                                        <div class="col-4"><div class="chart-stat"><div class="value" id="finalInvoiceValue">₹0.00</div><div class="label">Invoice Value</div></div></div>
                                    </div>
                                    <div class="chart-wrap-sm"><div id="stackedAreaChart"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-4" id="quickActionsCardWrap">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <h5 class="chart-card-title">Quick Actions</h5>
                                    <p class="chart-card-subtitle">Common shortcuts</p>
                                    <div class="quick-grid mt-3" id="quickActions"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4" id="topDueCustomersCardWrap">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <h5 class="chart-card-title">Top Due Customers</h5>
                                    <p class="chart-card-subtitle">Highest pending balances</p>
                                    <div class="mt-2" id="topDueCustomers"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="card dashboard-card">
                                <div class="card-body">
                                    <h5 class="chart-card-title">Low Stock Products</h5>
                                    <p class="chart-card-subtitle">At or below minimum stock</p>
                                    <div class="mt-2" id="lowStockProducts"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card dashboard-card mt-1" id="recentSalesCardWrap">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h5 class="chart-card-title">Recent Sales Documents</h5>
                                    <p class="chart-card-subtitle">Latest quotation, proforma, sales bill and invoice entries</p>
                                </div>
                                <a href="<?= BASE_URL; ?>pages/sales-list.php" class="btn btn-sm btn-light business-only-action" id="recentSalesViewAllBtn">View All</a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-centered table-nowrap mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Type</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Due</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody id="recentSalesTable">
                                        <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
</script>
<script src="<?= BASE_URL; ?>assets/libs/d3/d3.min.js?v=<?= time(); ?>"></script>
<script src="<?= BASE_URL; ?>assets/libs/c3/c3.min.js?v=<?= time(); ?>"></script>
<script src="<?= BASE_URL; ?>pages-js/dashboard.js?v=<?= time(); ?>"></script>
</body>
</html>
