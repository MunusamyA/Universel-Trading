<?php
require_once __DIR__ . '/config.php';

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['role_name'] ?? '';
$pageTitle = $page_title ?? 'Dashboard';
?>

<header id="page-topbar">
    <div class="navbar-header">

        <div class="d-flex">

            <!-- LOGO -->
            <div class="navbar-brand-box">

                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="<?= BASE_URL; ?>assets/images/logo-sm.png"
                             alt="Logo"
                             height="22">
                    </span>

                    <span class="logo-lg">
                        <img src="<?= BASE_URL; ?>assets/images/logo-dark.png"
                             alt="Logo"
                             height="20">
                    </span>
                </a>

                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="<?= BASE_URL; ?>assets/images/logo-sm.png"
                             alt="Logo"
                             height="22">
                    </span>

                    <span class="logo-lg">
                        <img src="<?= BASE_URL; ?>assets/images/logo-light.png"
                             alt="Logo"
                             height="20">
                    </span>
                </a>

            </div>

            <!-- Sidebar Toggle -->
            <button type="button"
                    class="btn btn-sm px-3 font-size-24 header-item waves-effect"
                    id="vertical-menu-btn">
                <i class="mdi mdi-menu"></i>
            </button>

            <!-- Page Title -->
            <div class="d-none d-sm-block ms-2">
                <h4 class="page-title font-size-18">
                    <?= htmlspecialchars($pageTitle); ?>
                </h4>
            </div>

        </div>

        <!-- Search -->
        <div class="search-wrap" id="search-wrap">
            <div class="search-bar">
                <input class="search-input form-control"
                       placeholder="Search">

                <a href="javascript:void(0);"
                   class="close-search toggle-search"
                   data-bs-target="#search-wrap">
                    <i class="mdi mdi-close-circle"></i>
                </a>
            </div>
        </div>

        <div class="d-flex">

            <!-- Search Button -->
            <div class="dropdown d-none d-lg-inline-block">
                <button type="button"
                        class="btn header-item toggle-search noti-icon waves-effect"
                        data-bs-target="#search-wrap">
                    <i class="mdi mdi-magnify"></i>
                </button>
            </div>

            <!-- Language -->
            <div class="dropdown d-none d-md-block ms-2">
                <button type="button"
                        class="btn header-item waves-effect"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false">
                    <img class="me-2"
                         src="<?= BASE_URL; ?>assets/images/flags/us_flag.jpg"
                         alt="Header Language"
                         height="16">
                    English
                    <span class="mdi mdi-chevron-down"></span>
                </button>

                <div class="dropdown-menu dropdown-menu-end">

                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="<?= BASE_URL; ?>assets/images/flags/germany_flag.jpg"
                             alt="German"
                             class="me-1"
                             height="12">
                        <span class="align-middle">German</span>
                    </a>

                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="<?= BASE_URL; ?>assets/images/flags/italy_flag.jpg"
                             alt="Italian"
                             class="me-1"
                             height="12">
                        <span class="align-middle">Italian</span>
                    </a>

                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="<?= BASE_URL; ?>assets/images/flags/french_flag.jpg"
                             alt="French"
                             class="me-1"
                             height="12">
                        <span class="align-middle">French</span>
                    </a>

                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="<?= BASE_URL; ?>assets/images/flags/spain_flag.jpg"
                             alt="Spanish"
                             class="me-1"
                             height="12">
                        <span class="align-middle">Spanish</span>
                    </a>

                    <a href="javascript:void(0);" class="dropdown-item notify-item">
                        <img src="<?= BASE_URL; ?>assets/images/flags/russia_flag.jpg"
                             alt="Russian"
                             class="me-1"
                             height="12">
                        <span class="align-middle">Russian</span>
                    </a>

                </div>
            </div>

            <!-- Full Screen -->
            <div class="dropdown d-none d-lg-inline-block">
                <button type="button"
                        class="btn header-item noti-icon waves-effect"
                        data-bs-toggle="fullscreen">
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
                        aria-expanded="false">
                    <i class="ion ion-md-notifications"></i>
                    <span class="badge bg-danger rounded-pill">3</span>
                </button>

                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                     aria-labelledby="page-header-notifications-dropdown">

                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="m-0 font-size-16">Notifications</h5>
                            </div>
                        </div>
                    </div>

                    <div data-simplebar style="max-height: 230px;">

                        <a href="javascript:void(0);" class="text-reset notification-item">
                            <div class="media d-flex">
                                <div class="avatar-xs me-3">
                                    <span class="avatar-title bg-success rounded-circle font-size-16">
                                        <i class="mdi mdi-check-circle-outline"></i>
                                    </span>
                                </div>

                                <div class="flex-1">
                                    <h6 class="mt-0 font-size-15 mb-1">
                                        Login Successful
                                    </h6>
                                    <div class="font-size-12 text-muted">
                                        <p class="mb-1">
                                            Welcome to Universal ERP.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <a href="javascript:void(0);" class="text-reset notification-item">
                            <div class="media d-flex">
                                <div class="avatar-xs me-3">
                                    <span class="avatar-title bg-warning rounded-circle font-size-16">
                                        <i class="mdi mdi-shield-account-outline"></i>
                                    </span>
                                </div>

                                <div class="flex-1">
                                    <h6 class="mt-0 font-size-15 mb-1">
                                        Security Enabled
                                    </h6>
                                    <div class="font-size-12 text-muted">
                                        <p class="mb-1">
                                            Session security is active.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </a>

                    </div>

                    <div class="p-2 border-top text-center">
                        <a class="btn btn-sm btn-link font-size-14 w-100"
                           href="javascript:void(0);">
                            View all
                        </a>
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

                    <img class="rounded-circle header-profile-user"
                         src="<?= BASE_URL; ?>assets/images/users/avatar-1.jpg"
                         alt="Header Avatar">

                    <span class="d-none d-xl-inline-block ms-1">
                        <?= htmlspecialchars($userName); ?>
                    </span>

                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">

                    <a class="dropdown-item" href="javascript:void(0);">
                        <i class="dripicons-user font-size-16 align-middle me-2"></i>
                        Profile
                    </a>

                    <a class="dropdown-item" href="javascript:void(0);">
                        <i class="dripicons-gear font-size-16 align-middle me-2"></i>
                        <?= htmlspecialchars($userRole); ?>
                    </a>

                    <a class="dropdown-item" href="javascript:void(0);">
                        <i class="dripicons-lock font-size-16 align-middle me-2"></i>
                        Lock Screen
                    </a>

                    <div class="dropdown-divider"></div>

                    <a class="dropdown-item text-danger"
                       href="<?= BASE_URL; ?>logout.php">
                        <i class="dripicons-exit font-size-16 align-middle me-2"></i>
                        Logout
                    </a>

                </div>
            </div>

            <!-- Right Bar Toggle -->
            <div class="dropdown d-inline-block">
                <button type="button"
                        class="btn header-item noti-icon right-bar-toggle waves-effect">
                    <i class="mdi mdi-spin mdi-cog"></i>
                </button>
            </div>

        </div>

    </div>
</header>