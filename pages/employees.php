<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Employees | Universal ERP';
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
</head>

<body data-sidebar="dark">

<?php include BASE_PATH . 'includes/pre-loader.php'; ?>

<?= csrfTokenInput(); ?>

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
                                <h4 class="mb-0" id="pageTitleText">Employees</h4>
                                <p class="text-muted mb-0 mt-1" id="pageNoteText">Loading...</p>
                            </div>

                            <a href="<?= BASE_URL; ?>pages/employee-form.php" class="btn btn-primary d-none" id="addEmployeeBtn">
                                <i class="mdi mdi-plus me-1"></i>
                                <span id="addEmployeeBtnText">Add Employee</span>
                            </a>
                        </div>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="totalEmployeesCount">0</h3>
                                Total Employees
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="activeEmployeesCount">0</h3>
                                Active Employees
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-danger" id="inactiveEmployeesCount">0</h3>
                                Inactive Employees
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">

                        <div class="row align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" id="employeeSearch" placeholder="Code / Name / Username / Mobile / Role">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" id="roleFilter">
                                    <option value="0">All Roles</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="employeeStatusFilter">
                                    <option value="0">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-3 text-end">
                                <button class="btn btn-light" id="refreshEmployeesBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
                                        <th>Employee</th>
                                        <th>Username</th>
                                        <th>Contact</th>
                                        <th>Designation</th>
                                        <th>Role</th>
                                        <th>Salary</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody id="employeeTableBody">
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

<?php
$rightbarPath1 = BASE_PATH . 'includes/rightbar.php';

if (file_exists($rightbarPath1)) {
    include $rightbarPath1;
}
?>

<?php
$scriptsPath1 = BASE_PATH . 'includes/scripts.php';

if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "employees";
</script>

<script src="<?= BASE_URL; ?>pages-js/employees.js?v=<?= time(); ?>"></script>

</body>
</html>
