<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Purchases | Universal ERP';
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
                            <h4 class="mb-0">Purchases</h4>
                            <a href="<?= BASE_URL; ?>pages/purchase-form.php" class="btn btn-primary">
                                <i class="mdi mdi-plus me-1"></i> Add Purchase
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-primary" id="totalPurchasesCount">0</h3>Total Purchases
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-success" id="totalAmount">0.00</h3>Total Amount
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-info" id="paidAmount">0.00</h3>Paid Amount
                        </div></div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center"><div class="card-body text-muted">
                            <h3 class="text-danger" id="dueAmount">0.00</h3>Due Amount
                        </div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?= csrfTokenInput(); ?>

                        <div class="row align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="purchaseSearch" placeholder="Bill No / Supplier">
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
                                <select class="form-select" id="purchaseStatusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-light" id="refreshPurchasesBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bill No</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                                </thead>
                                <tbody id="purchaseTableBody">
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

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/purchases.js?v=<?= time(); ?>"></script>
</body>
</html>
