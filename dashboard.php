<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// requireRootLogin();

$page_title = "Dashboard | Universal ERP";
$base_url = "";
?>
<!doctype html>
<html lang="en">

<head>
    <?php include('includes/head.php') ?>
</head>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php') ?>

<!-- Begin page -->
<div id="layout-wrapper">

    <?php include('includes/topbar.php') ?>

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">
        <div data-simplebar class="h-100">

            <!-- Sidebar -->
            <?php include('includes/sidebar.php') ?>

        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">


                <!-- Welcome Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-2">
                                    Welcome, <?= htmlspecialchars(currentUserName()); ?>
                                </h4>

                                <p class="text-muted mb-0">
                                    Login successful. Your session and database connection are working properly.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h5 class="text-info mt-2">
                                    <?= htmlspecialchars(currentBusinessName()); ?>
                                </h5>
                                Business
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h5 class="text-purple mt-2">
                                    <?= htmlspecialchars(currentBranchName()); ?>
                                </h5>
                                Branch
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h5 class="text-primary mt-2">
                                    <?= htmlspecialchars(currentRoleName()); ?>
                                </h5>
                                Role
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h5 class="text-danger mt-2">
                                    <?= htmlspecialchars(currentUserType()); ?>
                                </h5>
                                User Type
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end row -->

                <!-- ERP Module Cards -->
                <div class="row">

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Purchase Flow</h4>

                                <p class="text-muted mb-3">
                                    Supplier, purchase bill, batch stock and FIFO inventory.
                                </p>

                                <a href="pages/purchases.php" class="btn btn-primary btn-sm">
                                    Open Purchases
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Sales Flow</h4>

                                <p class="text-muted mb-3">
                                    Proforma, quotation, sale order and invoice.
                                </p>

                                <a href="pages/proforma_bills.php" class="btn btn-primary btn-sm">
                                    Open Sales
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Reports</h4>

                                <p class="text-muted mb-3">
                                    Day-wise report, monthly report, customer and supplier ledger.
                                </p>

                                <a href="pages/reports.php" class="btn btn-primary btn-sm">
                                    Open Reports
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end row -->

                <!-- Latest Activity -->
                <div class="row">

                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">System Overview</h4>

                                <div class="table-responsive">
                                    <table class="table mt-4 mb-0 table-centered table-nowrap">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-check-circle text-success me-2"></i>
                                                    Login Authentication
                                                </td>
                                                <td>Working</td>
                                            </tr>

                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-database text-primary me-2"></i>
                                                    Database Connection
                                                </td>
                                                <td>Connected</td>
                                            </tr>

                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-shield-account text-info me-2"></i>
                                                    Session Security
                                                </td>
                                                <td>Enabled</td>
                                            </tr>

                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-office-building text-warning me-2"></i>
                                                    Business Access
                                                </td>
                                                <td><?= htmlspecialchars(currentBusinessName()); ?></td>
                                            </tr>

                                            <tr>
                                                <td>
                                                    <i class="mdi mdi-source-branch text-danger me-2"></i>
                                                    Branch Access
                                                </td>
                                                <td><?= htmlspecialchars(currentBranchName()); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-3">Recent Activity Feed</h4>

                                <ol class="activity-feed mb-0">
                                    <li class="feed-item">
                                        <span class="date"><?= date('d M'); ?></span>
                                        <span class="activity-text">
                                            <?= htmlspecialchars(currentUserName()); ?> logged in successfully.
                                        </span>
                                    </li>

                                    <li class="feed-item">
                                        <span class="date"><?= date('d M'); ?></span>
                                        <span class="activity-text">
                                            Session created for <?= htmlspecialchars(currentUserType()); ?>.
                                        </span>
                                    </li>

                                    <li class="feed-item pb-0">
                                        <span class="activity-text">
                                            <a href="pages/reports.php" class="text-primary">
                                                View Reports
                                            </a>
                                        </span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end row -->

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php') ?>

    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php') ?>
<!-- /Right-bar -->

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php') ?>

</body>
</html>