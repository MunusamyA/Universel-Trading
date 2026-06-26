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
                            <h4 class="mb-0">Product Master</h4>
                            <div class="page-title-right">
                                <button type="button" class="btn btn-primary" id="addProductBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="totalProductsCount">0</h3>
                                Total Products
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="activeProductsCount">0</h3>
                                Active Products
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="inactiveProductsCount">0</h3>
                                Inactive Products
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-warning" id="minimumStockTotal">0</h3>
                                Min Stock Total
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="productSearch" placeholder="Code / Product / Category / HSN">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="categoryFilter">
                                    <option value="0">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="productStatusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-light" id="refreshProductsBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>HSN</th>
                                    <th>Unit</th>
                                    <th>Box Qty</th>
                                    <th>Markup</th>
                                    <th>Min Stock</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                                </thead>
                                <tbody id="productTableBody">
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

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="productForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="product_id" id="product_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">
                    <h5 class="font-size-15 mb-3">Product Details</h5>

                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Select Category</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="sub_category_id" name="sub_category_id">
                                <option value="">Select Sub Category</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">HSN <span class="text-danger">*</span></label>
                            <select class="form-select" id="hsn_id" name="hsn_id">
                                <option value="">Select HSN</option>
                            </select>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label">Product Code</label>
                            <input type="text" class="form-control text-uppercase" id="product_code" name="product_code" placeholder="Auto generated if empty">
                        </div>

                        <div class="col-md-8 mt-3">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter product name">
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-4">Unit & Sales Settings</h5>

                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Base Unit</label>
                            <select class="form-select" id="base_unit" name="base_unit">
                                <option value="Piece">Piece</option>
                                <option value="Box">Box</option>
                                <option value="Pack">Pack</option>
                                <option value="Case">Case</option>
                                <option value="Set">Set</option>
                                <option value="Dozen">Dozen</option>
                                <option value="KG">KG</option>
                                <option value="Gram">Gram</option>
                                <option value="Litre">Litre</option>
                                <option value="ML">ML</option>
                                <option value="Meter">Meter</option>
                                <option value="Feet">Feet</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Number">Number</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Box Label</label>
                            <select class="form-select" id="box_label" name="box_label">
                                <option value="Box">Box</option>
                                <option value="Case">Case</option>
                                <option value="Pack">Pack</option>
                                <option value="Carton">Carton</option>
                                <option value="Bundle">Bundle</option>
                                <option value="Bag">Bag</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Pieces Per Box <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="default_pieces_per_box" name="default_pieces_per_box" value="1.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Markup %</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="markup_percentage" name="markup_percentage" value="0.00">
                        </div>
                        <div class="col-md-3 mt-3">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="0.00">
                        </div>
                        <div class="col-md-3 mt-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0">
                        Purchase and sales rate will be handled in purchase batch. Markup % will be used for selling rate calculation.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveProductBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/products.js?v=<?= time(); ?>"></script>
</body>
</html>
