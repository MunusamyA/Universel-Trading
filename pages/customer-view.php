<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$customerId = (int)($_GET['id'] ?? $_GET['customer_id'] ?? 0);
$page_title = 'Customer View | Universal ERP';
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .customer-view-summary .card-body {
            padding: 14px 12px;
        }
        .customer-view-summary h4 {
            margin-bottom: 2px;
            font-size: 20px;
        }
        .customer-profile-line {
            line-height: 1.8;
        }
        .sales-action-group .btn {
            margin-left: 3px;
        }
        @media print {
            .vertical-menu,
            .navbar-header,
            .page-title-right,
            .footer,
            #backToCustomersBtn,
            .sales-action-group {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .page-content {
                padding: 10px !important;
            }
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
                                <h4 class="mb-0" id="customerViewTitle">Customer View</h4>
                                <p class="text-muted mb-0 mt-1" id="customerViewNote">Sales details and payment status</p>
                            </div>

                            <div class="page-title-right">
                                <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light" id="backToCustomersBtn">
                                    <i class="mdi mdi-arrow-left me-1"></i> Customers
                                </a>
                                <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
                                    <i class="mdi mdi-printer me-1"></i> Print Page
                                </button>
                                <a href="#" class="btn btn-success d-none" id="receiveCustomerPaymentBtn">
                                    <i class="mdi mdi-cash-plus me-1"></i> Receive Payment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row customer-view-summary">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h4 class="text-warning" id="viewOpeningBalance">₹0.00</h4>
                                Opening Balance
                                <div><small class="text-muted" id="viewOpeningDueText">Due: ₹0.00</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h4 class="text-info" id="viewOverallSales">₹0.00</h4>
                                Overall Sales
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h4 class="text-success" id="viewPaidAmount">₹0.00</h4>
                                Paid Amount
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h4 class="text-danger" id="viewDueAmount">₹0.00</h4>
                                Due Amount
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="font-size-15 mb-3">Customer Details</h5>
                                <div id="customerProfileBox" class="customer-profile-line text-muted">
                                    Loading customer details...
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="font-size-15 mb-0">Sales Summary</h5>
                                    <span class="badge bg-primary-subtle text-primary" id="totalSalesCountBadge">0 Sales</span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th>Total Sales</th>
                                                <th class="text-end">Sales Paid</th>
                                                <th class="text-end">Sales Due</th>
                                                <th class="text-end">Opening Paid</th>
                                                <th class="text-end">Opening Due</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong id="summarySalesTotal">₹0.00</strong></td>
                                                <td class="text-end text-success"><strong id="summarySalesPaid">₹0.00</strong></td>
                                                <td class="text-end text-danger"><strong id="summarySalesDue">₹0.00</strong></td>
                                                <td class="text-end text-success"><strong id="summaryOpeningPaid">₹0.00</strong></td>
                                                <td class="text-end text-danger"><strong id="summaryOpeningDue">₹0.00</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="font-size-15 mb-1">Sales Details</h5>
                                <p class="text-muted mb-0">View, print and collect pending payments from customer sales.</p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Document</th>
                                        <th>Date</th>
                                        <th class="text-end">Sub Total</th>
                                        <th class="text-end">Tax</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th width="160" class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="customerSalesTableBody">
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

$scriptsPath1 = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.CUSTOMER_ID = <?= (int)$customerId; ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/customer-view.js?v=<?= time(); ?>"></script>

</body>
</html>
