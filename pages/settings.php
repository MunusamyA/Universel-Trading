<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Settings | Universal ERP';

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
                                <h4 class="mb-0">Settings</h4>
                                <p class="text-muted mb-0 mt-1">Manage business, GST, logo, branch, stock and sales settings.</p>
                            </div>
                            <button type="button" class="btn btn-primary" id="refreshSettingsBtn">
                                <i class="mdi mdi-refresh me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-primary" id="settingsTotalCount">0</h3>
                                Saved Settings
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-success" id="gstStatusText">-</h3>
                                GST Status
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-info" id="taxModeText">-</h3>
                                Tax Mode
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card text-center">
                            <div class="card-body text-muted">
                                <h3 class="text-warning" id="logoStatusText">-</h3>
                                Logo
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">

                        <ul class="nav nav-pills nav-justified mb-4" role="tablist">
                            <li class="nav-item waves-effect waves-light">
                                <a class="nav-link active" data-bs-toggle="tab" href="#businessTab" role="tab">
                                    <i class="mdi mdi-store me-1"></i> Business
                                </a>
                            </li>
                            <li class="nav-item waves-effect waves-light">
                                <a class="nav-link" data-bs-toggle="tab" href="#branchTab" role="tab">
                                    <i class="mdi mdi-source-branch me-1"></i> Branch
                                </a>
                            </li>
                            <li class="nav-item waves-effect waves-light">
                                <a class="nav-link" data-bs-toggle="tab" href="#generalTab" role="tab">
                                    <i class="mdi mdi-cog-outline me-1"></i> General
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">

                            <div class="tab-pane active" id="businessTab" role="tabpanel">
                                <form id="businessProfileForm" autocomplete="off" enctype="multipart/form-data">
                                    <?= csrfTokenInput(); ?>
                                    <input type="hidden" name="remove_logo" id="remove_logo" value="0">

                                    <div class="row">
                                        <div class="col-lg-9">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Business Code</label>
                                                    <input type="text" class="form-control" id="business_code" readonly>
                                                </div>

                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="business_name" id="business_name" required>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Owner Name</label>
                                                    <input type="text" class="form-control" name="owner_name" id="owner_name">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Mobile</label>
                                                    <input type="text" class="form-control" name="mobile" id="business_mobile" maxlength="20">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" id="business_email">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">GST Number</label>
                                                    <input type="text" class="form-control text-uppercase" name="gst_number" id="gst_number" maxlength="50" placeholder="33ABCDE1234F1Z5">
                                                    <small class="text-muted">Used in business profile and invoice print.</small>
                                                </div>

                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Address</label>
                                                    <textarea class="form-control" name="address" id="business_address" rows="2"></textarea>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">City</label>
                                                    <input type="text" class="form-control" name="city" id="business_city">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">State</label>
                                                    <input type="text" class="form-control" name="state" id="business_state">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Pincode</label>
                                                    <input type="text" class="form-control" name="pincode" id="business_pincode" maxlength="20">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-3">
                                            <div class="border rounded p-3 bg-light h-100">
                                                <label class="form-label">Business / Invoice Logo</label>

                                                <div class="text-center mb-3">
                                                    <div id="logoPreviewBox" class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto" style="width:150px;height:110px;overflow:hidden;">
                                                        <span class="text-muted small">No Logo</span>
                                                    </div>
                                                </div>

                                                <input type="file" class="form-control" name="business_logo" id="business_logo" accept="image/png,image/jpeg,image/jpg,image/webp">

                                                <small class="text-muted d-block mt-2">
                                                    Allowed: JPG, PNG, WEBP. Max 2MB.
                                                </small>

                                                <button type="button" class="btn btn-sm btn-outline-danger w-100 mt-3" id="removeLogoBtn">
                                                    <i class="mdi mdi-delete-outline me-1"></i> Remove Logo
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <button type="button" class="btn btn-success" id="saveBusinessProfileBtn">
                                            <i class="mdi mdi-content-save me-1"></i> Save Business
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane" id="branchTab" role="tabpanel">
                                <form id="branchProfileForm" autocomplete="off">
                                    <?= csrfTokenInput(); ?>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Branch Code</label>
                                            <input type="text" class="form-control" id="branch_code" readonly>
                                        </div>

                                        <div class="col-md-5 mb-3">
                                            <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="branch_name" id="branch_name" required>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Branch Status</label>
                                            <div id="branchStatusBadge" class="pt-2">-</div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Mobile</label>
                                            <input type="text" class="form-control" name="mobile" id="branch_mobile" maxlength="20">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" id="branch_email">
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Pincode</label>
                                            <input type="text" class="form-control" name="pincode" id="branch_pincode" maxlength="20">
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" id="branch_address" rows="2"></textarea>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" name="city" id="branch_city">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">State</label>
                                            <input type="text" class="form-control" name="state" id="branch_state">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-success" id="saveBranchProfileBtn">
                                            <i class="mdi mdi-content-save me-1"></i> Save Branch
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane" id="generalTab" role="tabpanel">
                                <form id="generalSettingsForm" autocomplete="off">
                                    <?= csrfTokenInput(); ?>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" name="currency" id="currency">
                                                <option value="INR">INR - Indian Rupee</option>
                                                <option value="USD">USD - Dollar</option>
                                                <option value="AED">AED - Dirham</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Timezone</label>
                                            <select class="form-select" name="timezone" id="timezone">
                                                <option value="Asia/Kolkata">Asia/Kolkata</option>
                                                <option value="Asia/Dubai">Asia/Dubai</option>
                                                <option value="UTC">UTC</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">GST Enabled</label>
                                            <select class="form-select" name="gst_enabled" id="gst_enabled">
                                                <option value="yes">Yes</option>
                                                <option value="no">No</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tax Mode</label>
                                            <select class="form-select" name="tax_mode" id="tax_mode">
                                                <option value="cgst_sgst">CGST + SGST</option>
                                                <option value="igst">IGST</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">FIFO Stock Deduction</label>
                                            <select class="form-select" name="fifo_stock_deduction" id="fifo_stock_deduction">
                                                <option value="yes">Yes</option>
                                                <option value="no">No</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Allow Negative Stock</label>
                                            <select class="form-select" name="allow_negative_stock" id="allow_negative_stock">
                                                <option value="no">No</option>
                                                <option value="yes">Yes</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Sales Flow</label>
                                            <select class="form-select" name="sales_flow" id="sales_flow">
                                                <option value="proforma_to_quotation_to_sale_order_to_invoice">Proforma → Quotation → Sale Order → Invoice</option>
                                                <option value="quotation_to_invoice">Quotation → Invoice</option>
                                                <option value="sale_order_to_invoice">Sale Order → Invoice</option>
                                                <option value="direct_invoice">Direct Invoice</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Round Off</label>
                                            <select class="form-select" name="round_off_enabled" id="round_off_enabled">
                                                <option value="yes">Yes</option>
                                                <option value="no">No</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Default Due Days</label>
                                            <input type="number" min="0" max="365" class="form-control" name="default_due_days" id="default_due_days">
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Invoice Terms</label>
                                            <textarea class="form-control" name="invoice_terms" id="invoice_terms" rows="3" placeholder="Example: Goods once sold cannot be taken back."></textarea>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-success" id="saveGeneralSettingsBtn">
                                            <i class="mdi mdi-content-save me-1"></i> Save Settings
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
<script src="<?= BASE_URL; ?>pages-js/settings.js?v=<?= time(); ?>"></script>
</body>
</html>
