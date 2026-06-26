<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Suppliers | Universal ERP';
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
                            <h4 class="mb-0">Suppliers</h4>
                            <div class="page-title-right">
                                <button type="button" class="btn btn-primary waves-effect waves-light" id="addSupplierBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Supplier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-primary mt-2" id="totalSuppliersCount">0</h3>
                                Total Suppliers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-success mt-2" id="activeSuppliersCount">0</h3>
                                Active Suppliers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-danger mt-2" id="inactiveSuppliersCount">0</h3>
                                Inactive Suppliers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-warning mt-2" id="totalOutstandingAmount">₹0.00</h3>
                                Outstanding
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="supplierSearch" placeholder="Name / Mobile / GST / PAN / Bank">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="supplierStatusFilter">
                                    <option value="0">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-5 text-end">
                                <button type="button" class="btn btn-light" id="refreshSuppliersBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Supplier</th>
                                    <th>Contact</th>
                                    <th>GST / PAN</th>
                                    <th>DL / FL</th>
                                    <th>Bank</th>
                                    <th>Outstanding</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                                </thead>
                                <tbody id="supplierTableBody">
                                <tr>
                                    <td colspan="9" class="text-center text-muted">Loading...</td>
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

<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="supplierForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="supplier_id" id="supplier_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="supplierModalTitle">Add Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">
                    <h5 class="font-size-15 mb-3">Supplier Details</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Enter supplier name">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="mobile">Mobile</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10" placeholder="Enter mobile number">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="gst_number">GST Number</label>
                                <input type="text" class="form-control text-uppercase" id="gst_number" name="gst_number" maxlength="20" placeholder="GSTIN">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="pan_number">PAN Number</label>
                                <input type="text" class="form-control text-uppercase" id="pan_number" name="pan_number" maxlength="50" placeholder="PAN">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="dl_number">DL Number</label>
                                <input type="text" class="form-control text-uppercase" id="dl_number" name="dl_number" maxlength="100" placeholder="DL Number">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="fl_number">FL Number</label>
                                <input type="text" class="form-control text-uppercase" id="fl_number" name="fl_number" maxlength="100" placeholder="FL Number">
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-3">Address Details</h5>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="address">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter address"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="city">City</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="Enter city">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="state">State</label>
                                <input type="text" class="form-control" id="state" name="state" value="Tamil Nadu" placeholder="Enter state">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="pincode">Pincode</label>
                                <input type="text" class="form-control" id="pincode" name="pincode" maxlength="6" placeholder="Enter pincode">
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-3">Bank Details</h5>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="bank_name">Bank Name</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="Enter bank name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="bank_account_no">Account Number</label>
                                <input type="text" class="form-control" id="bank_account_no" name="bank_account_no" placeholder="Enter account number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="bank_branch">Bank Branch</label>
                                <input type="text" class="form-control" id="bank_branch" name="bank_branch" placeholder="Enter branch">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="bank_ifsc">IFSC Code</label>
                                <input type="text" class="form-control text-uppercase" id="bank_ifsc" name="bank_ifsc" maxlength="20" placeholder="Enter IFSC">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label" for="upi_id">UPI ID / QR Details</label>
                                <input type="text" class="form-control" id="upi_id" name="upi_id" placeholder="Enter UPI ID or QR details">
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-3">Outstanding & Status</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="opening_outstanding">Opening Outstanding</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="opening_outstanding" name="opening_outstanding" value="0.00">
                                <small class="text-muted">Add supplier: current outstanding = opening outstanding.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveSupplierBtn">Save Supplier</button>
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
<script src="<?= BASE_URL; ?>pages-js/suppliers.js?v=<?= time(); ?>"></script>
</body>
</html>
