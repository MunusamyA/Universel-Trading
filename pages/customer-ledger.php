<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';
secureSessionStart();
requireLogin();
$page_title = 'Customer Ledger | Universal ERP';
?>
<!doctype html>
<html lang="en">
<head><?php include BASE_PATH . 'includes/head.php'; ?></head>
<body data-sidebar="dark">
<?php include BASE_PATH . 'includes/pre-loader.php'; ?>
<div id="layout-wrapper">
<?php include BASE_PATH . 'includes/topbar.php'; ?>
<div class="vertical-menu"><div data-simplebar class="h-100"><?php include BASE_PATH . 'includes/sidebar.php'; ?></div></div>
<div class="main-content">
<div class="page-content"><div class="container-fluid">

<div class="row"><div class="col-12">
<div class="page-title-box d-flex align-items-center justify-content-between">
    <div><h4 class="mb-0">Customer Ledger</h4><small class="text-muted">Debit / Credit / Balance statement</small></div>
    <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light"><i class="mdi mdi-account-group me-1"></i> Customers</a>
</div></div></div>

<div class="row">
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-primary" id="ledgerEntriesCount">0</h3>Total Entries</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-danger" id="ledgerDebitTotal">₹0.00</h3>Total Debit</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-success" id="ledgerCreditTotal">₹0.00</h3>Total Credit</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-warning" id="ledgerClosingBalance">₹0.00</h3>Closing Balance</div></div></div>
</div>

<div class="card"><div class="card-body">
<div class="row align-items-end mb-3">
<div class="col-md-4"><label class="form-label">Customer</label><select class="form-select" id="customerId"><option value="">Select Customer</option></select></div>
<div class="col-md-2"><label class="form-label">From Date</label><input type="date" class="form-control" id="fromDate"></div>
<div class="col-md-2"><label class="form-label">End Date</label><input type="date" class="form-control" id="toDate"></div>
<div class="col-md-4 text-end"><button type="button" class="btn btn-primary" id="filterLedgerBtn"><i class="mdi mdi-filter me-1"></i> Filter</button> <button type="button" class="btn btn-light" id="resetLedgerBtn"><i class="mdi mdi-refresh me-1"></i> Reset</button></div>
</div>
<div class="row"><div class="col-12"><label class="form-label mb-2">Document Type</label><div class="d-flex flex-wrap gap-3">
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="1" checked> Quotation</label>
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="2" checked> Proforma Bill</label>
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="3" checked> Sales Bill</label>
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="4" checked> Direct Bill</label>
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="5" checked> Final Invoice</label>
<label class="form-check-label"><input type="checkbox" class="form-check-input ledger-doc-type" value="99" checked> Payments</label>
</div></div></div>
</div></div>

<div class="card"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-0" id="ledgerCustomerName">Select customer</h5><small class="text-muted" id="ledgerCustomerInfo">Ledger statement</small></div></div>
<div class="table-responsive"><table class="table table-centered table-nowrap mb-0">
<thead><tr><th>#</th><th>Date</th><th>Particular</th><th>Reference</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
<tbody id="ledgerTableBody"><tr><td colspan="8" class="text-center text-muted">Select customer and filter ledger.</td></tr></tbody>
</table></div>
</div></div>

</div></div>
<?php include BASE_PATH . 'includes/footer.php'; ?>
</div></div>
<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>window.BASE_URL = "<?= BASE_URL; ?>";</script>
<script src="<?= BASE_URL; ?>pages-js/customer-ledger.js?v=<?= time(); ?>"></script>
</body></html>
