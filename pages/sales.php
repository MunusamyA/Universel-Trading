<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Sales Entry | Universal ERP';

$pageMode = cleanInput($_GET['mode'] ?? 'new');
$editSaleId = (int)($_GET['id'] ?? 0);
$sourceSaleId = (int)($_GET['source_id'] ?? 0);
$sourceType = (int)($_GET['source_type'] ?? 0);
$targetType = (int)($_GET['target_type'] ?? 0);

if ($editSaleId > 0 && $pageMode !== 'convert') {
    $pageMode = 'edit';
}

$salesPageConfig = [
    'mode' => $pageMode,
    'id' => $editSaleId,
    'source_id' => $sourceSaleId,
    'source_type' => $sourceType,
    'target_type' => $targetType,
    'document_types' => [],
    'allowed_document_types' => [],
    'permissions' => []
];

?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>

<style>
    body[data-sidebar="dark"] {
        background: #f5f7fb;
    }
    .pos-sales-wrapper {
        min-height: 100vh;
    }
    .pos-main-content {
        margin-left: 0 !important;
        padding-top: 0 !important;
        min-height: 100vh;
    }
    .pos-page-content {
        padding: 12px !important;
        margin-top: 0 !important;
    }
    .pos-page-content .container-fluid {
        max-width: 100% !important;
        padding-left: 8px;
        padding-right: 8px;
    }
    .page-title-box {
        padding: 8px 0 14px 0 !important;
        margin-bottom: 0 !important;
    }

    .pos-compact-topbar {
        position: sticky;
        top: 0;
        z-index: 1055;
        background: #ffffff;
        border: 1px solid #e9edf3;
        border-radius: 8px;
        padding: 6px 8px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    }
    .pos-compact-topbar .btn-sm {
        padding: 3px 10px;
        line-height: 1.4;
    }
    #posCurrentTime {
        letter-spacing: .4px;
        min-width: 82px;
        display: inline-block;
        text-align: center;
    }


    /* Compact POS page */
    .pos-page-content {
        padding: 6px !important;
    }
    .pos-page-content .container-fluid {
        padding-left: 4px !important;
        padding-right: 4px !important;
    }
    .page-title-box {
        padding: 4px 0 8px 0 !important;
    }
    .page-title-box h4 {
        font-size: 16px;
    }
    .page-title-right {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .page-title-right .btn {
        padding: 4px 8px;
        font-size: 12px;
        line-height: 1.35;
    }
    .card {
        margin-bottom: 8px;
    }
    .card-body {
        padding: 10px !important;
    }
    .font-size-15 {
        font-size: 14px !important;
    }
    .form-label {
        margin-bottom: 3px;
        font-size: 12px;
    }
    .mb-3 {
        margin-bottom: 8px !important;
    }
    .row {
        --bs-gutter-x: 8px;
    }
    .form-control,
    .form-select,
    .input-group-text,
    .btn {
        font-size: 12px;
    }
    .form-control,
    .form-select,
    .input-group-text {
        padding-top: 5px;
        padding-bottom: 5px;
    }
    .table > :not(caption) > * > * {
        padding: 5px 6px;
        font-size: 12px;
    }
    .pos-compact-topbar {
        padding: 4px 6px;
        margin-bottom: 6px !important;
    }


    .compact-product-modal {
        padding: 10px !important;
    }
    .compact-product-modal .table > :not(caption) > * > * {
        padding: 4px 6px;
    }
    .modal-xl {
        max-width: 1120px;
    }


    .selected-batch-entry-table input,
    .selected-batch-entry-table select {
        min-width: 70px;
        font-size: 11px;
        padding: 3px 5px;
    }
    .selected-batch-entry-table .batch-title {
        font-size: 12px;
        font-weight: 600;
    }
    .selected-batch-entry-table .batch-sub {
        font-size: 10px;
    }


    #productEntryModal .modal-xl {
        max-width: 96vw;
    }
    #productEntryModal .modal-body {
        max-height: 68vh;
        overflow-y: auto;
    }

    .sales-inline-input {
        min-width: 82px;
        max-width: 110px;
        display: inline-block;
        padding: 3px 6px;
        font-size: 11px;
    }
    .sales-inline-muted {
        font-size: 10px;
        color: #6c757d;
    }
    .profit-summary-card {
        border: 1px solid #e9edf3;
        border-radius: 8px;
        padding: 10px;
        background: #fbfcff;
        height: 100%;
    }

</style>

</head>

<body data-sidebar="dark">


<div id="pos-sales-wrapper" class="pos-sales-wrapper">
    

    

    <div class="main-content pos-main-content">
        <div class="page-content pos-page-content">
            <div class="container-fluid">
                <div class="pos-compact-topbar d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">POS Sales</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Time</span>
                        <span class="badge bg-dark font-size-13" id="posCurrentTime">--:--:--</span>
                    </div>
                </div>


                <input type="hidden" id="saleId" value="<?= (int)($_GET['id'] ?? 0); ?>">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Sales Entry</h4>
                            <div class="page-title-right">
                                <a href="<?= BASE_URL; ?>pages/all-sales-list.php" class="btn btn-light d-none" id="salesListNavBtn">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Sales List
                                </a>
                                <button type="button" class="btn btn-warning d-none" id="holdBillBtn">
                                    <i class="mdi mdi-pause-circle-outline me-1"></i> Hold Bill
                                </button>
                                <button type="button" class="btn btn-info d-none" id="holdBillsListBtn">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Hold Bills
                                </button>
                                <button type="button" class="btn btn-outline-danger d-none" id="clearDraftBtn">
                                    <i class="mdi mdi-delete-sweep-outline me-1"></i> Clear Draft
                                </button>
                                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="btn btn-danger" id="posExitBtn" title="Exit POS">
                                    <i class="mdi mdi-exit-to-app me-1"></i> Exit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="salesForm" autocomplete="off">
                    <?= csrfTokenInput(); ?>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Document Details</h5>
                            <div class="alert alert-warning py-2 d-none" id="salesPermissionAlert">
                                You do not have permission to create or generate sales documents.
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="documentType" name="document_type">
                                            <option value="">Loading...</option>
                                        </select>
                                        <small class="text-muted" id="documentModeText">Role based document type</small>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Invoice Type <span class="text-danger">*</span></label>
                                        <input type="hidden" id="invoiceType" name="invoice_type" value="1">
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="invoiceTypeSwitch" checked>
                                            <label class="form-check-label" for="invoiceTypeSwitch" id="invoiceTypeLabel">GST Invoice</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Sales Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="salesDate" name="sales_date" value="<?= date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Common Due Date</label>
                                        <input type="date" class="form-control" id="dueDate" name="due_date">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Zone</label>
                                        <select class="form-select" id="customerZoneFilter" name="customer_zone_filter">
                                            <option value="">All Zones</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Customer</label>
                                        <input type="hidden" id="customerId" name="customer_id">
                                        <div class="position-relative">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="customerSearch" placeholder="Search customer / Walk-in">
                                                <button type="button" class="btn btn-primary d-none" id="addCustomerBtn" title="Add Customer">
                                                    <i class="mdi mdi-plus"></i>
                                                </button>
                                            </div>
                                            <div id="customerSuggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1050; max-height:260px; overflow-y:auto; top:100%; left:0; margin-top:0;"></div>
                                        </div>
                                        <small id="customerInfo" class="text-muted"></small>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Delivery Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="deliveryAddress" name="delivery_address" rows="2" placeholder="For walk-in, enter delivery address manually"></textarea>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-2">Product Selection</h5>
                            <div class="row align-items-end">
                                <div class="col-lg-8">
                                    <div class="mb-2">
                                        <label class="form-label">Search Product</label>
                                        <div class="position-relative">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="productSearch" placeholder="Search product code / name">
                                                <button type="button" class="btn btn-outline-secondary" id="clearProductSearchBtn" title="Clear product">
                                                    Clear
                                                </button>
                                            </div>
                                            <div id="productSuggestions" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1050; max-height:260px; overflow-y:auto; top:100%; left:0; margin-top:0;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Sales Items</h5>

                            <div class="table-responsive">
                                <table class="table table-centered table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Batch</th>
                                            <th class="text-end">Unit / Qty</th>
                                            <th class="text-end">Qty/Unit</th>
                                            <th class="text-end">Total Qty</th>
                                            <th class="text-end">Rate</th>
                                            <th class="text-end">Discount</th>
                                            <th class="text-end">GST</th>
                                            <th class="text-end">Total</th>
                                            <th width="80">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">No items added.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h5 class="font-size-15 mb-0">Payments</h5>
                                        <button type="button" class="btn btn-outline-primary btn-sm d-none" id="addPaymentBtn">
                                            <i class="mdi mdi-plus me-1"></i> Add Payment
                                        </button>
                                    </div>

                                    <div id="paymentsBox">
                                        <div class="text-muted">Payment optional for quotation/proforma.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="font-size-15 mb-3">Summary</h5>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Sub Total</span>
                                        <strong id="summarySubTotal">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Discount</span>
                                        <strong id="summaryDiscount">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Tax</span>
                                        <strong id="summaryTax">₹0.00</strong>
                                    </div>

                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <select class="form-select form-select-sm" id="headerDiscountType">
                                                <option value="1">Discount %</option>
                                                <option value="2">Discount ₹</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="headerDiscountValue" value="0">
                                        </div>
                                    </div>

                                    <div class="row mb-2 align-items-center">
                                        <div class="col-6">
                                            <span class="text-muted">Shipping Charges</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="shippingCharges" value="0">
                                        </div>
                                    </div>

                                    <div class="row mb-3 align-items-center">
                                        <div class="col-6">
                                            <span class="text-muted">Round Off</span>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control text-end" id="roundOff" value="0.00" inputmode="decimal" autocomplete="off">
                                                <button type="button" class="btn btn-outline-primary" id="roundOffToggleBtn">Round</button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Grand Total</span>
                                        <h5 class="mb-0" id="summaryGrandTotal">₹0.00</h5>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Paid</span>
                                        <strong id="summaryPaid">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Due</span>
                                        <strong class="text-danger" id="summaryDue">₹0.00</strong>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <div class="d-flex flex-wrap justify-content-end gap-2" id="salesActionButtons">
                                        <button type="button" class="btn btn-outline-success d-none" id="savePrintSaleBtn">
                                            <i class="mdi mdi-printer-check me-1"></i> Save & Print
                                        </button>
                                        <button type="button" class="btn btn-success d-none" id="saveSaleBtn">
                                            <i class="mdi mdi-content-save me-1"></i> Save
                                        </button>
                                        <button type="button" class="btn btn-outline-dark" id="profitCheckBtn">
                                            <i class="mdi mdi-chart-line me-1"></i> Profit
                                        </button>
                                        <button type="button" class="btn btn-outline-primary d-none sales-convert-btn" data-target-type="2" id="convertProformaBtn">
                                            <i class="mdi mdi-file-document-plus-outline me-1"></i> Generate Proforma Bill
                                        </button>
                                        <button type="button" class="btn btn-outline-info d-none sales-convert-btn" data-target-type="3" id="convertSalesBillBtn">
                                            <i class="mdi mdi-receipt-text-plus-outline me-1"></i> Generate Sales Bill
                                        </button>
                                        <button type="button" class="btn btn-warning d-none sales-convert-btn" data-target-type="5" id="generateInvoiceBtn">
                                            <i class="mdi mdi-receipt-text-check-outline me-1"></i> Generate Final Invoice
                                        </button>
                                        <button type="button" class="btn btn-secondary d-none" id="printSaleBtn">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>

            </div>
        </div>
        
    </div>
</div>





<div class="modal fade" id="productEntryModal" tabindex="-1" aria-labelledby="productEntryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h5 class="modal-title mb-0" id="productEntryModalTitle">Add Product</h5>
                    <small class="text-muted" id="productEntryModalSubTitle">Enter details batch-wise. Qty entered rows only will be added.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body compact-product-modal">
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-centered mb-0 selected-batch-entry-table">
                        <thead>
                            <tr>
                                <th style="min-width:150px;">Batch</th>
                                <th class="text-end" style="min-width:85px;">Available</th>
                                <th class="text-end" style="min-width:85px;">Purchase</th>
                                <th class="text-end" style="min-width:90px;" id="salesUnitHeader">Unit Qty</th>
                                <th class="text-end" style="min-width:90px;" id="salesQtyPerUnitHeader">Qty/Unit</th>
                                <th class="text-end" style="min-width:90px;">Total Qty</th>
                                <th style="min-width:115px;">Price Type</th>
                                <th class="text-end" style="min-width:95px;">Rate</th>
                                <th style="min-width:90px;">Disc Type</th>
                                <th class="text-end" style="min-width:95px;">Disc Value</th>
                                <th class="text-end" style="min-width:90px;">GST</th>
                            </tr>
                        </thead>
                        <tbody id="selectedBatchDetailsBody">
                            <tr>
                                <td colspan="11" class="text-center text-muted">Search and select product to load batch-wise inputs.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted d-block mt-1">Each batch has separate Unit Qty, Price Type and Discount. GST is auto-filled from product / HSN / purchase batch and cannot be edited.</small>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-light d-none" id="cancelEditItemBtn">Cancel Edit</button>
                <button type="button" class="btn btn-primary" id="addItemBtn">
                    <i class="mdi mdi-plus me-1"></i> Add Item
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="profitModal" tabindex="-1" aria-labelledby="profitModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="profitModalTitle">Current Profit Details</h5>
                    <small class="text-muted">Calculated from current sales item rate, purchase cost, discount, GST and round-off.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3" id="profitSummaryCards">
                    <div class="col-md-3"><div class="profit-summary-card"><small class="text-muted">Net Sales before GST</small><h5 class="mb-0" id="profitSalesTotal">₹0.00</h5></div></div>
                    <div class="col-md-3"><div class="profit-summary-card"><small class="text-muted">Purchase Cost</small><h5 class="mb-0" id="profitCostTotal">₹0.00</h5></div></div>
                    <div class="col-md-3"><div class="profit-summary-card"><small class="text-muted">Profit</small><h5 class="mb-0" id="profitAmountTotal">₹0.00</h5></div></div>
                    <div class="col-md-3"><div class="profit-summary-card"><small class="text-muted">Profit %</small><h5 class="mb-0" id="profitPercentTotal">0.00%</h5></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-centered mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Sale Rate</th>
                                <th class="text-end">Cost Rate</th>
                                <th class="text-end">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="profitDetailsBody">
                            <tr><td colspan="6" class="text-center text-muted">No items added.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="customerForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="customer_id" id="customer_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">
                    <h5 class="font-size-15 mb-3">Customer Details</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Enter customer name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zone <span class="text-danger">*</span></label>
                            <select class="form-select" id="zone_id" name="zone_id">
                                <option value="">Select Zone</option>
                            </select>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10" placeholder="Enter mobile">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="form-label">GST Number</label>
                            <input type="text" class="form-control text-uppercase" id="gst_number" name="gst_number" placeholder="GSTIN">
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-4">Address Details</h5>

                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter address"></textarea>
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" placeholder="Enter city">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" id="state" name="state" value="Tamil Nadu" placeholder="Enter state">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="form-label">Pincode</label>
                            <input type="text" class="form-control" id="pincode" name="pincode" maxlength="6" placeholder="Enter pincode">
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-4">Outstanding & Status</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Opening Outstanding</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="opening_outstanding" name="opening_outstanding" value="0.00">
                            <small class="text-muted">Add customer: current outstanding = opening outstanding.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status1" name="status">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary d-none" id="saveCustomerBtn">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="holdBillsModal" tabindex="-1" aria-labelledby="holdBillsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="holdBillsModalTitle">Hold Bills</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-centered table-nowrap mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Date / Time</th>
                                <th class="text-end">Amount</th>
                                <th width="130">Action</th>
                            </tr>
                        </thead>
                        <tbody id="holdBillsTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">No hold bills.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.SALES_PAGE_CONFIG = <?= json_encode($salesPageConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/sales.js?v=<?= time(); ?>"></script>
</body>
</html>
