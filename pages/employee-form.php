<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$employeeId = (int)($_GET['id'] ?? 0);
$page_title = ($employeeId > 0 ? 'Edit Employee' : 'Add Employee') . ' | Universal ERP';
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
                            <h4 class="mb-0"><?= $employeeId > 0 ? 'Edit Employee' : 'Add Employee'; ?></h4>
                            <a href="<?= BASE_URL; ?>pages/employees.php" class="btn btn-light">
                                <i class="mdi mdi-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>

                <form id="employeeForm" autocomplete="off">
                    <?= csrfTokenInput(); ?>
                    <input type="hidden" name="employee_id" id="employee_id" value="<?= $employeeId; ?>">

                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Employee Details</h5></div>
                        <div class="card-body">
                            <div class="alert alert-info">Employee will be created under current login branch. Role dropdown shows only current branch roles. No modal used.</div>

                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" class="form-control" id="employee_code" name="employee_code" placeholder="Auto if empty">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Employee Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="employee_name" name="employee_name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Branch Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role_id" name="role_id"><option value="">Select Role</option></select>
                                    <small class="text-muted">Locked Branch Admin role will not show.</small>
                                </div>

                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="0">Select</option>
                                        <option value="1">Male</option>
                                        <option value="2">Female</option>
                                        <option value="3">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dob" name="dob">
                                </div>

                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Joining Date</label>
                                    <input type="date" class="form-control" id="joining_date" name="joining_date">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control" id="designation" name="designation">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Salary</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="salary" name="salary" value="0.00">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status"><option value="1">Active</option><option value="2">Inactive</option></select>
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                </div>
                                <div class="col-md-2 mt-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                                <div class="col-md-2 mt-3">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>
                                <div class="col-md-2 mt-3">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" class="form-control" id="pincode" name="pincode">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Login Password</h5></div>
                        <div class="card-body">
                            <div class="alert alert-warning edit-password-note d-none">Leave password fields empty if you do not want to change password.</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger new-password-required">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Minimum 6 characters">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password <span class="text-danger new-password-required">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mb-4">
                        <a href="<?= BASE_URL; ?>pages/employees.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="saveEmployeeBtn">Save Employee</button>
                    </div>
                </form>

            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>window.BASE_URL = "<?= BASE_URL; ?>"; window.EMPLOYEE_ID = <?= $employeeId; ?>;</script>
<script src="<?= BASE_URL; ?>pages-js/employee-form.js?v=<?= time(); ?>"></script>
</body>
</html>
