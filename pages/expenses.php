<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Expenses | Universal ERP';
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

<?= csrfTokenInput(); ?>

<div class="row"><div class="col-12">
<div class="page-title-box d-flex align-items-center justify-content-between">
<h4 class="mb-0">Expenses</h4>
<a href="<?= BASE_URL; ?>pages/expense-form.php" class="btn btn-primary d-none" id="addExpenseBtn"><i class="mdi mdi-plus me-1"></i> Add Expense</a>
</div></div></div>

<div class="row">
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-primary" id="totalExpensesCount">0</h3>Total Expenses</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-success" id="activeExpensesCount">0</h3>Active Expenses</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-danger" id="cancelledExpensesCount">0</h3>Cancelled</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-warning" id="totalExpenseAmount">₹0.00</h3>Total Amount</div></div></div>
</div>

<div class="card"><div class="card-body">
<div class="row align-items-end mb-3">
<div class="col-md-3"><label class="form-label">Search</label><input type="text" class="form-control" id="expenseSearch" placeholder="No / Vendor / Reference / Notes"></div>
<div class="col-md-2"><label class="form-label">From Date</label><input type="date" class="form-control" id="fromDate"></div>
<div class="col-md-2"><label class="form-label">End Date</label><input type="date" class="form-control" id="toDate"></div>
<div class="col-md-2"><label class="form-label">Category</label><select class="form-select" id="categoryFilter"><option value="0">All Categories</option></select></div>
<div class="col-md-1"><label class="form-label">Status</label><select class="form-select" id="expenseStatusFilter"><option value="0">All</option><option value="1">Active</option><option value="2">Cancelled</option></select></div>
<div class="col-md-2 text-end">
<button class="btn btn-primary" id="filterExpensesBtn"><i class="mdi mdi-filter me-1"></i> Filter</button>
<button class="btn btn-light" id="refreshExpensesBtn"><i class="mdi mdi-refresh"></i></button>
</div>
</div>

<div class="table-responsive"><table class="table table-centered table-nowrap mb-0">
<thead><tr>
<th>#</th><th>Date</th><th>Expense No</th><th>Category</th><th>Vendor</th><th>Taxable</th><th>GST</th><th>Total</th><th>Payment Split</th><th>Status</th><th width="130">Action</th>
</tr></thead>
<tbody id="expenseTableBody"><tr><td colspan="11" class="text-center text-muted">Loading...</td></tr></tbody>
</table></div>
</div></div>

</div></div>
<?php include BASE_PATH . 'includes/footer.php'; ?>
</div></div>
<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>
window.BASE_URL = "<?= BASE_URL; ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/expenses.js?v=<?= time(); ?>"></script>
</body></html>
