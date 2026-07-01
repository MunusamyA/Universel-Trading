<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Overall Sales Documents | Universal ERP';
$listTitle = 'Overall Sales Documents';
$listDescription = 'All sales documents in one place';
$currentDocumentType = 0;
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .sales-list-page {
            padding: 12px;
        }
        .metric-card {
            border: 1px solid #edf0f5;
            border-radius: 12px;
            background: #fff;
            padding: 14px;
            box-shadow: 0 2px 10px rgba(16,24,40,.04);
        }
        .metric-card span {
            color: #6c757d;
            font-size: 12px;
        }
        .metric-card h4 {
            margin: 4px 0 0;
            font-weight: 700;
        }
        .filter-card {
            border-radius: 12px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
    </style>
</head>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php if (file_exists(BASE_PATH . 'includes/topbar.php')) include BASE_PATH . 'includes/topbar.php'; ?>
    <?php if (file_exists(BASE_PATH . 'includes/sidebar.php')) include BASE_PATH . 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-content sales-list-page">
            <div class="container-fluid">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="mb-1"><?= htmlspecialchars($listTitle); ?></h4>
                        <p class="text-muted mb-0"><?= htmlspecialchars($listDescription); ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL; ?>pages/sales.php" class="btn btn-primary">
                            <i class="mdi mdi-plus me-1"></i> New Sales Entry
                        </a>
                        <a href="<?= BASE_URL; ?>pages/all-sales-list.php" class="btn btn-light">
                            <i class="mdi mdi-format-list-bulleted me-1"></i> Overall List
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <span>Total Documents</span>
                            <h4 id="countCard">0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <span>Total Amount</span>
                            <h4 id="totalCard">₹0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <span>Paid Amount</span>
                            <h4 id="paidCard">₹0.00</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <span>Due Amount</span>
                            <h4 id="dueCard">₹0.00</h4>
                        </div>
                    </div>
                </div>

                <div class="card filter-card mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" id="searchText" class="form-control" placeholder="No / Customer / Mobile">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select id="statusFilter" class="form-select">
                                    <option value="">All</option>
                                    <option value="1">Active</option>
                                    <option value="2">Final</option>
                                    <option value="3">Deleted</option>
                                    <option value="4">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" id="fromDate" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" id="toDate" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="filterBtn" class="btn btn-primary">
                                    <i class="mdi mdi-filter me-1"></i> Filter
                                </button>
                                <button type="button" id="resetFilterBtn" class="btn btn-light">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Document No</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th class="text-end">Sub Total</th>
                                        <th class="text-end">GST</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="salesListBody">
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Normal users can see only their own created documents. Admin / Manager can see all documents.
                        </small>
                    </div>
                </div>

            </div>
        </div>
        <?php if (file_exists(BASE_PATH . 'includes/footer.php')) include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.SALES_LIST_CONFIG = {
        document_type: <?= (int)$currentDocumentType; ?>,
        document_types: {},
        permissions: {}
    };
</script>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script src="<?= BASE_URL; ?>pages-js/sales-list.js"></script>
</body>
</html>
