<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

if (!isPlatformOwner() && !hasPermission('branch_approvals', 'view')) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$page_title = 'Branch Approvals | Universal ERP';
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
                            <h4 class="mb-0">Branch Approvals</h4>

                            <div class="page-title-right">
                                <button type="button" class="btn btn-light btn-sm" id="refreshBranchApprovalBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-primary mt-2" id="totalApprovalCount">0</h3>
                                Total Requests
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-warning mt-2" id="pendingApprovalCount">0</h3>
                                Pending
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-success mt-2" id="approvedApprovalCount">0</h3>
                                Approved
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="mb-2 card-body text-muted">
                                <h3 class="text-danger mt-2" id="rejectedApprovalCount">0</h3>
                                Rejected
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
                                        <h4 class="card-title mb-1">Branch Approval Requests</h4>
                                        <p class="card-title-desc mb-0">Approve branch request and create branch login with selected package role.</p>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Business</th>
                                                <th>Main Branch</th>
                                                <th>Requested Branch</th>
                                                <th>Code</th>
                                                <th>Requested By</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                                <th width="180">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="branchApprovalTableBody">
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">Loading...</td>
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

<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <form id="approveBranchForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="branch_id" id="approve_branch_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalTitle">Approve Branch Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: calc(100vh - 190px); overflow-y: auto;">

                    <h5 class="font-size-15 mb-3">Requested Branch Details</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Business</label>
                                <input type="text" class="form-control" id="view_business_name" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Main Branch</label>
                                <input type="text" class="form-control" id="view_main_branch_name" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Requested Branch</label>
                                <input type="text" class="form-control" id="view_branch_name" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Branch Code</label>
                                <input type="text" class="form-control" id="view_branch_code" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mobile</label>
                                <input type="text" class="form-control" id="view_mobile" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" id="view_email" readonly>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" id="view_address" rows="2" readonly></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" id="view_city" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" id="view_state" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="view_pincode" readonly>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h5 class="font-size-15 mb-3">Create Branch Login & Package</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="approve_name">Login Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="approve_name"
                                       name="name"
                                       placeholder="Enter login display name">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="approve_username">Username <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="approve_username"
                                       name="username"
                                       placeholder="Enter login username">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="approve_password">Password <span class="text-danger">*</span></label>
                                <input type="password"
                                       class="form-control"
                                       id="approve_password"
                                       name="password"
                                       placeholder="Minimum 6 characters">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="approve_package_role_id">Select Package Role <span class="text-danger">*</span></label>
                                <select class="form-control" id="approve_package_role_id" name="package_role_id">
                                    <option value="">Select Package Role</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="approve_remarks">Approval Remarks</label>
                                <textarea class="form-control"
                                          id="approve_remarks"
                                          name="remarks"
                                          rows="2"
                                          placeholder="Enter approval remarks"></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success waves-effect waves-light" id="approveBranchBtn">
                        Approve & Create Login
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <form id="rejectBranchForm" autocomplete="off">
                <?= csrfTokenInput(); ?>
                <input type="hidden" name="branch_id" id="reject_branch_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalTitle">Reject Branch Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <h5 class="font-size-15 mb-3">Reject Details</h5>

                    <div class="mb-3">
                        <label class="form-label" for="reject_remarks">Remarks</label>
                        <textarea class="form-control"
                                  id="reject_remarks"
                                  name="remarks"
                                  rows="3"
                                  placeholder="Enter reject reason"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger waves-effect waves-light" id="rejectBranchBtn">Reject</button>
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
