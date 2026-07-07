<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Daily Ledger Report | Universal ERP';
$fromDate = date('Y-m-d');
$toDate = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .daily-ledger-shell .hero-card {
            border: 0;
            overflow: hidden;
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
            color: #fff;
        }
        .daily-ledger-shell .hero-card .text-muted { color: rgba(255,255,255,.78) !important; }
        .daily-ledger-shell .filter-card,
        .daily-ledger-shell .report-card { border: 0; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
        .daily-ledger-shell .summary-card { border: 1px solid var(--bs-border-color); border-radius: 14px; }
        .daily-ledger-table-wrap { max-height: 62vh; overflow: auto; }
        .daily-ledger-table-wrap thead th { position: sticky; top: 0; z-index: 1; background: var(--bs-light); }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .card { border: 0 !important; box-shadow: none !important; }
            .daily-ledger-table-wrap { max-height: none !important; overflow: visible !important; }
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
            <div class="container-fluid daily-ledger-shell">

                <div class="row no-print">
                    <div class="col-12">
                        <div class="card hero-card mb-3">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div>
                                        <h4 class="mb-1 text-white">Daily Ledger Report</h4>
                                        <p class="mb-0 text-muted">Sales, purchase, customer payment, supplier payment and expense entries in one daily ledger.</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-light" id="printReportBtn" onclick="window.print();">
                                        <i class="mdi mdi-printer me-1"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card filter-card no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                    <input type="text" class="form-control" id="reportSearch" placeholder="Bill No / Party / Reference">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                                    <i class="mdi mdi-filter me-1"></i> Load Report
                                </button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <label class="form-label mb-2">Entry Type</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check" id="typeAll" value="all" checked>
                                        <label class="form-check-label" for="typeAll">All</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check ledger-type-item" id="typeSales" value="sales" checked>
                                        <label class="form-check-label" for="typeSales">Sales</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check ledger-type-item" id="typeCustomerPayment" value="customer_payment" checked>
                                        <label class="form-check-label" for="typeCustomerPayment">Customer Payment</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check ledger-type-item" id="typePurchase" value="purchase" checked>
                                        <label class="form-check-label" for="typePurchase">Purchase</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check ledger-type-item" id="typeSupplierPayment" value="supplier_payment" checked>
                                        <label class="form-check-label" for="typeSupplierPayment">Supplier Payment</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input ledger-type-check ledger-type-item" id="typeExpense" value="expense" checked>
                                        <label class="form-check-label" for="typeExpense">Expense</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="printArea">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-primary" id="entryCount">0</h4>Total Entries</div></div>
                        </div>
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-success">₹<span id="salesTotal">0.00</span></h4>Sales</div></div>
                        </div>
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-info">₹<span id="customerPaymentTotal">0.00</span></h4>Customer Payment</div></div>
                        </div>
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-danger">₹<span id="purchaseTotal">0.00</span></h4>Purchase</div></div>
                        </div>
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-primary">₹<span id="supplierPaymentTotal">0.00</span></h4>Supplier Payment</div></div>
                        </div>
                        <div class="col-md-6 col-xl-2">
                            <div class="card summary-card text-center"><div class="card-body text-muted"><h4 class="text-warning">₹<span id="expenseTotal">0.00</span></h4>Expense</div></div>
                        </div>
                    </div>

                    <div class="card report-card mb-3">
                        <div class="card-header bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h5 class="mb-1">Daily Ledger Entries</h5>
                                    <small class="text-muted" id="reportPeriod">-</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary-subtle text-primary font-size-13" id="printDebitCredit">Debit: ₹0.00 | Credit: ₹0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive daily-ledger-table-wrap">
                                <table class="table table-bordered table-sm table-centered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Party</th>
                                            <th>Debit Account</th>
                                            <th>Credit Account</th>
                                            <th>Payment Mode</th>
                                            <th>Description</th>
                                            <th class="text-end">Debit</th>
                                            <th class="text-end">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledgerReportBody">
                                        <tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="9" class="text-end">Total</th>
                                            <th class="text-end">₹<span id="footerDebit">0.00</span></th>
                                            <th class="text-end">₹<span id="footerCredit">0.00</span></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card report-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Date Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-centered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-end">Sales</th>
                                            <th class="text-end">Customer Payment</th>
                                            <th class="text-end">Purchase</th>
                                            <th class="text-end">Supplier Payment</th>
                                            <th class="text-end">Expense</th>
                                            <th class="text-end">Debit</th>
                                            <th class="text-end">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dateSummaryBody">
                                        <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
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

<?php
$rightbarPath = BASE_PATH . 'includes/rightbar.php';
if (file_exists($rightbarPath)) {
    include $rightbarPath;
}

$scriptsPath = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath)) {
    include $scriptsPath;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/daily-ledger-report.js?v=<?= time(); ?>"></script>
</body>
</html>
