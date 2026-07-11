<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Customer Payments | Universal ERP';
$customerId = (int)($_GET['customer_id'] ?? 0);
$salesId = (int)($_GET['sales_id'] ?? 0);
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
                                <h4 class="mb-0" id="pageTitleText">Customer Payments</h4>
                                <small class="text-muted" id="pageNoteText">Individual bill payment, overall FIFO payment and opening balance payment</small>
                            </div>

                            <div>
                                <a href="<?= BASE_URL; ?>pages/all-sales-list.php" class="btn btn-light d-none" id="salesListBtn">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Sales List
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <input type="hidden" id="pageCustomerId" value="<?= (int)$customerId; ?>">
                <input type="hidden" id="pageSalesId" value="<?= (int)$salesId; ?>">
                <?= csrfTokenInput(); ?>

                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3 mb-3">
                    <div class="col">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-warning" id="openingDueCard">₹0.00</h4>
                                <p class="text-muted mb-0">Opening Due</p>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-primary" id="billAmountCard">₹0.00</h4>
                                <p class="text-muted mb-0">Bill Amount</p>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-info" id="paidAmountCard">₹0.00</h4>
                                <p class="text-muted mb-0">Paid Amount</p>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-danger" id="dueAmountCard">₹0.00</h4>
                                <p class="text-muted mb-0">Due Amount</p>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h4 class="text-success" id="totalReceivableCard">₹0.00</h4>
                                <p class="text-muted mb-0">Total Pending Amount</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border d-none" id="selectedCustomerInfo">
                    Selected Customer: <strong id="selectedCustomerName">-</strong>
                </div>



                <div class="card" id="selectedSaleCard" style="display:none;">
                    <div class="card-body">
                        <h5 class="font-size-15 mb-2">Selected Sales Document</h5>

                        <div class="row">
                            <div class="col-md-3">
                                <strong>No:</strong> <span id="selectedSalesNo">-</span>
                            </div>

                            <div class="col-md-3">
                                <strong>Total:</strong> <span id="selectedSalesTotal">₹0.00</span>
                            </div>

                            <div class="col-md-3">
                                <strong>Paid:</strong> <span id="selectedSalesPaid">₹0.00</span>
                            </div>

                            <div class="col-md-3">
                                <strong>Due:</strong> <span class="text-danger fw-bold" id="selectedSalesDue">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" id="paymentEntryCard">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0" id="paymentModalTitle">Payment Entry</h5>
                        <small class="text-muted">Direct entry, no popup</small>
                    </div>

                    <form id="paymentForm" autocomplete="off">
                        <?= csrfTokenInput(); ?>

                        <input type="hidden" id="paymentId" name="payment_id">
                        <input type="hidden" id="paymentCustomerId" name="customer_id">
                        <input type="hidden" id="paymentSalesId" name="sales_id">
                        <input type="hidden" id="paymentTypeHidden" name="payment_type" value="1">

                        <div class="card-body">

                    <div class="alert alert-info py-2" id="paymentInfoBox">
                        Select customer or sales document.
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3" id="paymentCustomerBox">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="paymentCustomerSearch" placeholder="Search customer name / mobile" autocomplete="off">
                                    <button type="button" class="btn btn-light border" id="clearCustomerSearchBtn" title="Clear customer">
                                        <i class="mdi mdi-close"></i>
                                    </button>
                                </div>
                                <div class="list-group position-absolute w-100 shadow-sm d-none" id="paymentCustomerDropdown" style="z-index: 1050; max-height: 230px; overflow-y: auto;"></div>
                            </div>
                            <small class="text-muted" id="paymentCustomerHelp">Type customer name / mobile and select from list.</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Type <span class="text-danger">*</span></label>

                            <select class="form-select" id="paymentType">
                                <option value="1">Individual Document Payment</option>
                                <option value="2">Overall FIFO Payment</option>
                                <option value="3">Opening Balance Payment</option>
                            </select>

                            <small class="text-muted">Individual = quotation / proforma / sales bill / invoice separately.</small>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="paymentDate" name="payment_date">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Total Split Amount</label>
                            <input type="text" class="form-control fw-bold" id="paymentAmount" name="amount" readonly>
                        </div>

                        <div class="col-md-8 mt-3" id="individualDocumentBox">
                            <label class="form-label">Select Particular Document <span class="text-danger">*</span></label>

                            <select class="form-select" id="individualSalesSelect">
                                <option value="">Select quotation / proforma / sales bill / invoice</option>
                            </select>

                            <small class="text-muted" id="individualDocumentInfo"></small>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="paymentNotes" name="notes">
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Payment Split</h6>

                            <button type="button" class="btn btn-sm btn-success" id="addSplitRowBtn">
                                <i class="mdi mdi-plus"></i> Add Split
                            </button>
                        </div>

                        <input type="hidden" id="splitPaymentsJson" name="split_payments">

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:35%;">Payment Mode</th>
                                        <th style="width:25%;">Amount</th>
                                        <th>Reference No</th>
                                        <th style="width:60px;">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="splitRowsBody"></tbody>
                            </table>
                        </div>

                        <small class="text-muted">Example: ₹200 bill can be split as ₹100 UPI + ₹100 Cash.</small>

                        <div class="text-end mt-2">
                            <strong>Total: <span id="splitTotalText">₹0.00</span></strong>
                        </div>
                    </div>

                    <hr>

                    <small class="text-muted">
                        Individual = selected quotation / proforma / sales bill / invoice only. Overall = Bill due FIFO first, then opening due. Opening = opening due only.
                    </small>
                        </div>

                        <div class="card-footer text-end">
                            <button type="button" class="btn btn-light" id="resetPaymentBtn">Reset</button>
                            <button type="submit" class="btn btn-primary" id="savePaymentBtn">Save Payment</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-body">

                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="paymentSearch" placeholder="Payment no / customer / bill no / mobile">
                            </div>

                            <div class="col-md-2">
                                <button type="button" class="btn btn-light" id="refreshPaymentListBtn">
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
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Mode</th>
                                        <th>Sales No</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
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
    window.MASTER_FILE = "sales";
</script>

<script src="<?= BASE_URL; ?>pages-js/customer-payments.js?v=<?= time(); ?>"></script>

</body>
</html>
