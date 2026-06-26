<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Customers | Universal ERP';
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
                            <h4 class="mb-0">Customers</h4>
                            <div class="page-title-right">
                                <button type="button" class="btn btn-primary" id="addCustomerBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Customer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="totalCustomersCount">0</h3>
                                Total Customers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="activeCustomersCount">0</h3>
                                Active Customers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="inactiveCustomersCount">0</h3>
                                Inactive Customers
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-warning" id="totalOutstandingAmount">₹0.00</h3>
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
                                <input type="text" class="form-control" id="customerSearch" placeholder="Name / Mobile / GST / Zone">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Zone</label>
                                <select class="form-select" id="zoneFilter">
                                    <option value="0">All Zones</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="customerStatusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button class="btn btn-light" id="refreshCustomersBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Customer</th>
                                    <th>Zone</th>
                                    <th>Contact</th>
                                    <th>GST</th>
                                    <th>Outstanding</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                                </thead>
                                <tbody id="customerTableBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Loading...</td>
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
                            <select class="form-select" id="status" name="status">
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveCustomerBtn">Save Customer</button>
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
<script src="<?= BASE_URL; ?>pages-js/customers.js?v=<?= time(); ?>"></script>
</body>
</html>
