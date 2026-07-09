<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Customer Ledger | Universal ERP';

$branchName = 'Branch';
if (function_exists('currentBranchName')) {
    $value = trim((string)currentBranchName());
    if ($value !== '') {
        $branchName = $value;
    }
} elseif (!empty($_SESSION['branch_name'])) {
    $branchName = trim((string)$_SESSION['branch_name']);
} elseif (!empty($_SESSION['current_branch_name'])) {
    $branchName = trim((string)$_SESSION['current_branch_name']);
}
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .ledger-hero-card {
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .ledger-stat-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }
        .ledger-stat-card .card-body {
            padding: 14px 16px;
        }
        .ledger-stat-card h4 {
            margin-bottom: 3px;
            font-size: 20px;
            font-weight: 700;
        }
        .ledger-toolbar .btn {
            margin-left: 4px;
            margin-bottom: 6px;
        }
        .ledger-filter-card {
            border-radius: 14px;
        }
        .ledger-filter-card .form-check-label {
            cursor: pointer;
        }
        .ledger-table th {
            background: #f8f9fa;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .ledger-table td {
            vertical-align: middle;
        }
        .ledger-table .particular-cell {
            white-space: normal !important;
            min-width: 220px;
        }
        .ledger-print-area {
            display: none;
        }
        @media print {
            body * {
                visibility: hidden !important;
            }
            #ledgerPrintArea,
            #ledgerPrintArea * {
                visibility: visible !important;
            }
            #ledgerPrintArea {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                color: #111827 !important;
                background: #fff !important;
                font-family: Arial, sans-serif;
            }
            .print-statement {
                padding: 0;
                font-size: 11px;
            }
            .print-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 2px solid #111827;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }
            .print-title {
                font-size: 18px;
                font-weight: 800;
                margin: 0;
            }
            .print-branch {
                font-size: 15px;
                font-weight: 800;
                margin-bottom: 2px;
            }
            .print-meta {
                font-size: 10px;
                color: #374151 !important;
            }
            .print-grid {
                display: grid;
                grid-template-columns: 1.1fr .9fr;
                gap: 10px;
                margin-bottom: 10px;
            }
            .print-box {
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 8px;
            }
            .print-box-title {
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                margin-bottom: 6px;
                color: #111827 !important;
            }
            .print-summary-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 6px;
                margin-bottom: 10px;
            }
            .print-summary-item {
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 7px;
            }
            .print-summary-item div:first-child {
                color: #6b7280 !important;
                font-size: 9px;
                text-transform: uppercase;
            }
            .print-summary-item div:last-child {
                font-size: 12px;
                font-weight: 800;
                margin-top: 2px;
            }
            .print-ledger-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 10px;
            }
            .print-ledger-table th,
            .print-ledger-table td {
                border: 1px solid #d1d5db;
                padding: 5px;
                vertical-align: top;
                word-break: break-word;
            }
            .print-ledger-table th {
                background: #f3f4f6 !important;
                font-weight: 800;
                color: #111827 !important;
            }
            .print-footer {
                border-top: 1px solid #d1d5db;
                margin-top: 10px;
                padding-top: 6px;
                display: flex;
                justify-content: space-between;
                font-size: 9px;
                color: #6b7280 !important;
            }
            body.ledger-print-a3 .print-statement {
                font-size: 12px;
            }
            body.ledger-print-a3 .print-ledger-table {
                font-size: 11px;
            }
            body.ledger-print-a3 .print-title {
                font-size: 21px;
            }
        }
    </style>
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

                <div class="card ledger-hero-card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4 class="mb-1" id="pageTitleText">Customer Ledger</h4>
                                <p class="text-muted mb-0" id="pageNoteText">Customer debit / credit / balance statement</p>
                            </div>

                            <div class="ledger-toolbar text-end">
                                <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light d-none" id="customersBtn">
                                    <i class="mdi mdi-account-group me-1"></i> Customers
                                </a>
                                <div class="btn-group d-none" id="printLedgerDropdown">
                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="mdi mdi-printer me-1"></i> Print
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button class="dropdown-item print-ledger-option" type="button" data-size="a4" data-orientation="portrait">A4 Portrait</button>
                                        <button class="dropdown-item print-ledger-option" type="button" data-size="a4" data-orientation="landscape">A4 Landscape</button>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item print-ledger-option" type="button" data-size="a3" data-orientation="portrait">A3 Portrait</button>
                                        <button class="dropdown-item print-ledger-option" type="button" data-size="a3" data-orientation="landscape">A3 Landscape</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-success d-none" id="exportExcelBtn">
                                    <i class="mdi mdi-file-excel me-1"></i> Excel
                                </button>
                                <button type="button" class="btn btn-outline-secondary d-none" id="exportCsvBtn">
                                    <i class="mdi mdi-file-delimited me-1"></i> CSV
                                </button>
                                <div class="btn-group d-none" id="exportPdfDropdown">
                                    <button class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="mdi mdi-file-pdf-box me-1"></i> PDF Download
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button class="dropdown-item pdf-ledger-option" type="button" data-orientation="portrait">PDF Portrait</button>
                                        <button class="dropdown-item pdf-ledger-option" type="button" data-orientation="landscape">PDF Landscape</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card ledger-stat-card">
                            <div class="card-body text-muted">
                                <h4 class="text-primary" id="ledgerEntriesCount">0</h4>
                                Total Entries
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card ledger-stat-card">
                            <div class="card-body text-muted">
                                <h4 class="text-danger" id="ledgerDebitTotal">₹0.00</h4>
                                Total Debit / Sales
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card ledger-stat-card">
                            <div class="card-body text-muted">
                                <h4 class="text-success" id="ledgerCreditTotal">₹0.00</h4>
                                Total Credit / Received
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card ledger-stat-card">
                            <div class="card-body text-muted">
                                <h4 class="text-warning" id="ledgerClosingBalance">₹0.00</h4>
                                Closing Balance
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card ledger-filter-card" id="ledgerFilterCard">
                    <div class="card-body">
                        <div class="row align-items-end g-3">
                            <div class="col-md-4">
                                <label class="form-label">Customer</label>
                                <select class="form-select" id="customerId">
                                    <option value="">Select Customer</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="fromDate">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="toDate">
                            </div>

                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-primary" id="filterLedgerBtn">
                                    <i class="mdi mdi-filter me-1"></i> Filter
                                </button>

                                <button type="button" class="btn btn-light" id="resetLedgerBtn">
                                    <i class="mdi mdi-refresh me-1"></i> Reset
                                </button>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-12">
                                <label class="form-label mb-2">Document Type</label>

                                <div class="d-flex flex-wrap gap-3">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="1" checked> Quotation
                                    </label>

                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="2" checked> Proforma Bill
                                    </label>

                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="3" checked> Sales Bill
                                    </label>

                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="4" checked> Direct Bill
                                    </label>

                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="5" checked> Final Invoice
                                    </label>

                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input ledger-doc-type" value="99" checked> Payments
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                            <div>
                                <h5 class="mb-0" id="ledgerCustomerName">Select customer</h5>
                                <small class="text-muted" id="ledgerCustomerInfo">Ledger statement</small>
                            </div>
                            <span class="badge bg-primary-subtle text-primary" id="ledgerPeriodBadge">All Period</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered mb-0 ledger-table">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th width="110">Date</th>
                                        <th>Particular</th>
                                        <th width="150">Reference</th>
                                        <th width="120">Type</th>
                                        <th width="130" class="text-end">Debit</th>
                                        <th width="130" class="text-end">Credit</th>
                                        <th width="140" class="text-end">Balance</th>
                                    </tr>
                                </thead>

                                <tbody id="ledgerTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Select customer and filter ledger.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="ledgerPrintArea" class="ledger-print-area"></div>

            </div>
        </div>

        <?php include BASE_PATH . 'includes/footer.php'; ?>

    </div>
</div>

<?php
$rightbarPath1 = BASE_PATH . 'includes/rightbar.php';
if (file_exists($rightbarPath1)) {
    include $rightbarPath1;
}

$scriptsPath1 = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "customer-ledger";
    window.BRANCH_NAME = <?= json_encode($branchName); ?>;
</script>

<script src="<?= BASE_URL; ?>pages-js/customer-ledger.js?v=<?= time(); ?>"></script>

</body>
</html>
