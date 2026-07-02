<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$customerId = (int)($_GET['id'] ?? 0);
$page_title = ($customerId > 0 ? 'Edit Customer' : 'Add Customer') . ' | Universal ERP';
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
                            <div>
                                <h4 class="mb-0" id="customerPageTitle"><?= $customerId > 0 ? 'Edit Customer' : 'Add Customer'; ?></h4>
                                <small class="text-muted">Customer create / edit separate page</small>
                            </div>
                            <div>
                                <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light">
                                    <i class="mdi mdi-arrow-left me-1"></i> Customer List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="customerForm" autocomplete="off">
                    <?= csrfTokenInput(); ?>
                    <input type="hidden" name="customer_id" id="customer_id" value="<?= (int)$customerId; ?>">

                    <div class="card">
                        <div class="card-body">
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
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Address Details</h5>

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
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Outstanding & Status</h5>

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
                    </div>

                    <div class="card">
                        <div class="card-body text-end">
                            <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="saveCustomerBtn">
                                <i class="mdi mdi-content-save me-1"></i> Save Customer
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.CUSTOMER_FORM_CONFIG = {
        customer_id: <?= (int)$customerId; ?>
    };
</script>
<script src="<?= BASE_URL; ?>pages-js/customers-create.js?v=<?= time(); ?>"></script>
</body>
</html>
