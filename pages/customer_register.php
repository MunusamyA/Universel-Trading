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

$page_title = 'Customer Registration | Universal Trading';

/** @var PDO $pdo */
$packageRoles = [];

try {
    $stmt = $pdo->prepare("
        SELECT id, role_name, description
        FROM roles
        WHERE role_type = 1
        AND business_id IS NULL
        AND branch_id IS NULL
        AND status = 1
        ORDER BY id ASC
    ");
    $stmt->execute();
    $packageRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <div>
                                <h4 class="mb-0">Customer Registration</h4>
                                <p class="text-muted mb-0 mt-1">
                                    Create business, branch, admin login and selected package role.
                                </p>
                            </div>

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
                                        <p class="card-title-desc mb-0">
                                            Permission is inherited from selected package role through <b>roles.parent_role_id</b>.
                                        </p>
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
                                            </tr>
                                        </thead>

                                        <tbody id="customerRegisterTableBody">
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

                <div class="modal-header">
                    <h5 class="modal-title" id="customerRegisterModalTitle">Add Customer Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">

                    <div class="alert alert-info">
                        <strong>Note:</strong> This form does not copy permissions.
                        It stores selected package role id in <b>roles.parent_role_id</b>.
                    </div>

                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Registration Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="registration_mode" name="registration_mode">
                                    <option value="new_business">New Business + Main Branch</option>
                                    <option value="new_branch">New Branch Under Existing Business</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 existing-business-area d-none">
                            <div class="mb-3">
                                <label class="form-label">Existing Business <span class="text-danger">*</span></label>
                                <select class="form-select" id="existing_business_id" name="existing_business_id">
                                    <option value="">Select Business</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 existing-business-area d-none">
                            <div class="mb-3">
                                <label class="form-label">Main Branch <span class="text-danger">*</span></label>
                                <select class="form-select" id="parent_branch_id" name="parent_branch_id">
                                    <option value="">Select Main Branch</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <h5 class="font-size-15 mt-2 mb-3">Business Details</h5>

                    <div class="row">

                        <div class="col-lg-4 col-md-6 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="business_name" name="business_name" placeholder="Enter business name">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Owner / Login Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" placeholder="Enter owner name">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                <input type="text" maxlength="10" class="form-control" id="mobile" name="mobile" placeholder="10 digit mobile">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter email">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">GST Number</label>
                                <input type="text" class="form-control" id="gst_number" name="gst_number" placeholder="Enter GST number">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="Enter city">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" value="Tamil Nadu">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="pincode" name="pincode" placeholder="Enter pincode">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12 new-business-area">
                            <div class="mb-3">
                                <label class="form-label">Business Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter address"></textarea>
                            </div>
                        </div>

                    </div>

                    <h5 class="font-size-15 mt-2 mb-3">Branch Details</h5>

                    <div class="row">

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="branch_name" name="branch_name" value="Main Branch">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Branch Mobile</label>
                                <input type="text" maxlength="10" class="form-control" id="branch_mobile" name="branch_mobile" placeholder="Branch mobile">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Branch Email</label>
                                <input type="email" class="form-control" id="branch_email" name="branch_email" placeholder="Branch email">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Branch Address</label>
                                <textarea class="form-control" id="branch_address" name="branch_address" rows="2" placeholder="Branch address"></textarea>
                            </div>
                        </div>

                    </div>

                    <h5 class="font-size-15 mt-2 mb-3">Login & Package</h5>

                    <div class="row">

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter username">
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                                    <button class="btn btn-light" type="button" id="togglePassword">
                                        <i class="mdi mdi-eye-outline"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password">
                                    <button class="btn btn-light" type="button" id="toggleConfirmPassword">
                                        <i class="mdi mdi-eye-outline"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Package Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="package_role_id" name="package_role_id">
                                    <option value="">Select Package</option>
                                    <?php foreach ($packageRoles as $role) { ?>
                                        <option value="<?= (int)$role['id']; ?>">
                                            <?= escapeHtml($role['role_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small class="text-muted">Permission will be inherited from this package.</small>
                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light" id="customerRegisterBtn">
                        Save Registration
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php
$rightSidebarPath1 = BASE_PATH . 'includes/right-sidebar.php';
$rightSidebarPath2 = BASE_PATH . 'includes/rightsiderbar.php';

if (file_exists($rightSidebarPath1)) {
    include $rightSidebarPath1;
} elseif (file_exists($rightSidebarPath2)) {
    include $rightSidebarPath2;
}
?>

<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/customer_register.js"></script>

</body>
</html>
