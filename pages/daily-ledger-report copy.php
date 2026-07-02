<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Daily Ledger Report | Universal ERP';
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .card { border: 0 !important; box-shadow: none !important; }
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

                <div class="row no-print">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Daily Ledger Report</h4>
                            <button class="btn btn-outline-primary" type="button" onclick="window.print()">
                                <i class="mdi mdi-printer me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row no-print">
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-primary" id="entryCount">0</h3>Entries
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-success" id="salesTotal">0.00</h3>Sales
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-info" id="customerPaymentTotal">0.00</h3>Cust. Payment
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-danger" id="purchaseTotal">0.00</h3>Purchase
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-warning" id="supplierPaymentTotal">0.00</h3>Supp. Payment
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-dark" id="expenseTotal">0.00</h3>Expense
                        </div></div>
                    </div>
                </div>

                <div class="card no-print">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="fromDate" value="<?= $today; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="toDate" value="<?= $today; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" id="entryType">
                                    <option value="all">All</option>
                                    <option value="sales">Sales</option>
                                    <option value="customer_payment">Customer Payment</option>
                                    <option value="purchase">Purchase</option>
                                    <option value="supplier_payment">Supplier Payment</option>
                                    <option value="expense">Expense</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="reportSearch" placeholder="Bill No / Party / Reference">
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-primary w-100" type="button" id="loadReportBtn">
                                    <i class="mdi mdi-filter me-1"></i> Filter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="printArea">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Daily Ledger Report</h5>
                                    <small class="text-muted" id="reportPeriod">Date: <?= $today; ?></small>
                                </div>
                                <div class="text-end">
                                    <strong id="printDebitCredit">Debit: ₹0.00 | Credit: ₹0.00</strong><br>
                                    <small class="text-muted">Double Entry Ledger View</small>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <h6 class="mb-3">Date-wise Summary</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Sales</th>
                                        <th class="text-end">Cust. Payment</th>
                                        <th class="text-end">Purchase</th>
                                        <th class="text-end">Supp. Payment</th>
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

                            <h6 class="mb-3">Ledger Entries</h6>
                            <div class="table-responsive">
                                <table class="table table-centered table-bordered table-sm mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Entry Type</th>
                                        <th>Reference</th>
                                        <th>Party</th>
                                        <th>Debit A/c</th>
                                        <th>Credit A/c</th>
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
                                        <th class="text-end" id="footerDebit">0.00</th>
                                        <th class="text-end" id="footerCredit">0.00</th>
                                    </tr>
                                    </tfoot>
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
<script src="<?= BASE_URL; ?>pages-js/daily-ledger-report.js?v=<?= time(); ?>"></script>
</body>
</html>
