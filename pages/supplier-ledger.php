<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$supplierId = (int)($_GET['supplier_id'] ?? 0);
$page_title = 'Supplier Ledger | Universal ERP';
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
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
                                <h4 class="mb-0" id="pageTitleText">Supplier Ledger</h4>
                                <small class="text-muted" id="pageNoteText">Supplier debit / credit / balance statement</small>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="totalEntries">0</h3>
                                Total Entries
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="totalDebit">0.00</h3>
                                Total Debit / Paid
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="totalCredit">0.00</h3>
                                Total Credit / Purchase
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-info" id="closingBalance">0.00</h3>
                                Closing Balance
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" id="ledgerFilterCard">
                    <div class="card-body">

                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" id="supplier_id">
                                    <option value="">Select Supplier</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" id="fromDate">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" id="toDate">
                            </div>

                            <div class="col-md-4 text-end">
                                <button class="btn btn-light" id="refreshLedgerBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>

                                <button class="btn btn-outline-primary d-none" id="printLedgerBtn" type="button">
                                    <i class="mdi mdi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Particular</th>
                                        <th>Reference</th>
                                        <th>Type</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Balance</th>
                                    </tr>
                                </thead>

                                <tbody id="ledgerTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Select supplier.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <?php include BASE_PATH . 'includes/footer.php'; ?>

    </div>
</div>

<?php
$rightbarPath1 = BASE_PATH . 'includes/rightbar.php';

if (file_exists($rightbarPath1)) {
    include $rightbarPath1;
}
?>

<?php
$scriptsPath1 = BASE_PATH . 'includes/scripts.php';

if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "supplier-ledger";
    window.PRE_SUPPLIER_ID = <?= (int)$supplierId; ?>;
</script>

<script src="<?= BASE_URL; ?>pages-js/supplier-ledger.js?v=<?= time(); ?>"></script>

</body>
</html>
