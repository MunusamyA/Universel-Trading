<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Customers | Universal ERP';
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
</head>

<body data-sidebar="dark">

<?php include BASE_PATH . 'includes/pre-loader.php'; ?>

<?= csrfTokenInput(); ?>

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
                                <h4 class="mb-0" id="pageTitleText">Customers</h4>
                                <p class="text-muted mb-0 mt-1" id="pageNoteText">Loading...</p>
                            </div>

                            <div class="page-title-right">
                                <a href="<?= BASE_URL; ?>pages/customers-create.php" class="btn btn-primary d-none" id="addCustomerBtn">
                                    <i class="mdi mdi-plus me-1"></i>
                                    <span id="addCustomerBtnText">Add Customer</span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="totalCustomersCount">0</h3>
                                Total Customers
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="activeCustomersCount">0</h3>
                                Active Customers
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="inactiveCustomersCount">0</h3>
                                Inactive Customers
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-warning" id="totalOpeningBalanceAmount">₹0.00</h3>
                                Opening Balance
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-info" id="overallSalesAmount">₹0.00</h3>
                                Overall Sales
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="paidAmount">₹0.00</h3>
                                Paid Amount
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="dueAmount">₹0.00</h3>
                                Due Amount
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">

                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="customerSearch" placeholder="Name / Mobile / GST / Zone">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Zone</label>
                                <select class="form-select" id="zoneFilter">
                                    <option value="0">All Zones</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="customerStatusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-3 text-end">
                                <button type="button" class="btn btn-light" id="refreshCustomersBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Customer</th>
                                        <th>Zone</th>
                                        <th>Contact</th>
                                        <th>GST</th>
                                        <th class="text-end">Opening Balance</th>
                                        <th class="text-end">Overall Sales</th>
                                        <th class="text-end">Paid Amount</th>
                                        <th class="text-end">Due Amount</th>
                                        <th>Status</th>
                                        <th width="230" class="text-end">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="customerTableBody">
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
    window.MASTER_FILE = "customers";
</script>

<script src="<?= BASE_URL; ?>pages-js/customers.js?v=<?= time(); ?>"></script>

</body>
</html>
