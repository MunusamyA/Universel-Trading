<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

if (!isPlatformOwner()) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

/** @var PDO $pdo */
$page_title = 'Customer Registration | Universal ERP';

$packageRoles = [];

try {
    $packageStmt = $pdo->prepare("\n        SELECT id, role_name, description\n        FROM roles\n        WHERE role_type = 1\n        AND business_id IS NULL\n        AND branch_id IS NULL\n        AND status = 1\n        ORDER BY id ASC\n    ");
    $packageStmt->execute();
    $packageRoles = $packageStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $packageRoles = [];
}
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
                            <h4 class="mb-0">Customer Registration</h4>
                            <div class="page-title-right">
                                <button type="button" class="btn btn-primary waves-effect waves-light" id="addCustomerRegisterBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Registration
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-primary mt-2" id="totalBusinessCount">0</h3>
                                Total Businesses
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-success mt-2" id="activeBusinessCount">0</h3>
                                Active Businesses
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-info mt-2" id="approvedBranchCount">0</h3>
                                Approved Main Branches
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-warning mt-2" id="businessUserCount">0</h3>
                                Business Users
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h4 class="card-title mb-1">Registered Customers / Businesses</h4>
                                        <p class="card-title-desc mb-0">Use branch count icon to view all child branches under the main branch.</p>
                                    </div>
                                    <button type="button" class="btn btn-light btn-sm" id="refreshCustomerRegisterBtn">
                                        <i class="mdi mdi-refresh me-1"></i> Refresh
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                        <tr>
                                            <th width="60">#</th>
                                            <th>Business</th>
                                            <th>Owner</th>
                                            <th>Mobile</th>
                                            <th>Main Branch</th>
                                            <th>Username</th>
                                            <th>Package</th>
                                            <th>Branches</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th width="100">Action</th>
                                        </tr>
                                        </thead>
                                        <tbody id="customerRegisterTableBody">
                                        <tr>
                                            <td colspan="11" class="text-center text-muted">Loading...</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="customerRegisterModal" tabindex="-1" aria-labelledby="customerRegisterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="customerRegisterForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="registration_id" id="registration_id" value="">
                <input type="hidden" name="edit_branch_id" id="edit_branch_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="customerRegisterModalTitle">Add Customer Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">

                    <h5 class="font-size-15 mb-3">Registration Type</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="registration_mode">Registration Mode <span class="text-danger">*</span></label>
                                <select class="form-select" id="registration_mode" name="registration_mode">
                                    <option value="new_business">Create New Business</option>
                                    <option value="existing_business">Select Existing Business & Create Another Branch</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="existingBusinessSection" style="display:none;">
                        <h5 class="font-size-15 mb-3 mt-3">Existing Business / Main Branch</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="existing_business_id">Select Existing Business <span class="text-danger">*</span></label>
                                    <select class="form-select" id="existing_business_id" name="existing_business_id">
                                        <option value="">Select Existing Business</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="parent_branch_id" id="parent_branch_label">Select Main Branch <span class="text-danger">*</span></label>
                                    <select class="form-select" id="parent_branch_id" name="parent_branch_id">
                                        <option value="">Select Main Branch</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="newBusinessSection">
                        <h5 class="font-size-15 mb-3 mt-3">Business Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="business_name">Business Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" placeholder="Enter business name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="gst_number">GST Number</label>
                                    <input type="text" class="form-control" id="gst_number" name="gst_number" placeholder="Enter GST number">
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-3"><span id="branchDetailsTitle">Main Branch Details</span></h5>
                    <div class="row">
                        <div class="col-md-6 existing-only" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label" for="branch_name">Branch Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="branch_name" name="branch_name" placeholder="Enter branch name">
                            </div>
                        </div>
                        <div class="col-md-6 existing-only" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label" for="branch_code">Branch Code</label>
                                <input type="text" class="form-control" id="branch_code" name="branch_code" placeholder="Auto generated if empty">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="owner_name">Login / Owner Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" placeholder="Enter login display name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="mobile">Mobile <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10" placeholder="Enter mobile number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address">
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

                    <h5 class="font-size-15 mb-3 mt-3">Login Details</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter username">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                                    <button class="btn btn-light" type="button" id="togglePassword"><i class="mdi mdi-eye-outline"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label" for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password">
                                    <button class="btn btn-light" type="button" id="toggleConfirmPassword"><i class="mdi mdi-eye-outline"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mb-3 mt-3">Package Role Access</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="package_role_id">Select Package Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="package_role_id" name="package_role_id">
                                    <option value="">Loading package roles...</option>
                                </select>
                                <small class="text-muted">Selected package permissions will be copied to locked Branch Admin role.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info mb-3">
                                <strong>Note:</strong> For existing business, this will create another branch under the selected main branch.
                            </div>
                        </div>
                    </div>

                    <?php if (empty($packageRoles)) { ?>
                        <div class="alert alert-warning mb-0">
                            No package roles found. Create Basic / Premium / Gold roles with <b>role_type = 1</b> first.
                        </div>
                    <?php } ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="customerRegisterBtn">Save Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="branchListModal" tabindex="-1" aria-labelledby="branchListModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchListModalTitle">Branches Under Main Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-centered table-nowrap mb-0">
                        <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>Branch</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Username</th>
                            <th>Package</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody id="branchListTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted">Select branch count icon to load branches.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.PRELOADED_PACKAGE_ROLES = <?= json_encode($packageRoles); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/customer_register.js?v=<?= time(); ?>"></script>
</body>
</html>
