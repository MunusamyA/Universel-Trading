<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$csrfToken = generateCsrfToken();
$pageTitle = 'Branch Request';
?>
<?php include BASE_PATH . 'includes/head.php'; ?>

<body data-sidebar="dark">
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
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0">Branch Request</h4>
                            <div class="page-title-right">
                                <?php if (!isPlatformOwner()) { ?>
                                <button type="button" class="btn btn-primary" id="addBranchRequestBtn">
                                    <i class="mdi mdi-plus me-1"></i> Add Branch Request
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 col-md-6">
                        <div class="card mini-stats-wid">
                            <div class="card-body">
                                <div class="d-flex">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium">Total Requests</p>
                                        <h4 class="mb-0" id="totalRequestsCount">0</h4>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-primary align-self-center mini-stat-icon">
                                        <span class="avatar-title rounded-circle bg-primary">
                                            <i class="mdi mdi-source-branch font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card mini-stats-wid">
                            <div class="card-body">
                                <div class="d-flex">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium">Pending</p>
                                        <h4 class="mb-0" id="pendingRequestsCount">0</h4>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-warning align-self-center mini-stat-icon">
                                        <span class="avatar-title rounded-circle bg-warning">
                                            <i class="mdi mdi-clock-outline font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6">
                        <div class="card mini-stats-wid">
                            <div class="card-body">
                                <div class="d-flex">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium">Approved</p>
                                        <h4 class="mb-0" id="approvedRequestsCount">0</h4>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-success align-self-center mini-stat-icon">
                                        <span class="avatar-title rounded-circle bg-success">
                                            <i class="mdi mdi-check-circle-outline font-size-24"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Business</th>
                                            <th>Main Branch</th>
                                            <th>Requested Branch</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                            <th>Requested Date</th>
                                        </tr>
                                        </thead>
                                        <tbody id="branchRequestTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Loading...</td>
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

<div class="modal fade" id="branchRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="branchRequestForm">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= escapeHtml($csrfToken); ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white">Add Branch Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">Only main branch admin user can submit branch request. Platform owner will approve it from Branch Approvals.</div>

                    <div class="card border mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Requested Branch Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_name" id="branch_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Branch Code</label>
                                        <input type="text" class="form-control" name="branch_code" id="branch_code">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" id="city">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" class="form-control" name="state" id="state">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Pincode</label>
                                        <input type="text" class="form-control" name="pincode" id="pincode">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Mobile</label>
                                        <input type="text" class="form-control" name="mobile" id="mobile">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" id="email">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBranchRequestBtn">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.USER_TYPE = "<?= escapeHtml(currentUserType()); ?>";
</script>
<script src="<?= BASE_URL; ?>pages-js/branch_request.js"></script>
</body>
</html>
