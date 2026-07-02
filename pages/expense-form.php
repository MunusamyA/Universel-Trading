<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$expenseId = (int)($_GET['id'] ?? 0);
$page_title = ($expenseId > 0 ? 'Edit Expense' : 'Add Expense') . ' | Universal ERP';
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
<div>
<h4 class="mb-0" id="expensePageTitle"><?= $expenseId > 0 ? 'Edit Expense' : 'Add Expense'; ?></h4>
<small class="text-muted">Expense form with split payment</small>
</div>
<a href="<?= BASE_URL; ?>pages/expenses.php" class="btn btn-light"><i class="mdi mdi-arrow-left me-1"></i> Expense List</a>
</div></div></div>

<form id="expenseForm" autocomplete="off">
<?= csrfTokenInput(); ?>
<input type="hidden" id="expense_id" name="expense_id" value="<?= (int)$expenseId; ?>">
<input type="hidden" id="splitPaymentsJson" name="split_payments" value="[]">

<div class="card"><div class="card-body">
<h5 class="font-size-15 mb-3">Expense Details</h5>
<div class="row">
<div class="col-md-3">
<label class="form-label">Expense Date <span class="text-danger">*</span></label>
<input type="date" class="form-control" id="expense_date" name="expense_date">
</div>
<div class="col-md-4">
<label class="form-label">Category <span class="text-danger">*</span></label>
<div class="input-group">
<select class="form-select" id="category_id" name="category_id"><option value="">Select Category</option></select>
<button type="button" class="btn btn-light" id="quickAddCategoryBtn"><i class="mdi mdi-plus"></i></button>
</div>
</div>
<div class="col-md-3">
<label class="form-label">Vendor / Paid To</label>
<input type="text" class="form-control" id="vendor_name" name="vendor_name" placeholder="Vendor / Paid To">
</div>
<div class="col-md-2">
<label class="form-label">Reference No</label>
<input type="text" class="form-control" id="reference_no" name="reference_no" placeholder="Bill / Ref">
</div>
</div>
</div></div>

<div class="card"><div class="card-body">
<h5 class="font-size-15 mb-3">Amount Details</h5>
<div class="row">
<div class="col-md-4">
<label class="form-label">Taxable Amount</label>
<input type="number" step="0.01" min="0" class="form-control amount-field" id="taxable_amount" name="taxable_amount" value="0.00">
</div>
<div class="col-md-4">
<label class="form-label">GST Amount</label>
<input type="number" step="0.01" min="0" class="form-control amount-field" id="gst_amount" name="gst_amount" value="0.00">
</div>
<div class="col-md-4">
<label class="form-label">Total Amount</label>
<input type="text" class="form-control fw-bold" id="total_amount" readonly value="0.00">
<small class="text-muted">Split payment total must match this amount.</small>
</div>
<div class="col-md-12 mt-3">
<label class="form-label">Notes</label>
<input type="text" class="form-control" id="notes" name="notes" placeholder="Expense notes">
</div>
</div>
</div></div>

<div class="card"><div class="card-body">
<div class="d-flex align-items-center justify-content-between mb-3">
<h5 class="font-size-15 mb-0">Payment Split</h5>
<button type="button" class="btn btn-sm btn-primary" id="addSplitRowBtn"><i class="mdi mdi-plus me-1"></i> Add Split</button>
</div>

<div class="table-responsive">
<table class="table table-bordered mb-0">
<thead><tr><th width="35%">Payment Mode</th><th width="25%">Amount</th><th>Reference</th><th width="70">Action</th></tr></thead>
<tbody id="splitRowsBody"></tbody>
<tfoot>
<tr>
<th class="text-end">Split Total</th>
<th><input type="text" class="form-control fw-bold" id="splitTotalAmount" readonly value="0.00"></th>
<th colspan="2"></th>
</tr>
</tfoot>
</table>
</div>
</div></div>

<div class="card"><div class="card-body">
<div class="row align-items-center">
<div class="col-md-4">
<label class="form-label">Status</label>
<select class="form-select" id="status1" name="status"><option value="1">Active</option><option value="2">Cancelled</option></select>
</div>
<div class="col-md-8 text-end">
<a href="<?= BASE_URL; ?>pages/expenses.php" class="btn btn-light me-2">Cancel</a>
<button type="submit" class="btn btn-primary" id="saveExpenseBtn"><i class="mdi mdi-content-save me-1"></i> Save Expense</button>
</div>
</div>
</div></div>
</form>

</div></div>
<?php include BASE_PATH . 'includes/footer.php'; ?>
</div></div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form id="categoryForm">
<?= csrfTokenInput(); ?>
<div class="modal-header"><h5 class="modal-title">Add Expense Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<label class="form-label">Category Name</label>
<input type="text" class="form-control" id="quick_category_name" name="category_name" placeholder="Enter category name">
</div>
<div class="modal-footer">
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
<button type="submit" class="btn btn-primary">Save Category</button>
</div>
</form>
</div></div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>
window.BASE_URL = "<?= BASE_URL; ?>";
window.EXPENSE_FORM_CONFIG = { expense_id: <?= (int)$expenseId; ?> };
</script>
<script src="<?= BASE_URL; ?>pages-js/expense-form.js?v=<?= time(); ?>"></script>
</body></html>
