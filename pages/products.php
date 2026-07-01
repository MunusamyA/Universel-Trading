<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';
secureSessionStart();
requireLogin();
$page_title = 'Product Master | Universal ERP';
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
<h4 class="mb-0">Product Master</h4>
<a href="<?= BASE_URL; ?>pages/product-form.php" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Add Product</a>
</div></div></div>

<div class="row">
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-primary" id="totalProductsCount">0</h3>Total Products</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-success" id="activeProductsCount">0</h3>Active Products</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-danger" id="inactiveProductsCount">0</h3>Inactive Products</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card text-center"><div class="card-body text-muted"><h3 class="text-warning" id="minimumStockTotal">0</h3>Min Stock Total</div></div></div>
</div>

<div class="card"><div class="card-body">
<div class="row align-items-end mb-3">
<div class="col-md-4"><label class="form-label">Search</label><input type="text" class="form-control" id="productSearch" placeholder="Code / Product / Category / HSN"></div>
<div class="col-md-3"><label class="form-label">Category</label><select class="form-select" id="categoryFilter"><option value="0">All Categories</option></select></div>
<div class="col-md-2"><label class="form-label">Status</label><select class="form-select" id="productStatusFilter"><option value="0">All</option><option value="1">Active</option><option value="2">Inactive</option></select></div>
<div class="col-md-3 text-end"><button class="btn btn-light" id="refreshProductsBtn"><i class="mdi mdi-refresh me-1"></i> Refresh</button></div>
</div>

<div class="table-responsive"><table class="table table-centered table-nowrap mb-0">
<thead><tr>
<th>#</th><th>Image</th><th>Product</th><th>Category</th><th>HSN/GST</th><th>MRP</th><th>Stock</th><th>Retail</th><th>Wholesale</th><th>Save</th><th>Status</th><th>Action</th>
</tr></thead>
<tbody id="productTableBody"><tr><td colspan="12" class="text-center text-muted">Loading...</td></tr></tbody>
</table></div>
</div></div>

</div></div>
<?php include BASE_PATH . 'includes/footer.php'; ?>
</div></div>
<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>window.BASE_URL = "<?= BASE_URL; ?>";</script>
<script src="<?= BASE_URL; ?>pages-js/products.js?v=<?= time(); ?>"></script>
</body></html>
