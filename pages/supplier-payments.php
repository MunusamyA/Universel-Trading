<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$supplierId = (int)($_GET['supplier_id'] ?? 0);
$purchaseId = (int)($_GET['purchase_id'] ?? 0);
$page_title = 'Supplier Payments | Universal ERP';
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
                                <h4 class="mb-0" id="pageTitleText">Supplier Payments</h4>
                                <p class="text-muted mb-0 mt-1" id="pageNoteText">Supplier payment entry and history</p>
                            </div>

                            <div>
                                <a href="<?= BASE_URL; ?>pages/supplier-ledger.php<?= $supplierId > 0 ? '?supplier_id=' . $supplierId : ''; ?>" class="btn btn-outline-info me-2 d-none" id="ledgerBtn">
                                    <i class="mdi mdi-book-open-page-variant me-1"></i> Ledger
                                </a>

                                <a href="<?= BASE_URL; ?>pages/purchases.php" class="btn btn-light d-none" id="backBtn">
                                    <i class="mdi mdi-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="openingDue">0.00</h3>
                                Opening Due
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="purchaseDue">0.00</h3>
                                Purchase Due
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-info" id="purchasePaid">0.00</h3>
                                Purchase Paid
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="totalPayable">0.00</h3>
                                Total Payable
                            </div>
                        </div>
                    </div>
                </div>

                <form id="supplierPaymentForm" autocomplete="off">
                    <?= csrfTokenInput(); ?>

                    <input type="hidden" id="payment_id" name="payment_id" value="0">
                    <input type="hidden" id="payment_splits_json" name="payment_splits_json" value="">

                    <div class="card" id="paymentEntryCard">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Entry</h5>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Supplier *</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">Select Supplier</option>
                                    </select>
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Type</label>

                                    <select class="form-select" id="payment_type" name="payment_type">
                                        <option value="1">Overall Payment</option>
                                        <option value="2">Individual Purchase Bill</option>
                                        <option value="3">Opening Outstanding Payment</option>
                                    </select>

                                    <small class="text-muted" id="paymentTypeHelp">Overall: FIFO purchase due first, opening outstanding last.</small>
                                </div>

                                <div class="col-md-3 mb-3" id="purchaseBillBox">
                                    <label class="form-label">Purchase Bill</label>
                                    <select class="form-select" id="purchase_id" name="purchase_id">
                                        <option value="">Select Bill</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Amount *</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control text-end" id="total_amount" name="total_amount" value="0.00" placeholder="Enter payment amount">
                                    <small class="text-muted" id="amountLimitHelp">Select supplier and payment type. Amount can be edited for partial payment.</small>
                                </div>

                                <div class="col-md-9 mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" class="form-control" id="notes" name="notes">
                                </div>
                            </div>

                            <div class="border rounded p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="mb-0">Payment Split</h6>

                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addSplitBtn">
                                        <i class="mdi mdi-plus me-1"></i> Add Split
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-2">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Payment Mode</th>
                                                <th>Amount</th>
                                                <th>Reference No</th>
                                                <th width="50">#</th>
                                            </tr>
                                        </thead>

                                        <tbody id="paymentSplitsBody">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">Enter amount.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="text-end small">
                                    Split Total:
                                    <strong>₹<span id="splitTotal">0.00</span></strong>
                                    <span class="ms-2">
                                        Balance:
                                        <strong class="text-danger">₹<span id="splitBalance">0.00</span></strong>
                                    </span>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-light" id="resetPaymentBtn">Reset</button>
                                <button type="submit" class="btn btn-primary" id="savePaymentBtn">Save Payment</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="card" id="paymentHistoryCard">
                    <div class="card-header">
                        <h5 class="mb-0">Payment History</h5>
                    </div>

                    <div class="card-body">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-2">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" id="fromDate">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" id="toDate">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-6 text-end">
                                <button class="btn btn-light" type="button" id="refreshPaymentsBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Payment No</th>
                                        <th>Date</th>
                                        <th>Supplier</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Split</th>
                                        <th>Allocation</th>
                                        <th>Status</th>
                                        <th width="140">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="paymentsTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">Loading...</td>
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
    window.MASTER_FILE = "supplier-payments";
    window.PRE_SUPPLIER_ID = <?= (int)$supplierId; ?>;
    window.PRE_PURCHASE_ID = <?= (int)$purchaseId; ?>;
</script>

<script src="<?= BASE_URL; ?>pages-js/supplier-payments.js?v=<?= time(); ?>"></script>

</body>
</html>
