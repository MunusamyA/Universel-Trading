<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$purchaseId = (int)($_GET['id'] ?? $_GET['purchase_id'] ?? 0);
$printMode = (int)($_GET['print'] ?? 0);
$page_title = 'Purchase View | Universal ERP';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .view-label { color:#74788d; font-size:12px; margin-bottom:3px; }
        .view-value { font-weight:600; }
        @media print {
            .vertical-menu, .navbar-header, .footer, .no-print, .page-title-box .btn { display:none !important; }
            .main-content { margin-left:0 !important; }
            .page-content { padding:0 !important; }
            .card { box-shadow:none !important; border:1px solid #ddd !important; }
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
                            <div>
                                <h4 class="mb-0">Purchase View</h4>
                                <p class="text-muted mb-0 mt-1">Read-only purchase details</p>
                            </div>
                            <div>
                                <a href="<?= BASE_URL; ?>pages/purchases.php" class="btn btn-light me-1">
                                    <i class="mdi mdi-arrow-left me-1"></i> Purchase List
                                </a>
                                <button type="button" class="btn btn-primary" onclick="window.print();">
                                    <i class="mdi mdi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="purchaseId" value="<?= (int)$purchaseId; ?>">
                <input type="hidden" id="printMode" value="<?= (int)$printMode; ?>">

                <div id="purchaseViewAlert" class="alert alert-info">Loading purchase details...</div>

                <div id="purchaseViewContent" style="display:none;">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="mb-1" id="billNoText">-</h4>
                                    <div id="purchaseStatusBadge"></div>
                                </div>
                                <div class="text-end">
                                    <h4 class="text-danger mb-0" id="dueAmountTop">₹0.00</h4>
                                    <small class="text-muted">Due Amount</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Purchase Date</div>
                                    <div class="view-value" id="purchaseDateText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Due Date</div>
                                    <div class="view-value" id="dueDateText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Supplier</div>
                                    <div class="view-value" id="supplierNameText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Batch No</div>
                                    <div class="view-value" id="batchNoText">-</div>
                                </div>
                                <div class="col-md-12 mb-2">
                                    <div class="view-label">Notes</div>
                                    <div class="view-value" id="notesText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Purchase Items</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>HSN</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Rate</th>
                                            <th class="text-end">Discount</th>
                                            <th class="text-end">Tax</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
    
                    <div class="card">
                        <div class="card-body">
                            <div class="row justify-content-end">
                                <div class="col-md-5 col-lg-4">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr><th>Sub Total</th><td class="text-end" id="subTotalText">₹0.00</td></tr>
                                            <tr><th>Discount</th><td class="text-end" id="discountText">₹0.00</td></tr>
                                            <tr><th>Tax Amount</th><td class="text-end" id="taxText">₹0.00</td></tr>
                                            <tr><th>Round Off</th><td class="text-end" id="roundOffText">₹0.00</td></tr>
                                            <tr class="table-light"><th>Grand Total</th><td class="text-end fw-bold" id="grandTotalText">₹0.00</td></tr>
                                            <tr><th>Paid</th><td class="text-end text-success" id="paidText">₹0.00</td></tr>
                                            <tr><th>Due</th><td class="text-end text-danger fw-bold" id="dueText">₹0.00</td></tr>
                                        </tbody>
                                    </table>
                                </div>
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
if (file_exists($rightbarPath)) { include $rightbarPath; }

$scriptsPath = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath)) { include $scriptsPath; }
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "purchases";
</script>
<script src="<?= BASE_URL; ?>pages-js/purchase-view.js?v=<?= time(); ?>"></script>

</body>
</html>
