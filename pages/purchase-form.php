<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$purchaseId = (int)($_GET['id'] ?? 0);
$page_title = ($purchaseId > 0 ? 'Edit Purchase' : 'Add Purchase') . ' | Universal ERP';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
<style>
#productSuggestionBox .product-suggestion-item { text-align:left; cursor:pointer; }
#productSuggestionBox .product-suggestion-item:hover { background:#f8f9fa; }
.purchase-products-table { min-width: 1540px; }
.purchase-products-table th,
.purchase-products-table td { vertical-align: middle; }
.purchase-products-table .scheme-disc-col { min-width: 210px; width: 210px; }
.purchase-products-table .gst-col { min-width: 210px; width: 210px; }
.purchase-products-table .scheme-disc-col .input-group,
.purchase-products-table .gst-col .input-group { flex-wrap: nowrap; min-width: 185px; }
.purchase-products-table .scheme-disc-col .form-select,
.purchase-products-table .gst-col .form-select { flex: 0 0 72px; max-width: 72px; }
.purchase-products-table .scheme-disc-col input,
.purchase-products-table .gst-col input { min-width: 95px; }
.purchase-products-table .item-gst-amount,
.purchase-products-table .item-taxable-total { white-space: nowrap; }
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
                            <h4 class="mb-0" id="purchasePageTitle"><?= $purchaseId > 0 ? 'Edit Purchase' : 'Add Purchase'; ?></h4>
                            <a href="<?= BASE_URL; ?>pages/purchases.php" class="btn btn-light" id="backPurchasesBtn">
                                <i class="mdi mdi-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                

                <form id="purchaseForm" autocomplete="off">
                    <?= csrfTokenInput(); ?>
                    <input type="hidden" name="purchase_id" id="purchase_id" value="<?= $purchaseId; ?>">
                    <input type="hidden" name="items_json" id="items_json" value="">
                    <input type="hidden" name="payment_splits_json" id="payment_splits_json" value="">

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Purchase Details</h5>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">Select Supplier</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Bill No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="bill_no" name="bill_no" placeholder="Supplier bill no">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Batch No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control bg-light" id="batch_no" name="batch_no" placeholder="Auto Generated" readonly>
                                    <small class="text-muted">Common batch for this purchase</small>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date">
                                </div>

                                <div class="col-md-12 mt-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body" style="overflow:visible;">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h5 class="font-size-15 mb-0">Select Product</h5>
                                        <button type="button" class="btn btn-success btn-sm d-none" id="quickProductBtn" data-bs-toggle="modal" data-bs-target="#quickProductModal">
                                            <i class="mdi mdi-plus me-1"></i> New Product
                                        </button>
                                    </div>

                                    <div class="row">
                                        <div class="col-12 position-relative" style="overflow:visible;">
                                            <label class="form-label">Product</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                                <input type="text" class="form-control" id="productSearchInput" placeholder="Click or type product name/code" autocomplete="off">
                                                <button type="button" class="btn btn-outline-secondary" id="clearProductSearchBtn">
                                                    Clear
                                                </button>
                                            </div>
                                            <input type="hidden" id="productSelect">
                                            <div id="productSuggestionBox" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:3000;max-height:320px;overflow:auto;left:0;right:0;"></div>
                                            <small class="text-muted">Click input to show first 10 products. Type to filter by product name or code.</small>
                                        </div>
                                    </div>

                                    <div id="selectedProductBox" class="border rounded p-3 mt-3 d-none">
                                        <input type="hidden" id="pre_product_id">
                                        <input type="hidden" id="pre_product_code">
                                        <input type="hidden" id="pre_product_name">
                                        <input type="hidden" id="pre_base_unit">
                                        <input type="hidden" id="pre_box_label">
                                        <input type="hidden" id="pre_pieces_per_box">

                                        <h6 class="mb-3" id="selectedProductTitle">Selected Product</h6>

                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">HSN <button type="button" class="btn btn-link btn-sm p-0 ms-1 d-none quick-hsn-btn" data-bs-toggle="modal" data-bs-target="#hsnModal">+ Add</button></label>
                                                <select class="form-select form-select-sm pre-calc" id="pre_hsn_id">
                                                    <option value="">Select HSN</option>
                                                </select>
                                                <input type="hidden" id="pre_hsn_code">
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">CGST %</label>
                                                <input type="number" class="form-control form-control-sm" id="pre_cgst_percentage" value="0.00" readonly>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">SGST %</label>
                                                <input type="number" class="form-control form-control-sm" id="pre_sgst_percentage" value="0.00" readonly>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">IGST %</label>
                                                <input type="number" class="form-control form-control-sm" id="pre_igst_percentage" value="0.00" readonly>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">GST Type</label>
                                                <select class="form-select form-select-sm pre-calc" id="pre_gst_type">
                                                    <option value="2" selected>Exclusive</option>
                                                    <option value="1">Inclusive</option>
                                                </select>
                                                <small class="text-muted" id="pre_gst_type_info">Exclusive: GST added | Inclusive: GST inside rate</small>
                                            </div>

                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Purchase Batch</label>
                                                <input type="text" class="form-control form-control-sm bg-light" id="pre_purchase_batch_no" placeholder="Header Batch" readonly>
                                            </div>
                                            <div class="col-md-3 mb-2 pre-secondary-unit-field d-none">
                                                <label class="form-label">Box / Case Label</label>
                                                <input type="text" class="form-control form-control-sm bg-light" id="pre_unit_label" value="Box" readonly>
                                                <small class="text-muted">Secondary unit from product master</small>
                                            </div>
                                            <div class="col-md-3 mb-2 pre-secondary-unit-field d-none">
                                                <label class="form-label">Box / Case Qty</label>
                                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm pre-calc" id="pre_box_qty" value="0">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label" id="preLoosePieceQtyLabel">Piece Qty</label>
                                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm pre-calc" id="pre_loose_piece_qty" value="0">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Free Piece Qty</label>
                                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm pre-calc" id="pre_free_qty" value="0">
                                            </div>
                                            <div class="col-md-3 mb-2 pre-secondary-unit-field d-none">
                                                <label class="form-label">Pieces Per Box / UPC</label>
                                                <input type="number" step="0.0001" min="1" class="form-control form-control-sm pre-calc" id="pre_unit_conversion" value="1">
                                                <small class="text-muted">Same as product secondary unit value</small>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Total Stock Pieces</label>
                                                <input type="number" step="0.0001" class="form-control form-control-sm bg-light" id="pre_qty" value="0" readonly>
                                                <small class="text-muted">Purchase calculation quantity</small>
                                            </div>

                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Product Rate / Piece</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm pre-calc" id="pre_purchase_price" value="" placeholder="Enter rate">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Scheme Discount</label>
                                                <div class="input-group input-group-sm">
                                                    <select class="form-select pre-calc" id="pre_discount_type">
                                                        <option value="2" selected>₹</option>
                                                        <option value="1">%</option>
                                                    </select>
                                                    <input type="number" step="0.01" min="0" class="form-control pre-calc" id="pre_discount_value" value="0.00" placeholder="If ₹, enter total discount">
                                                </div>
                                                <small class="text-muted">₹ = fixed total line discount, % = percentage discount</small>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">MRP</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm pre-calc" id="pre_mrp" value="0.00">
                                            </div>
                                            <input type="hidden" id="pre_expiry_days" value="0">

                                            <input type="hidden" id="pre_retail_price" value="0.00">

                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control form-control-sm pre-calc" id="pre_expiry_date">
                                            </div>

                                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                                <button type="button" class="btn btn-success w-100" id="confirmAddProductBtn">
                                                    <i class="mdi mdi-plus me-1"></i> Add to Purchase
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
</div>
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Products</h5>
                            <button type="button" class="btn btn-primary btn-sm" id="addItemBtn">
                                <i class="mdi mdi-plus me-1"></i> Add Product
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle purchase-products-table">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="min-width:220px;">Product</th>
                                        <th style="min-width:95px;">Qty</th>
                                        <th style="min-width:95px;">Free</th>
                                        <th style="min-width:120px;">Conv.</th>
                                        <th style="min-width:120px;">P.Price</th>
                                        <th class="scheme-disc-col">Scheme Disc.</th>
                                        <th class="gst-col">GST</th>
                                        <th style="min-width:120px;">MRP</th>
                                        <th style="min-width:135px;">Expiry</th>
                                        <th style="min-width:120px;">Total</th>
                                        <th width="60">#</th>
                                    </tr>
                                    </thead>
                                    <tbody id="itemsBody">
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">No products added.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info mb-0">
                                All purchase rows are editable. Edit product, quantity, price, GST, expiry and selling prices directly in table. Purchase batch is common in header.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="font-size-15 mb-3">Summary</h5>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Sub Total</label>
                                        <div class="col-7">
                                            <input type="number" class="form-control text-end" id="sub_total" readonly value="0.00">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Bill Discount</label>
                                        <div class="col-3">
                                            <select class="form-select calc-main" id="discount_type" name="discount_type">
                                                <option value="1">%</option>
                                                <option value="2">₹</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <input type="number" step="0.01" min="0" class="form-control text-end calc-main" id="discount_value" name="discount_value" value="0.00">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Tax Amount</label>
                                        <div class="col-7">
                                            <input type="number" class="form-control text-end" id="tax_amount" readonly value="0.00">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Round Off <small class="text-muted">(+ / -)</small></label>
                                        <div class="col-7">
                                            <div class="input-group">
                                                <span class="input-group-text fw-bold" id="roundOffSignAddon">±</span>
                                                <input type="number" step="0.01" class="form-control text-end calc-main" id="round_off" name="round_off" value="0.00" placeholder="+ add / - reduce">
                                                <button class="btn btn-outline-secondary" type="button" id="roundOffToggleBtn" title="Click once to round grand total, click again to remove round off">Round</button>
                                            </div>
                                            <!-- <small class="text-muted" id="roundOffHelpText">Use + amount to add and - amount to reduce. Click Round to nearest rupee.</small> -->
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Grand Total</label>
                                        <div class="col-7">
                                            <input type="number" class="form-control text-end fw-bold" id="grand_total" readonly value="0.00">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Paid Amount</label>
                                        <div class="col-7">
                                            <input type="number" step="0.01" min="0" class="form-control text-end calc-main" id="paid_amount" name="paid_amount" value="0.00">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-5 col-form-label">Due Amount</label>
                                        <div class="col-7">
                                            <input type="number" class="form-control text-end text-danger fw-bold" id="due_amount" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card h-100" id="purchasePaymentSplitBox">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h5 class="font-size-15 mb-0">Payment Split</h5>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="addPurchasePaymentSplitBtn">
                                            <i class="mdi mdi-plus me-1"></i> Add Split
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-2">
                                            <thead class="table-light">
                                            <tr>
                                                <th>Payment Mode</th>
                                                <th>Amount</th>
                                                <th>Reference No</th>
                                                <th width="50">#</th>
                                            </tr>
                                            </thead>
                                            <tbody id="purchasePaymentSplitsBody">
                                            <tr><td colspan="4" class="text-center text-muted">Enter paid amount to add split.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end small">
                                        Split Total: <strong>₹<span id="purchaseSplitTotal">0.00</span></strong>
                                        <span class="ms-2">Balance: <strong class="text-danger">₹<span id="purchaseSplitBalance">0.00</span></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mb-4">
                        <a href="<?= BASE_URL; ?>pages/purchases.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="savePurchaseBtn">Save Purchase</button>
                    </div>
                </form>

            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>



<!-- Quick Product Add Modal -->
<div class="modal fade" id="quickProductModal" tabindex="-1" aria-labelledby="quickProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form class="modal-content" id="quickProductForm">
            <?= csrfTokenInput(); ?>
            <input type="hidden" name="action" value="add_quick_product">
            <div class="modal-header">
                <h5 class="modal-title" id="quickProductModalLabel">Add New Product Master</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Same as product-form.php. Product saves in master first. It will not directly add to purchase tbody.
                    After save, it loads in purchase place; you can change unit, qty, rate, GST and scheme before Add.
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0">Product Details</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" id="quick_category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sub Category</label>
                                <select class="form-select" name="sub_category_id" id="quick_sub_category_id">
                                    <option value="">Select Sub Category</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">HSN</label>
                                <select class="form-select" name="hsn_id" id="quick_hsn_id">
                                    <option value="">Select HSN</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Product Code</label>
                                <input type="text" class="form-control" name="product_code" id="quick_product_code" placeholder="Auto if empty">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="product_name" id="quick_product_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">GST Type</label>
                                <select class="form-select quick-product-calc" name="gst_type" id="quick_gst_type">
                                    <option value="2" selected>Exclusive</option>
                                    <option value="1">Inclusive</option>
                                </select>
                                <small class="text-muted">Inclusive / Exclusive supported</small>
                            </div>
                            <input type="hidden" name="expire_days" id="quick_expire_days" value="0">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="quick_status">
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0">Pricing</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Stock / Purchase Price</label>
                                <input type="number" step="0.01" min="0" class="form-control quick-product-calc" name="cost_price" id="quick_cost_price" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">MRP</label>
                                <input type="number" step="0.01" min="0" class="form-control quick-product-calc" name="enter_mrp" id="quick_enter_mrp" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">MRP Discount</label>
                                <div class="input-group">
                                    <select class="form-select quick-product-calc" name="discount_type" id="quick_discount_type">
                                        <option value="1">%</option>
                                        <option value="2">₹</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" class="form-control quick-product-calc" name="discount_value" id="quick_discount_value" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Final MRP</label>
                                <input type="number" step="0.01" min="0" class="form-control bg-light" name="final_mrp" id="quick_final_mrp" value="0.00" readonly>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Retail Markup</label>
                                <div class="input-group">
                                    <select class="form-select quick-product-calc" name="retail_markup_type" id="quick_retail_markup_type">
                                        <option value="1">%</option>
                                        <option value="2">₹</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" class="form-control quick-product-calc" name="retail_markup_value" id="quick_retail_markup_value" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Retail Price</label>
                                <input type="number" step="0.01" min="0" class="form-control quick-product-calc" name="retail_price" id="quick_retail_price" value="0.00">
                            </div>
                            

                            <div class="col-md-12">
                                <div class="alert alert-light border mb-0">
                                    <b>Calculation Info:</b>
                                    <span id="quick_product_calc_info">Enter stock price, MRP, discount and markup.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-header py-2"><h6 class="mb-0">Unit & Conversion</h6></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Base Unit</label>
                                <select class="form-select" name="base_unit" id="quick_base_unit">
                                    <option value="Piece">Piece</option><option value="Box">Box</option><option value="Pack">Pack</option><option value="Case">Case</option><option value="Set">Set</option><option value="Dozen">Dozen</option><option value="KG">KG</option><option value="Gram">Gram</option><option value="Litre">Litre</option><option value="ML">ML</option><option value="Meter">Meter</option><option value="Feet">Feet</option><option value="Bottle">Bottle</option><option value="Number">Number</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Minimum Stock</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="minimum_stock" id="quick_minimum_stock" value="0.00">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input quick-product-calc" type="checkbox" id="quick_enable_secondary_unit" name="enable_secondary_unit" value="1">
                                    <label class="form-check-label" for="quick_enable_secondary_unit">Enable Box / Case / UPC secondary unit</label>
                                </div>
                            </div>
                            <div class="col-md-3 quick-secondary-unit-fields d-none">
                                <label class="form-label">Box / Case Label</label>
                                <select class="form-select quick-product-calc" name="secondary_unit_label" id="quick_secondary_unit_label">
                                    <option value="">Select</option><option value="Box">Box</option><option value="Case">Case</option><option value="Pack">Pack</option><option value="Carton">Carton</option><option value="Bundle">Bundle</option><option value="Bag">Bag</option>
                                </select>
                            </div>
                            <div class="col-md-3 quick-secondary-unit-fields d-none">
                                <label class="form-label">Pieces Per Box / UPC</label>
                                <input type="number" step="0.0001" min="1" class="form-control quick-product-calc" name="secondary_unit_value" id="quick_secondary_unit_value" value="">
                                <small class="text-muted">This will be saved as secondary unit value.</small>
                            </div>
                            <input type="hidden" name="box_label" id="quick_box_label" value="">
                            <input type="hidden" name="default_pieces_per_box" id="quick_default_pieces_per_box" value="1.0000">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <small class="text-muted me-auto">Save master only. Purchase tbody append happens only after clicking Add.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="saveQuickProductBtn">Save Product Master</button>
            </div>
        </form>
    </div>
</div>

<!-- HSN Quick Add Modal -->
<div class="modal fade" id="hsnModal" tabindex="-1" aria-labelledby="hsnModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="hsnForm">
            <?= csrfTokenInput(); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="hsnModalLabel">Add HSN Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_hsn_code">

                <div class="mb-3">
                    <label class="form-label">HSN Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="hsn_code" id="hsn_code" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="hsn_description" id="hsn_description">
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">CGST %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="cgst_percentage" id="hsn_cgst_percentage" value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SGST %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="sgst_percentage" id="hsn_sgst_percentage" value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">IGST %</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="igst_percentage" id="hsn_igst_percentage" value="0.00">
                    </div>
                </div>

                <small class="text-muted d-block mt-2">In purchase row CGST, SGST and IGST will be read-only after HSN selection.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveHsnBtn">Save HSN</button>
            </div>
        </form>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "purchases";
    window.PURCHASE_ID = <?= (int)$purchaseId; ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/purchase-form.js?v=<?= time(); ?>"></script>
</body>
</html>
