<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = "Roles & Permissions | Universal Trading";
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
                                <button type="button" class="btn btn-primary waves-effect waves-light" id="addRoleBtn">
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
                                                <td colspan="5" class="text-center text-muted">Loading...</td>
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
                <input type="hidden" id="status" name="status" value="1">

                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalTitle">Add Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">

                    <div class="row">

                        <div class="col-lg-6 col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="role_name">
                                    Role Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="role_name" name="role_name" placeholder="Enter role name">
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-12">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-switch form-switch-md">
                                <input class="form-check-input" type="checkbox" id="statusSwitch" checked>
                                <label class="form-check-label" for="statusSwitch">Active</label>
                            </div>
                        </div>

                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Enter description"></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0">Menu Permissions</h5>

                        <div>
                            <button type="button" class="btn btn-light btn-sm" id="checkViewListOnly">View/List Only</button>
                            <button type="button" class="btn btn-light btn-sm" id="checkAllPermissions">Check All</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Menu</th>
                                    <th>Allowed Actions</th>
                                </tr>
                            </thead>
                            <tbody id="permissionTableBody">
                                <tr>
                                    <td colspan="2" class="text-center text-muted">Select role to load permissions.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2 text-muted">
                        <small>
                            Actions are shown as numbers from <b>sidebar_menus.allowed_actions</b>.
                            Meaning is stored in database column comment.
                        </small>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light" id="saveRoleBtn">Save Role</button>
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
    window.USER_TYPE = <?= json_encode(currentUserType()); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/roles.js"></script>

</body>
</html>
