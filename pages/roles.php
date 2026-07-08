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

<?php
$preloaderPath = BASE_PATH . 'includes/pre-loader.php';
if (file_exists($preloaderPath)) {
    include $preloaderPath;
}
?>

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
                                <h4 class="mb-0" id="pageTitleText">Roles & Permissions</h4>
                                <p class="text-muted mb-0 mt-1" id="pageSubTitleText">Loading...</p>
                            </div>

                            <div class="page-title-right">
                                <button type="button" class="btn btn-primary waves-effect waves-light d-none" id="addRoleBtn">
                                    <i class="mdi mdi-plus me-1"></i>
                                    <span id="addRoleBtnText">Add Role</span>
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
                                Permission Menus
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
                                        <h4 class="card-title mb-1">Role List</h4>
                                        <p class="card-title-desc mb-0" id="roleListNote">Loading...</p>
                                    </div>

                                    <button type="button" class="btn btn-light btn-sm" id="refreshRolesBtn">
                                        <i class="mdi mdi-refresh me-1"></i> Refresh
                                    </button>
                                </div>

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

                    <div class="alert alert-info" id="roleModalNote">
                        Loading permission control...
                    </div>

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
                        <div>
                            <h5 class="mb-0">Menu Permissions</h5>
                            <small class="text-muted" id="permissionSourceNote">Loading...</small>
                        </div>

                        <div>
                            <button type="button" class="btn btn-light btn-sm" id="checkViewListOnly">
                                View/List Only
                            </button>

                            <button type="button" class="btn btn-light btn-sm" id="checkAllPermissions">
                                Check All
                            </button>
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
                                    <td colspan="2" class="text-center text-muted">
                                        Loading permissions...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2 text-muted">
                        <small>
                            Database stores only action numbers like <b>1,2,3</b>. Names are shown only in UI.
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

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.USER_TYPE = "<?= escapeHtml(currentUserType()); ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/branch_approvals.js"></script>

</body>
</html>
