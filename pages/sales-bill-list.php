<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Sales Bill List' . ' | Universal ERP';
$listTitle = 'Sales Bill List';
$listDescription = 'List of sales bill documents';
$currentDocumentType = 3;
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
                                <h4 class="mb-0" id="salesListTitle"><?= htmlspecialchars($listTitle); ?></h4>
                                <small class="text-muted" id="salesListDescription"><?= htmlspecialchars($listDescription); ?></small>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= BASE_URL; ?>pages/sales.php" class="btn btn-primary d-none" id="newSalesEntryBtn">
                                    <i class="mdi mdi-plus me-1"></i> New Sales Entry
                                </a>

                                <a href="<?= BASE_URL; ?>pages/quotation-list.php" class="btn btn-outline-primary d-none sales-doc-nav" id="quotationListBtn" data-doc-type="1">
                                    <i class="mdi mdi-file-edit-outline me-1"></i> Quotation
                                </a>

                                <a href="<?= BASE_URL; ?>pages/proforma-bill-list.php" class="btn btn-outline-info d-none sales-doc-nav" id="proformaListBtn" data-doc-type="2">
                                    <i class="mdi mdi-file-document-outline me-1"></i> Proforma
                                </a>

                                <a href="<?= BASE_URL; ?>pages/sales-list.php" class="btn btn-outline-success d-none sales-doc-nav" id="salesBillListBtn" data-doc-type="3">
                                    <i class="mdi mdi-receipt-text-outline me-1"></i> Sales Bill
                                </a>

                                <a href="<?= BASE_URL; ?>pages/direct-sale-list.php" class="btn btn-outline-warning d-none sales-doc-nav" id="directSaleListBtn" data-doc-type="4">
                                    <i class="mdi mdi-cart-arrow-right me-1"></i> Direct Sale
                                </a>

                                <a href="<?= BASE_URL; ?>pages/final-invoice-list.php" class="btn btn-outline-dark d-none sales-doc-nav" id="finalInvoiceListBtn" data-doc-type="5">
                                    <i class="mdi mdi-receipt-text-check-outline me-1"></i> Final Invoice
                                </a>

                                <a href="<?= BASE_URL; ?>pages/all-sales-list.php" class="btn btn-light d-none" id="overallSalesListBtn">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Overall List
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="countCard">0</h3>
                                Total Documents
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="totalCard">₹0.00</h3>
                                Total Amount
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-info" id="paidCard">₹0.00</h3>
                                Paid Amount
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="dueCard">₹0.00</h3>
                                Due Amount
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">

                        <div class="row align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="searchText" placeholder="No / Customer / Mobile">
                            </div>

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
                                    <option value="">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Final</option>
                                    <option value="3">Deleted</option>
                                    <option value="4">Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-3 text-end">
                                <button class="btn btn-primary" id="filterBtn">
                                    <i class="mdi mdi-filter me-1"></i> Filter
                                </button>

                                <button class="btn btn-light" id="resetFilterBtn">
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Document No</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th class="text-end">Sub Total</th>
                                        <th class="text-end">GST</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th>Status</th>
                                        <th width="180" class="text-end">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="salesListBody">
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">Loading...</td>
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


<?= csrfTokenInput(); ?>

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
    window.SALES_LIST_CONFIG = {
        document_type: <?= (int)$currentDocumentType; ?>,
        document_types: {},
        permissions: {}
    };
</script>

<script src="<?= BASE_URL; ?>pages-js/sales-list.js?v=<?= time(); ?>"></script>

</body>
</html>
