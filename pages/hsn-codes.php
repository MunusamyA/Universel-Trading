<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();
$page_title = 'HSN Codes | Universal ERP';
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
<div class="row"><div class="col-12"><div class="page-title-box d-flex align-items-center justify-content-between"><h4 class="mb-0">HSN Codes</h4><button type="button" class="btn btn-primary" id="addBtn"><i class="mdi mdi-plus me-1"></i> Add HSN</button></div></div></div>

<div class="row">
<div class="col-md-4"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-primary" id="totalCount">0</h3>Total</div></div></div>
<div class="col-md-4"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-success" id="activeCount">0</h3>Active</div></div></div>
<div class="col-md-4"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-danger" id="inactiveCount">0</h3>Inactive</div></div></div>
</div>

<div class="card"><div class="card-body">
<div class="row align-items-end mb-3"><div class="col-md-4"><label class="form-label">Search</label><input type="text" class="form-control" id="searchInput"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" id="statusFilter"><option value="0">All</option><option value="1">Active</option><option value="2">Inactive</option></select></div><div class="col-md-5 text-end"><button class="btn btn-light" id="refreshBtn"><i class="mdi mdi-refresh me-1"></i> Refresh</button></div></div>
<div class="table-responsive"><table class="table table-centered table-nowrap mb-0"><thead><tr><th width="60">#</th><th>HSN Code</th><th>Description</th><th>CGST</th><th>SGST</th><th>IGST</th><th>Status</th><th width="120">Action</th></tr></thead><tbody id="tableBody"><tr><td colspan="8" class="text-center text-muted">Loading...</td></tr></tbody></table></div>
</div></div>
</div></div><?php include BASE_PATH . 'includes/footer.php'; ?></div></div>

<div class="modal fade" id="recordModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="recordForm" autocomplete="off"><?= csrfTokenInput(); ?><input type="hidden" name="id" id="id">
<div class="modal-header"><h5 class="modal-title" id="modalTitle">Add HSN</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row"><div class="col-md-4"><label class="form-label">HSN Code <span class="text-danger">*</span></label><input type="text" class="form-control" id="hsn_code" name="hsn_code"></div><div class="col-md-8"><label class="form-label">Description</label><input type="text" class="form-control" id="hsn_description" name="hsn_description"></div><div class="col-md-3 mt-3"><label class="form-label">CGST %</label><input type="number" step="0.01" min="0" class="form-control" id="cgst_percentage" name="cgst_percentage" value="0.00"></div><div class="col-md-3 mt-3"><label class="form-label">SGST %</label><input type="number" step="0.01" min="0" class="form-control" id="sgst_percentage" name="sgst_percentage" value="0.00"></div><div class="col-md-3 mt-3"><label class="form-label">IGST %</label><input type="number" step="0.01" min="0" class="form-control" id="igst_percentage" name="igst_percentage" value="0.00"></div><div class="col-md-3 mt-3"><label class="form-label">Status</label><select class="form-select" id="status1" name="status"><option value="1">Active</option><option value="2">Inactive</option></select></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" id="saveBtn">Save</button></div>
</form></div></div></div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>window.BASE_URL = "<?= BASE_URL; ?>"; window.MASTER_FILE = "hsn_codes";</script>
<script src="<?= BASE_URL; ?>pages-js/hsn_codes.js?v=<?= time(); ?>"></script>
</body></html>