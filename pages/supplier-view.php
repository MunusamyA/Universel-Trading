<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$supplierId = (int)($_GET['id'] ?? $_GET['supplier_id'] ?? 0);
$page_title = 'Supplier View | Universal ERP';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .view-label { color:#74788d; font-size:12px; margin-bottom:3px; }
        .view-value { font-weight:600; }
        .summary-card .card-body { padding:16px; }
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
                                <h4 class="mb-0">Supplier View</h4>
                                <p class="text-muted mb-0 mt-1">Supplier details, bank details and purchase history</p>
                            </div>
                            <div>
                                <a href="<?= BASE_URL; ?>pages/suppliers.php" class="btn btn-light me-1">
                                    <i class="mdi mdi-arrow-left me-1"></i> Supplier List
                                </a>
                                <button type="button" class="btn btn-primary" onclick="window.print();">
                                    <i class="mdi mdi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="supplierId" value="<?= (int)$supplierId; ?>">

                <div id="supplierViewAlert" class="alert alert-info">Loading supplier details...</div>

                <div id="supplierViewContent" style="display:none;">

                    <div class="row">
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-warning" id="openingOutstandingCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Opening Outstanding</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-info" id="purchaseAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Purchase Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-success" id="paidAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Paid Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-danger" id="dueAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Due Amount</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="mb-1" id="supplierNameText">-</h4>
                                    <div id="supplierStatusBadge"></div>
                                </div>
                                <div class="no-print" id="supplierTopActions"></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Mobile</div>
                                    <div class="view-value" id="mobileText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Email</div>
                                    <div class="view-value" id="emailText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">GST Number</div>
                                    <div class="view-value" id="gstText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">PAN Number</div>
                                    <div class="view-value" id="panText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">DL Number</div>
                                    <div class="view-value" id="dlText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">FL Number</div>
                                    <div class="view-value" id="flText">-</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="view-label">Address</div>
                                    <div class="view-value" id="addressText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Bank Details</h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Bank Name</div>
                                    <div class="view-value" id="bankNameText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Account No</div>
                                    <div class="view-value" id="accountNoText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Branch</div>
                                    <div class="view-value" id="bankBranchText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">IFSC</div>
                                    <div class="view-value" id="ifscText">-</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="view-label">UPI / QR Details</div>
                                    <div class="view-value" id="upiText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-size-15 mb-0">Purchase List</h5>
                                <span class="badge bg-info" id="purchaseCountBadge">0 Purchases</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Bill No</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Paid</th>
                                            <th class="text-end">Due</th>
                                            <th>Status</th>
                                            <th class="text-end no-print" width="170">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseTableBody">
                                        <tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>
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
if (file_exists($rightbarPath)) { include $rightbarPath; }

$scriptsPath = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath)) { include $scriptsPath; }
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "suppliers";
</script>
<script src="<?= BASE_URL; ?>pages-js/supplier-view.js?v=<?= time(); ?>"></script>

</body>
</html>
