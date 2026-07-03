<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'My Profile | Universal ERP';
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
                                <h4 class="mb-0">My Profile</h4>
                                <p class="text-muted mb-0 mt-1">View and update your account details.</p>
                            </div>
                            <a href="<?= BASE_URL; ?>pages/dashboard.php" class="btn btn-light">
                                <i class="mdi mdi-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="avatar-lg mx-auto mb-3">
                                    <span class="avatar-title rounded-circle bg-primary text-white font-size-24" id="profileInitials">U</span>
                                </div>

                                <h5 class="mb-1" id="profileNameText">-</h5>
                                <p class="text-muted mb-2" id="profileUsernameText">-</p>

                                <div class="mt-3">
                                    <span class="badge bg-success" id="profileStatusText">Active</span>
                                    <span class="badge bg-info ms-1" id="profileTypeText">User</span>
                                </div>

                                <hr>

                                <div class="text-start">
                                    <p class="mb-2">
                                        <i class="mdi mdi-shield-account-outline text-primary me-2"></i>
                                        <strong>Role:</strong> <span id="profileRoleText">-</span>
                                    </p>
                                    <p class="mb-2">
                                        <i class="mdi mdi-store-outline text-primary me-2"></i>
                                        <strong>Business:</strong> <span id="profileBusinessText">-</span>
                                    </p>
                                    <p class="mb-0">
                                        <i class="mdi mdi-source-branch text-primary me-2"></i>
                                        <strong>Branch:</strong> <span id="profileBranchText">-</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Account Info</h5>
                                <p class="mb-2">
                                    <strong>Email:</strong><br>
                                    <span class="text-muted" id="profileEmailText">-</span>
                                </p>
                                <p class="mb-0">
                                    <strong>Mobile:</strong><br>
                                    <span class="text-muted" id="profileMobileText">-</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Profile Details</h5>
                            </div>
                            <div class="card-body">
                                <form id="profileForm" autocomplete="off">
                                    <?= csrfTokenInput(); ?>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter name">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" readonly>
                                            <small class="text-muted">Username cannot be changed from profile.</small>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Mobile</label>
                                            <input type="text" class="form-control" id="mobile" name="mobile" maxlength="20" placeholder="Enter mobile">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                            <i class="mdi mdi-content-save me-1"></i> Save Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form id="passwordForm" autocomplete="off">
                                    <?= csrfTokenInput(); ?>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Current password">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New password">
                                            <small class="text-muted">Minimum 6 characters.</small>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-warning" id="changePasswordBtn">
                                            <i class="mdi mdi-lock-reset me-1"></i> Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/profile.js?v=<?= time(); ?>"></script>
</body>
</html>
