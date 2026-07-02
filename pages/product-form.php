<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';
secureSessionStart();
requireLogin();
$productId = (int)($_GET['id'] ?? 0);
$page_title = ($productId > 0 ? 'Edit Product' : 'Add Product') . ' | Universal ERP';
?>
<!doctype html><html lang="en">
<head><?php include BASE_PATH . 'includes/head.php'; ?></head>
<body data-sidebar="dark">
<?php include BASE_PATH . 'includes/pre-loader.php'; ?>
<div id="layout-wrapper">
<?php include BASE_PATH . 'includes/topbar.php'; ?>
<div class="vertical-menu"><div data-simplebar class="h-100"><?php include BASE_PATH . 'includes/sidebar.php'; ?></div></div>
<div class="main-content"><div class="page-content"><div class="container-fluid">

<div class="row"><div class="col-12"><div class="page-title-box d-flex align-items-center justify-content-between">
<h4 class="mb-0"><?= $productId > 0 ? 'Edit Product' : 'Add Product'; ?></h4>
<a href="<?= BASE_URL; ?>pages/products.php" class="btn btn-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
</div></div></div>

<form id="productForm" autocomplete="off" enctype="multipart/form-data">
<?= csrfTokenInput(); ?>
<input type="hidden" name="product_id" id="product_id" value="<?= $productId; ?>">
<input type="hidden" name="remove_image" id="remove_image" value="0">

<div class="card"><div class="card-header"><h5 class="mb-0">Product Details</h5></div><div class="card-body">
<div class="row">
<div class="col-md-4"><label class="form-label">Category <span class="text-danger">*</span></label><div class="input-group"><select class="form-select" id="category_id" name="category_id"><option value="">Select Category</option></select><button class="btn btn-outline-primary quick-master-btn" type="button" data-master="category"><i class="mdi mdi-plus"></i></button></div></div>
<div class="col-md-4"><label class="form-label">Sub Category <span class="text-danger">*</span></label><div class="input-group"><select class="form-select" id="sub_category_id" name="sub_category_id"><option value="">Select Sub Category</option></select><button class="btn btn-outline-primary quick-master-btn" type="button" data-master="sub_category"><i class="mdi mdi-plus"></i></button></div></div>
<div class="col-md-4"><label class="form-label">HSN <span class="text-danger">*</span></label><div class="input-group"><select class="form-select price-calc" id="hsn_id" name="hsn_id"><option value="">Select HSN</option></select><button class="btn btn-outline-primary quick-master-btn" type="button" data-master="hsn"><i class="mdi mdi-plus"></i></button></div><small class="text-muted" id="gstAmountText">GST amount: ₹0.00</small></div>
<div class="col-md-4 mt-3"><label class="form-label">Product Code</label><input type="text" class="form-control text-uppercase" id="product_code" name="product_code" placeholder="Auto generated if empty"></div>
<div class="col-md-8 mt-3"><label class="form-label">Product Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter product name"></div>
<div class="col-md-6 mt-3"><label class="form-label">Product Image</label><input type="file" class="form-control" id="product_image" name="product_image" accept="image/png,image/jpeg,image/webp"><small class="text-muted">JPG, PNG, WEBP. Max 2 MB.</small></div>
<div class="col-md-6 mt-3"><label class="form-label">Current Image</label><div id="currentImagePreview" class="border rounded p-2 text-muted">No image selected</div></div>
</div></div></div>

<div class="card"><div class="card-header"><h5 class="mb-0">MRP, GST & Price</h5></div><div class="card-body">

<h5 class="font-size-15 mb-3">MRP & Stock Price</h5>
<div class="row">
<div class="col-md-3"><label class="form-label">Enter MRP <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" min="0" class="form-control price-calc" id="enter_mrp" name="enter_mrp" value="0.00"></div></div>
<div class="col-md-3"><label class="form-label">GST Type</label><select class="form-select price-calc" id="gst_type" name="gst_type"><option value="1">Inclusive</option><option value="2">Exclusive</option></select><small class="text-muted" id="gstTypeInfo">MRP includes GST.</small></div>
<div class="col-md-3"><label class="form-label">Final MRP</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" class="form-control" id="final_mrp" name="final_mrp" value="0.00" readonly></div></div>
<div class="col-md-3"><label class="form-label">Purchase Price / Stock Price <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" min="0" class="form-control price-calc" id="cost_price" name="cost_price" value="0.00"></div><small class="text-muted" id="stockPriceInfo">Enter purchase/stock price manually</small></div>
<input type="hidden" id="discount_type" name="discount_type" value="1">
<input type="hidden" id="discount_value" name="discount_value" value="0.00">
<input type="hidden" id="you_save_display" value="₹0.00 / 0.00%">
</div>

<hr><h5 class="font-size-15 mb-3">Sale / Retail Price (For Customers)</h5>
<div class="row">
<div class="col-md-3"><label class="form-label">Markup Type</label><select class="form-select price-calc" id="retail_markup_type" name="retail_markup_type"><option value="1">Percentage (%)</option><option value="2">Fixed Amount</option></select></div>
<div class="col-md-3"><label class="form-label">Markup Value</label><div class="input-group"><input type="number" step="0.01" min="0" class="form-control price-calc" id="retail_markup_value" name="retail_markup_value" value="0.00"><span class="input-group-text retail-markup-symbol">%</span></div><small class="text-muted" id="retailMarkupInfo">Markup: ₹0.00</small></div>
<div class="col-md-3"><label class="form-label">Sale Price / Retail Price <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" min="0" class="form-control price-calc" id="retail_price" name="retail_price" value="0.00"></div><small class="text-danger d-none" id="retailPriceError">Error: Must be &gt; Stock Price</small></div>
<div class="col-md-3"><label class="form-label">Profit Margin</label><input type="text" class="form-control" id="retail_profit_display" value="0.00% / ₹0.00" readonly></div>
</div>

<hr><h5 class="font-size-15 mb-3">Wholesale Price (For Customers) <span class="text-danger">*</span></h5>
<div class="row">
<div class="col-md-3"><label class="form-label">Markup Type</label><select class="form-select price-calc" id="wholesale_markup_type" name="wholesale_markup_type"><option value="1">Percentage (%)</option><option value="2">Fixed Amount</option></select></div>
<div class="col-md-3"><label class="form-label">Markup Value</label><div class="input-group"><input type="number" step="0.01" min="0" class="form-control price-calc" id="wholesale_markup_value" name="wholesale_markup_value" value="0.00"><span class="input-group-text wholesale-markup-symbol">%</span></div><small class="text-muted" id="wholesaleMarkupInfo">Markup: ₹0.00</small></div>
<div class="col-md-3"><label class="form-label">Wholesale Price <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" min="0" class="form-control price-calc" id="wholesale_price" name="wholesale_price" value="0.00"></div><small class="text-muted" id="wholesalePriceInfo">Same as stock price (no markup)</small><small class="text-danger d-none" id="wholesalePriceError">Error: Must be &gt;= Stock Price</small></div>
<div class="col-md-3"><label class="form-label">Profit Margin</label><input type="text" class="form-control" id="wholesale_profit_display" value="0.00% / ₹0.00" readonly></div>
</div>
<div class="alert alert-warning mt-4 mb-0">GST amount and Profit Margin are display-only. Discount fields removed.</div>
</div></div>

<div class="card"><div class="card-header"><h5 class="mb-0">Unit, Conversion & Initial Stock</h5></div><div class="card-body"><div class="row">
<div class="col-md-3"><label class="form-label">Base Unit <span class="text-danger">*</span></label><select class="form-select" id="base_unit" name="base_unit"><option value="Piece">Piece</option><option value="Box">Box</option><option value="Pack">Pack</option><option value="Case">Case</option><option value="Set">Set</option><option value="Dozen">Dozen</option><option value="KG">KG</option><option value="Gram">Gram</option><option value="Litre">Litre</option><option value="ML">ML</option><option value="Meter">Meter</option><option value="Feet">Feet</option><option value="Bottle">Bottle</option><option value="Number">Number</option></select></div>
<div class="col-md-3"><label class="form-label">Secondary Unit Label <span class="text-danger">*</span></label><select class="form-select" id="secondary_unit_label" name="secondary_unit_label"><option value="Piece">Piece</option><option value="Box">Box</option><option value="Pack">Pack</option><option value="Case">Case</option><option value="Set">Set</option><option value="Dozen">Dozen</option><option value="KG">KG</option><option value="Gram">Gram</option><option value="Litre">Litre</option><option value="ML">ML</option><option value="Meter">Meter</option><option value="Feet">Feet</option><option value="Bottle">Bottle</option><option value="Number">Number</option></select></div>
<div class="col-md-3"><label class="form-label">Secondary Conversion <span class="text-danger">*</span></label><input type="number" step="0.0001" min="0.0001" class="form-control" id="secondary_unit_value" name="secondary_unit_value" value="1.0000"><small class="text-muted">1 secondary unit = this many base units</small></div>
<div class="col-md-3"><label class="form-label">Initial Stock Qty</label><input type="number" step="0.0001" min="0" class="form-control" id="initial_stock" name="initial_stock" value="0.0000"><small class="text-muted">Opening stock / batch quantity</small></div>
<div class="col-md-3 mt-3"><label class="form-label">Initial Stock Expiry Date</label><input type="date" class="form-control" id="initial_stock_expiry_date" name="initial_stock_expiry_date"></div>
<div class="col-md-3 mt-3"><label class="form-label">Minimum Stock</label><input type="number" step="0.01" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="0.00"></div>
<div class="col-md-3 mt-3"><label class="form-label">Status</label><select class="form-select" id="status1" name="status"><option value="1">Active</option><option value="2">Inactive</option></select></div>
<input type="hidden" id="box_label" name="box_label" value="">
<input type="hidden" id="default_pieces_per_box" name="default_pieces_per_box" value="1.0000">
<input type="hidden" id="markup_type" name="markup_type" value="1"><input type="hidden" id="markup_value" name="markup_value" value="0.00">
</div></div></div>

<div class="text-end mb-4"><a href="<?= BASE_URL; ?>pages/products.php" class="btn btn-light">Cancel</a><button type="submit" class="btn btn-primary" id="saveProductBtn">Save Product</button></div>
</form>

</div></div><?php include BASE_PATH . 'includes/footer.php'; ?></div></div>

<div class="modal fade" id="quickMasterModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="quickMasterForm">
<?= csrfTokenInput(); ?><input type="hidden" id="quick_master_type" name="quick_master_type">
<div class="modal-header"><h5 class="modal-title" id="quickMasterTitle">Add Master</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="quickMasterBody"></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" id="saveQuickMasterBtn">Save</button></div>
</form></div></div></div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>window.BASE_URL = "<?= BASE_URL; ?>"; window.PRODUCT_ID = <?= $productId; ?>;</script>
<script src="<?= BASE_URL; ?>pages-js/product-form.js?v=<?= time(); ?>"></script>
</body></html>
