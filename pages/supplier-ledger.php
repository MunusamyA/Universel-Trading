<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$supplierId = (int)($_GET['supplier_id'] ?? 0);
$page_title = 'Supplier Ledger | Universal ERP';

$printBranchName = '';
if (!empty($_SESSION['branch_name'])) {
    $printBranchName = (string)$_SESSION['branch_name'];
} elseif (!empty($_SESSION['current_branch_name'])) {
    $printBranchName = (string)$_SESSION['current_branch_name'];
} elseif (!empty($_SESSION['selected_branch_name'])) {
    $printBranchName = (string)$_SESSION['selected_branch_name'];
} elseif (function_exists('currentBranchName')) {
    $printBranchName = (string)currentBranchName();
}
$printBranchName = trim($printBranchName) !== '' ? trim($printBranchName) : 'Branch';
?>
<!doctype html>
<html lang="en">

<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style id="ledgerPrintPageSize">@page { size: A4 landscape; margin: 8mm; }</style>
    <style>
        .ledger-stat-card .card-body {
            padding: 16px 14px;
        }
        .ledger-stat-card h3 {
            margin-bottom: 2px;
            font-size: 21px;
            font-weight: 700;
        }
        .ledger-filter-card {
            border: 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }
        .ledger-toolbar .btn {
            min-width: 92px;
        }
        .ledger-table {
            table-layout: fixed;
            width: 100%;
        }
        .ledger-table th,
        .ledger-table td {
            white-space: normal !important;
            vertical-align: middle;
            word-break: break-word;
        }
        .ledger-table .particular-col { width: 25%; }
        .ledger-table .reference-col { width: 16%; }
        .ledger-print-report { display: none; }

        @media print {
            html, body {
                background: #fff !important;
                color: #111827 !important;
                font-size: 11px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body * { visibility: hidden !important; }
            #supplierLedgerPrintReport, #supplierLedgerPrintReport * { visibility: visible !important; }
            #supplierLedgerPrintReport {
                display: block !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #fff !important;
            }
            .vertical-menu,
            .navbar-header,
            .page-title-box,
            .footer,
            .right-bar,
            .rightbar-overlay,
            #preloader,
            .ledger-screen-wrapper,
            .no-print {
                display: none !important;
            }
            .main-content,
            .page-content,
            .container-fluid {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            body.ledger-print-a3 { font-size: 12px !important; }
            body.ledger-print-a3 .print-company { font-size: 21px !important; }
            body.ledger-print-a3 .print-title { font-size: 15px !important; }
            body.ledger-print-a3 .print-meta { font-size: 11px !important; }
            body.ledger-print-a3 .print-section-title { font-size: 13px !important; }
            body.ledger-print-a3 .print-kpi { min-height: 56px !important; padding: 9px 10px !important; }
            body.ledger-print-a3 .print-kpi span { font-size: 10px !important; }
            body.ledger-print-a3 .print-kpi strong { font-size: 16px !important; }
            body.ledger-print-a3 .print-table { font-size: 11.5px !important; }
            body.ledger-print-a3 .print-table th { font-size: 10px !important; }
            body.ledger-print-a3 .print-table th,
            body.ledger-print-a3 .print-table td { padding: 7px 7px !important; }
            body.ledger-print-a3 .print-footer { font-size: 10px !important; margin-top: 14px !important; }
            body.ledger-print-a4 .print-table { font-size: 10px !important; }

            .print-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 2px solid #111827;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }
            .print-company {
                font-size: 17px;
                font-weight: 800;
                letter-spacing: .04em;
                text-transform: uppercase;
                color: #111827 !important;
            }
            .print-title {
                font-size: 13px;
                font-weight: 700;
                color: #475569 !important;
                margin-top: 2px;
            }
            .print-meta {
                text-align: right;
                font-size: 10px;
                color: #475569 !important;
                line-height: 1.6;
            }
            .print-section-title {
                font-size: 12px;
                font-weight: 800;
                margin: 8px 0 6px;
                color: #111827 !important;
                text-transform: uppercase;
                letter-spacing: .03em;
            }
            .print-info-grid {
                display: grid;
                grid-template-columns: 1.1fr .9fr;
                gap: 8px;
                margin-bottom: 8px;
            }
            .print-box {
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                padding: 7px 8px;
                background: #fff !important;
                page-break-inside: avoid;
            }
            .print-info-row {
                display: grid;
                grid-template-columns: 110px 1fr;
                gap: 6px;
                line-height: 1.55;
                border-bottom: 1px dashed #e2e8f0;
                padding: 2px 0;
            }
            .print-info-row:last-child { border-bottom: 0; }
            .print-label { font-weight: 800; color: #334155 !important; }
            .print-value { color: #111827 !important; word-break: break-word; }
            .print-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 6px;
                margin-bottom: 8px;
            }
            .print-kpi {
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                padding: 7px 8px;
                background: #f8fafc !important;
                min-height: 46px;
            }
            .print-kpi span {
                display: block;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b !important;
                font-weight: 700;
                margin-bottom: 4px;
            }
            .print-kpi strong {
                display: block;
                font-size: 13px;
                color: #111827 !important;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 10px;
            }
            .print-table th,
            .print-table td {
                border: 1px solid #cbd5e1;
                padding: 5px 5px;
                vertical-align: top;
                white-space: normal !important;
                word-break: break-word;
                color: #111827 !important;
            }
            .print-table th {
                background: #e2e8f0 !important;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: .03em;
                font-weight: 800;
            }
            .print-table tbody tr:nth-child(even) td { background: #f8fafc !important; }
            .print-text-end { text-align: right !important; }
            .print-text-center { text-align: center !important; }
            .print-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 10px;
                padding-top: 6px;
                border-top: 1px solid #cbd5e1;
                font-size: 9px;
                color: #64748b !important;
            }
            .print-ledger-table th:nth-child(1), .print-ledger-table td:nth-child(1) { width: 4%; }
            .print-ledger-table th:nth-child(2), .print-ledger-table td:nth-child(2) { width: 10%; }
            .print-ledger-table th:nth-child(3), .print-ledger-table td:nth-child(3) { width: 25%; }
            .print-ledger-table th:nth-child(4), .print-ledger-table td:nth-child(4) { width: 15%; }
            .print-ledger-table th:nth-child(5), .print-ledger-table td:nth-child(5) { width: 12%; }
            .print-ledger-table th:nth-child(6), .print-ledger-table td:nth-child(6) { width: 11%; }
            .print-ledger-table th:nth-child(7), .print-ledger-table td:nth-child(7) { width: 11%; }
            .print-ledger-table th:nth-child(8), .print-ledger-table td:nth-child(8) { width: 12%; }
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

                <div class="ledger-screen-wrapper">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="mb-0" id="pageTitleText">Supplier Ledger</h4>
                                    <small class="text-muted" id="pageNoteText">Supplier debit / credit / balance statement</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center ledger-stat-card">
                                <div class="card-body text-muted">
                                    <h3 class="text-primary" id="totalEntries">0</h3>
                                    Total Entries
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center ledger-stat-card">
                                <div class="card-body text-muted">
                                    <h3 class="text-success" id="totalDebit">0.00</h3>
                                    Total Debit / Paid
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center ledger-stat-card">
                                <div class="card-body text-muted">
                                    <h3 class="text-danger" id="totalCredit">0.00</h3>
                                    Total Credit / Purchase
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center ledger-stat-card">
                                <div class="card-body text-muted">
                                    <h3 class="text-info" id="closingBalance">0.00</h3>
                                    Closing Balance
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card ledger-filter-card" id="ledgerFilterCard">
                        <div class="card-body">

                            <div class="row align-items-end mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-select" id="supplier_id">
                                        <option value="">Select Supplier</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" class="form-control" id="fromDate">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" class="form-control" id="toDate">
                                </div>

                                <div class="col-md-4 text-end ledger-toolbar">
                                    <button class="btn btn-light" id="refreshLedgerBtn" type="button">
                                        <i class="mdi mdi-refresh me-1"></i> Refresh
                                    </button>

                                    <div class="btn-group ms-1 d-none" id="printLedgerDropdown">
                                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <button class="dropdown-item print-ledger-option" type="button" data-size="a4" data-orientation="portrait">
                                                A4 Portrait
                                            </button>
                                            <button class="dropdown-item print-ledger-option" type="button" data-size="a4" data-orientation="landscape">
                                                A4 Landscape
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item print-ledger-option" type="button" data-size="a3" data-orientation="portrait">
                                                A3 Portrait
                                            </button>
                                            <button class="dropdown-item print-ledger-option" type="button" data-size="a3" data-orientation="landscape">
                                                A3 Landscape
                                            </button>
                                        </div>
                                    </div>

                                    <div class="btn-group ms-1" role="group" aria-label="Export format">
                                        <button class="btn btn-outline-success d-none" id="exportExcelBtn" type="button">
                                            <i class="mdi mdi-file-excel me-1"></i> Excel
                                        </button>

                                        <div class="btn-group d-none" id="exportPdfDropdown">
                                            <button class="btn btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="mdi mdi-file-pdf-box me-1"></i> PDF Download
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button class="dropdown-item pdf-ledger-option" type="button" data-orientation="portrait">
                                                    PDF Portrait
                                                </button>
                                                <button class="dropdown-item pdf-ledger-option" type="button" data-orientation="landscape">
                                                    PDF Landscape
                                                </button>
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-secondary d-none" id="exportCsvBtn" type="button">
                                            <i class="mdi mdi-file-delimited me-1"></i> CSV
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered mb-0 ledger-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">#</th>
                                            <th width="110">Date</th>
                                            <th class="particular-col">Particular</th>
                                            <th class="reference-col">Reference</th>
                                            <th width="120">Type</th>
                                            <th class="text-end" width="130">Debit</th>
                                            <th class="text-end" width="130">Credit</th>
                                            <th class="text-end" width="140">Balance</th>
                                        </tr>
                                    </thead>

                                    <tbody id="ledgerTableBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Select supplier.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

                <div id="supplierLedgerPrintReport" class="ledger-print-report">
                    <div class="print-header">
                        <div>
                            <div class="print-company" id="printBranchName"><?= htmlspecialchars($printBranchName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="print-title">Supplier Ledger Statement</div>
                        </div>
                        <div class="print-meta">
                            <div><strong>Format:</strong> <span id="printLedgerFormatText">A4 Landscape</span></div>
                            <div><strong>Printed:</strong> <span id="printGeneratedAt">-</span></div>
                            <div><strong>Printed by:</strong> <?= htmlspecialchars($printBranchName, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <div class="print-info-grid">
                        <div class="print-box">
                            <div class="print-section-title">Supplier Details</div>
                            <div class="print-info-row"><div class="print-label">Supplier</div><div class="print-value" id="printSupplierName">-</div></div>
                            <div class="print-info-row"><div class="print-label">Period</div><div class="print-value" id="printPeriodText">All Dates</div></div>
                            <div class="print-info-row"><div class="print-label">Total Entries</div><div class="print-value" id="printTotalEntries">0</div></div>
                        </div>
                        <div class="print-box">
                            <div class="print-section-title">Statement Summary</div>
                            <div class="print-info-row"><div class="print-label">Total Paid</div><div class="print-value" id="printTotalDebit">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Total Purchase</div><div class="print-value" id="printTotalCredit">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Closing Balance</div><div class="print-value" id="printClosingBalance">₹0.00</div></div>
                        </div>
                    </div>

                    <div class="print-kpi-grid">
                        <div class="print-kpi"><span>Total Entries</span><strong id="printKpiEntries">0</strong></div>
                        <div class="print-kpi"><span>Total Debit / Paid</span><strong id="printKpiDebit">₹0.00</strong></div>
                        <div class="print-kpi"><span>Total Credit / Purchase</span><strong id="printKpiCredit">₹0.00</strong></div>
                        <div class="print-kpi"><span>Closing Balance</span><strong id="printKpiClosing">₹0.00</strong></div>
                    </div>

                    <div class="print-section-title">Ledger Entries</div>
                    <table class="print-table print-ledger-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Particular</th>
                                <th>Reference</th>
                                <th>Type</th>
                                <th class="print-text-end">Debit</th>
                                <th class="print-text-end">Credit</th>
                                <th class="print-text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="printLedgerTableBody">
                            <tr><td colspan="8" class="print-text-center">Select supplier.</td></tr>
                        </tbody>
                    </table>

                    <div class="print-footer">
                        <div>This is a system generated supplier ledger statement.</div>
                        <div>Printed by <?= htmlspecialchars($printBranchName, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>

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
?>

<?php
$scriptsPath1 = BASE_PATH . 'includes/scripts.php';

if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "supplier-ledger";
    window.PRE_SUPPLIER_ID = <?= (int)$supplierId; ?>;
    window.BRANCH_NAME = <?= json_encode($printBranchName); ?>;
</script>

<script src="<?= BASE_URL; ?>pages-js/supplier-ledger.js?v=<?= time(); ?>"></script>

</body>
</html>
