<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

/** @var PDO $pdo */

secureSessionStart();
requireLogin();

$page_title = "Roles & Permissions | Universal ERP";
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
                            <h4 class="mb-0">Roles & Permissions</h4>

                            <div class="page-title-right">
                                <button type="button"
                                        class="btn btn-primary waves-effect waves-light"
                                        id="addRoleBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Role
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-primary mt-2" id="totalRolesCount">0</h3>
                                Total Roles
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-success mt-2" id="activeRolesCount">0</h3>
                                Active Roles
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-danger mt-2" id="inactiveRolesCount">0</h3>
                                Inactive Roles
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-info mt-2" id="menuCount">0</h3>
                                Sidebar Menus
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-12">

                        <div class="card">
                            <div class="card-body">

                                <h4 class="card-title mb-3">Role List</h4>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Role Name</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th width="180">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody id="rolesTableBody">
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    Loading...
                                                </td>
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

<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <form id="roleForm">

                <?= csrfTokenInput(); ?>

                <input type="hidden" id="role_id" name="role_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalTitle">Add Role</h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">

                    <div class="row">

                        <div class="col-lg-6 col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="role_name">
                                    Role Name <span class="text-danger">*</span>
                                </label>

                                <input type="text"
                                       class="form-control"
                                       id="role_name"
                                       name="role_name"
                                       placeholder="Enter role name">
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-12">
                            <div class="mb-3">
                                <label class="form-label d-block" for="statusSwitch">
                                    Status
                                </label>

                                <input type="checkbox"
                                       id="statusSwitch"
                                       switch="primary"
                                       checked>

                                <label for="statusSwitch"
                                       data-on-label="On"
                                       data-off-label="Off"></label>

                                <input type="hidden"
                                       id="status"
                                       name="status"
                                       value="1">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="description">
                                    Description
                                </label>

                                <textarea class="form-control"
                                          id="description"
                                          name="description"
                                          rows="2"
                                          placeholder="Enter description"></textarea>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-2 mb-3">
                        <h5 class="font-size-15 mb-0">Menu Permissions</h5>

                        <div>
                            <button type="button"
                                    class="btn btn-sm btn-light"
                                    id="checkViewOnly">
                                View Only
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-light"
                                    id="checkAllPermissions">
                                Check All
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1250px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 250px;">Menu</th>
                                    <th class="text-center">View</th>
                                    <th class="text-center">Add</th>
                                    <th class="text-center">Edit</th>
                                    <th class="text-center">Delete</th>
                                    <th class="text-center">Print</th>
                                    <th class="text-center">Export</th>
                                    <th class="text-center">Approve</th>
                                    <th class="text-center">Convert</th>
                                    <th class="text-center">Adjust</th>
                                    <th class="text-center">Ship</th>
                                    <th class="text-center">Generate Invoice</th>
                                </tr>
                            </thead>

                            <tbody id="permissionTableBody">
                                <tr>
                                    <td colspan="12" class="text-center text-muted">
                                        Loading menus...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="saveRoleBtn">
                        Save Role
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.USER_TYPE = "<?= escapeHtml($_SESSION['user_type'] ?? 'business_user'); ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/roles.js?v=<?= time(); ?>"></script>

</body>
</html>