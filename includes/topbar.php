<?php
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Topbar
|--------------------------------------------------------------------------
| Common topbar for Universal ERP.
| This file is included inside pages after auth/session load.
*/

if (session_status() === PHP_SESSION_NONE) {
    secureSessionStart();
}

if (!function_exists('topbarText')) {
    function topbarText($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$userName = $_SESSION['user_name']
    ?? $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'User';

$userRole = $_SESSION['role_name']
    ?? $_SESSION['role']
    ?? '';

$businessName = $_SESSION['business_name']
    ?? $_SESSION['selected_business_name']
    ?? '';

$branchName = $_SESSION['branch_name']
    ?? $_SESSION['selected_branch_name']
    ?? '';

$pageTitle = $page_title ?? 'Dashboard';

$profileInitial = strtoupper(substr(trim((string)$userName), 0, 1));
if ($profileInitial === '') {
    $profileInitial = 'U';
}
?>

<header id="page-topbar">
    <div class="navbar-header">

        <div class="d-flex align-items-center">

            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="<?= BASE_URL; ?>assets/images/logo-sm.png" alt="Logo" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="<?= BASE_URL; ?>assets/images/logo-dark.png" alt="Logo" height="20">
                    </span>
                </a>

                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="<?= BASE_URL; ?>assets/images/logo-sm.png" alt="Logo" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="<?= BASE_URL; ?>assets/images/logo-light.png" alt="Logo" height="20">
                    </span>
                </a>
            </div>

            <!-- Sidebar Toggle -->
            <button type="button"
                    class="btn btn-sm px-3 font-size-24 header-item waves-effect"
                    id="vertical-menu-btn"
                    aria-label="Toggle sidebar">
                <i class="mdi mdi-menu"></i>
            </button>

            <!-- Page Title -->
            <div class="d-none d-sm-block ms-2">
                <h4 class="page-title font-size-18 mb-0">
                    <?= topbarText($pageTitle); ?>
                </h4>
            </div>

        </div>

        <!-- Search Overlay -->
        <div class="search-wrap" id="search-wrap">
            <div class="search-bar">
                <input class="search-input form-control"
                       type="search"
                       placeholder="Search..."
                       aria-label="Search">

                <a href="javascript:void(0);"
                   class="close-search toggle-search"
                   data-bs-target="#search-wrap"
                   aria-label="Close search">
                    <i class="mdi mdi-close-circle"></i>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center">

            <!-- Search Button -->
            <div class="dropdown d-none d-lg-inline-block">
                <button type="button"
                        class="btn header-item toggle-search noti-icon waves-effect"
                        data-bs-target="#search-wrap"
                        aria-label="Search">
                    <i class="mdi mdi-magnify"></i>
                </button>
            </div>

            <!-- Full Screen -->
            <div class="dropdown d-none d-lg-inline-block">
                <button type="button"
                        class="btn header-item noti-icon waves-effect"
                        data-bs-toggle="fullscreen"
                        aria-label="Fullscreen">
                    <i class="mdi mdi-fullscreen"></i>
                </button>
            </div>

            <!-- Notifications -->
            <div class="dropdown d-inline-block ms-2">
                <button type="button"
                        class="btn header-item noti-icon waves-effect"
                        id="page-header-notifications-dropdown"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Notifications">
                    <i class="ion ion-md-notifications"></i>
                    <span class="badge bg-danger rounded-pill d-none" id="topbarNotificationCount">0</span>
                </button>

                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                     aria-labelledby="page-header-notifications-dropdown">

                    <div class="p-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="m-0 font-size-16">Notifications</h5>
                            </div>
                        </div>
                    </div>

                    <div data-simplebar style="max-height: 230px;">
                        <div class="p-3 text-center text-muted">
                            <i class="mdi mdi-bell-outline d-block font-size-24 mb-1"></i>
                            No new notifications
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Profile -->
            <div class="dropdown d-inline-block ms-2">
                <button type="button"
                        class="btn header-item waves-effect"
                        id="page-header-user-dropdown"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false">

                    <span class="avatar-xs d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white me-1">
                        <?= topbarText($profileInitial); ?>
                    </span>

                    <span class="d-none d-xl-inline-block ms-1">
                        <?= topbarText($userName); ?>
                    </span>

                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">

                    <div class="dropdown-item-text">
                        <div class="fw-semibold"><?= topbarText($userName); ?></div>

                        <?php if ($userRole !== ''): ?>
                            <small class="text-muted"><?= topbarText($userRole); ?></small>
                        <?php endif; ?>

                        <?php if ($businessName !== '' || $branchName !== ''): ?>
                            <div class="small text-muted mt-1">
                                <?php if ($businessName !== ''): ?>
                                    <?= topbarText($businessName); ?>
                                <?php endif; ?>

                                <?php if ($businessName !== '' && $branchName !== ''): ?>
                                    /
                                <?php endif; ?>

                                <?php if ($branchName !== ''): ?>
                                    <?= topbarText($branchName); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown-divider"></div>

                    <a class="dropdown-item" href="<?= BASE_URL; ?>pages/profile.php">
                        <i class="dripicons-user font-size-16 align-middle me-2"></i>
                        My Profile
                    </a>

                    <a class="dropdown-item" href="<?= BASE_URL; ?>pages/settings.php">
                        <i class="dripicons-gear font-size-16 align-middle me-2"></i>
                        Settings
                    </a>

                    <div class="dropdown-divider"></div>

                    <a class="dropdown-item text-danger" href="<?= BASE_URL; ?>logout.php">
                        <i class="dripicons-exit font-size-16 align-middle me-2"></i>
                        Logout
                    </a>

                </div>
            </div>

            <!-- Right Bar Toggle -->
            <div class="dropdown d-inline-block">
                <button type="button"
                        class="btn header-item noti-icon right-bar-toggle waves-effect"
                        aria-label="Settings panel">
                    <i class="mdi mdi-spin mdi-cog"></i>
                </button>
            </div>

        </div>

    </div>
</header>
