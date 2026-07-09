<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$supplierId = (int)($_GET['id'] ?? $_GET['supplier_id'] ?? 0);
$page_title = 'Supplier View | Universal ERP';
$printBranchName = '';
if (!empty($_SESSION['branch_name'])) {
    $printBranchName = (string)$_SESSION['branch_name'];
} elseif (function_exists('currentBranchName')) {
    $printBranchName = (string)currentBranchName();
}
$printBranchName = trim($printBranchName) !== '' ? trim($printBranchName) : 'Branch';
$printCompanyName = $printBranchName;
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style id="supplierPrintPageSize">@page { size: A4 landscape; margin: 8mm; }</style>
    <style>
        .view-label { color:#74788d; font-size:12px; margin-bottom:3px; }
        .view-value { font-weight:600; word-break:break-word; }
        .summary-card .card-body { padding:16px; }
        .screen-purchase-table th,
        .screen-purchase-table td { white-space: nowrap; }
        .print-format-btn-group .btn { min-width: 92px; }
        .supplier-print-report { display: none; }

        @media print {
            html, body {
                background:#fff !important;
                color:#111827 !important;
                font-size:11px !important;
                -webkit-print-color-adjust:exact !important;
                print-color-adjust:exact !important;
            }
            body * { visibility:hidden !important; }
            #supplierPrintReport, #supplierPrintReport * { visibility:visible !important; }
            #supplierPrintReport {
                display:block !important;
                position:absolute !important;
                left:0 !important;
                top:0 !important;
                width:100% !important;
                padding:0 !important;
                margin:0 !important;
                background:#fff !important;
            }
            .vertical-menu, .navbar-header, .page-title-box, .footer, .right-bar, .rightbar-overlay, #preloader, .supplier-screen-wrapper, .no-print {
                display:none !important;
            }
            .main-content, .page-content, .container-fluid {
                margin:0 !important;
                padding:0 !important;
                width:100% !important;
                max-width:100% !important;
            }
            body.supplier-print-a3 { font-size:12px !important; }
            body.supplier-print-a3 .print-company { font-size:21px !important; }
            body.supplier-print-a3 .print-title { font-size:15px !important; }
            body.supplier-print-a3 .print-meta { font-size:11px !important; }
            body.supplier-print-a3 .print-section-title { font-size:13px !important; }
            body.supplier-print-a3 .print-info-row { grid-template-columns:115px 1fr !important; font-size:11.5px !important; line-height:1.65 !important; }
            body.supplier-print-a3 .print-kpi { min-height:56px !important; padding:9px 10px !important; }
            body.supplier-print-a3 .print-kpi span { font-size:10px !important; }
            body.supplier-print-a3 .print-kpi strong { font-size:16px !important; }
            body.supplier-print-a3 .print-table { font-size:11.5px !important; }
            body.supplier-print-a3 .print-table th { font-size:10px !important; }
            body.supplier-print-a3 .print-table th, body.supplier-print-a3 .print-table td { padding:7px 7px !important; }
            body.supplier-print-a3 .print-footer { font-size:10px !important; margin-top:14px !important; }
            body.supplier-print-a4 .print-table { font-size:10px !important; }
            .print-header {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                border-bottom:2px solid #111827;
                padding-bottom:8px;
                margin-bottom:10px;
            }
            .print-company {
                font-size:17px;
                font-weight:800;
                letter-spacing:.04em;
                text-transform:uppercase;
                color:#111827 !important;
            }
            .print-title { font-size:13px; font-weight:700; color:#475569 !important; margin-top:2px; }
            .print-meta { text-align:right; font-size:10px; color:#475569 !important; line-height:1.6; }
            .print-section-title {
                font-size:12px;
                font-weight:800;
                margin:8px 0 6px;
                color:#111827 !important;
                text-transform:uppercase;
                letter-spacing:.03em;
            }
            .print-info-grid { display:grid; grid-template-columns:1.05fr .95fr; gap:8px; margin-bottom:8px; }
            .print-box { border:1px solid #cbd5e1; border-radius:4px; padding:7px 8px; background:#fff !important; page-break-inside:avoid; }
            .print-info-row {
                display:grid;
                grid-template-columns:98px 1fr;
                gap:6px;
                line-height:1.55;
                border-bottom:1px dashed #e2e8f0;
                padding:2px 0;
            }
            .print-info-row:last-child { border-bottom:0; }
            .print-label { font-weight:800; color:#334155 !important; }
            .print-value { color:#111827 !important; word-break:break-word; }
            .print-kpi-grid { display:grid; grid-template-columns:repeat(5, 1fr); gap:6px; margin-bottom:8px; }
            .print-kpi { border:1px solid #cbd5e1; border-radius:4px; padding:7px 8px; background:#f8fafc !important; min-height:46px; }
            .print-kpi span { display:block; font-size:9px; text-transform:uppercase; letter-spacing:.04em; color:#64748b !important; font-weight:700; margin-bottom:4px; }
            .print-kpi strong { display:block; font-size:13px; color:#111827 !important; }
            .print-table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:10px; }
            .print-table th, .print-table td {
                border:1px solid #cbd5e1;
                padding:5px 5px;
                vertical-align:top;
                white-space:normal !important;
                word-break:break-word;
                color:#111827 !important;
            }
            .print-table th { background:#e2e8f0 !important; font-size:9px; text-transform:uppercase; letter-spacing:.03em; font-weight:800; }
            .print-table tbody tr:nth-child(even) td { background:#f8fafc !important; }
            .print-text-end { text-align:right !important; }
            .print-text-center { text-align:center !important; }
            .print-footer {
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-top:10px;
                padding-top:6px;
                border-top:1px solid #cbd5e1;
                font-size:9px;
                color:#64748b !important;
            }
            .print-purchase-table th:nth-child(1), .print-purchase-table td:nth-child(1) { width:4%; }
            .print-purchase-table th:nth-child(2), .print-purchase-table td:nth-child(2) { width:15%; }
            .print-purchase-table th:nth-child(3), .print-purchase-table td:nth-child(3) { width:10%; }
            .print-purchase-table th:nth-child(4), .print-purchase-table td:nth-child(4) { width:7%; }
            .print-purchase-table th:nth-child(5), .print-purchase-table td:nth-child(5) { width:12%; }
            .print-purchase-table th:nth-child(6), .print-purchase-table td:nth-child(6) { width:12%; }
            .print-purchase-table th:nth-child(7), .print-purchase-table td:nth-child(7) { width:12%; }
            .print-purchase-table th:nth-child(8), .print-purchase-table td:nth-child(8) { width:10%; }
            .print-purchase-table th:nth-child(9), .print-purchase-table td:nth-child(9) { width:18%; }
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

                <div class="row no-print">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-0">Supplier View</h4>
                                <p class="text-muted mb-0 mt-1">Supplier details, bank details and purchase history</p>
                            </div>
                            <div>
                                <a href="<?= BASE_URL; ?>pages/suppliers.php" class="btn btn-light me-1">
                                    <i class="mdi mdi-arrow-left me-1"></i> Supplier List
                                </a>
                                <div class="btn-group print-format-btn-group" role="group" aria-label="Supplier print format">
                                    <button type="button" class="btn btn-outline-secondary print-supplier-format-btn" data-format="a4">
                                        <i class="mdi mdi-printer me-1"></i> A4 Print
                                    </button>
                                    <button type="button" class="btn btn-outline-dark print-supplier-format-btn" data-format="a3">
                                        <i class="mdi mdi-printer-pos me-1"></i> A3 Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="supplierId" value="<?= (int)$supplierId; ?>">

                <div id="supplierViewAlert" class="alert alert-info">Loading supplier details...</div>

                <div id="supplierViewContent" class="supplier-screen-wrapper" style="display:none;">

                    <div class="row">
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-warning" id="openingOutstandingCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Opening Outstanding</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-info" id="purchaseAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Purchase Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-success" id="paidAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Paid Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-danger" id="dueAmountCard">₹0.00</h4>
                                    <p class="text-muted mb-0">Due Amount</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="mb-1" id="supplierNameText">-</h4>
                                    <div id="supplierStatusBadge"></div>
                                </div>
                                <div class="no-print" id="supplierTopActions"></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Mobile</div>
                                    <div class="view-value" id="mobileText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Email</div>
                                    <div class="view-value" id="emailText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">GST Number</div>
                                    <div class="view-value" id="gstText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">PAN Number</div>
                                    <div class="view-value" id="panText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">DL Number</div>
                                    <div class="view-value" id="dlText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">FL Number</div>
                                    <div class="view-value" id="flText">-</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="view-label">Address</div>
                                    <div class="view-value" id="addressText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Bank Details</h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Bank Name</div>
                                    <div class="view-value" id="bankNameText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Account No</div>
                                    <div class="view-value" id="accountNoText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Branch</div>
                                    <div class="view-value" id="bankBranchText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">IFSC</div>
                                    <div class="view-value" id="ifscText">-</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="view-label">UPI / QR Details</div>
                                    <div class="view-value" id="upiText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-size-15 mb-0">Purchase List</h5>
                                <span class="badge bg-info" id="purchaseCountBadge">0 Purchases</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered mb-0 screen-purchase-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Bill No</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Paid</th>
                                            <th class="text-end">Due</th>
                                            <th>Status</th>
                                            <th class="text-end no-print" width="170">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseTableBody">
                                        <tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

                <div id="supplierPrintReport" class="supplier-print-report">
                    <div class="print-header">
                        <div>
                            <div class="print-company"><?= htmlspecialchars($printCompanyName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="print-title">Supplier Account Statement</div>
                        </div>
                        <div class="print-meta">
                            <div>Format: <span id="printSupplierFormatText">A4 Landscape</span></div>
                            <div>Supplier ID: <span id="printSupplierId">-</span></div>
                            <div>Printed: <span id="printGeneratedAt">-</span></div>
                        </div>
                    </div>

                    <div class="print-info-grid">
                        <div class="print-box">
                            <div class="print-section-title">Supplier Details</div>
                            <div class="print-info-row"><div class="print-label">Name</div><div class="print-value" id="printSupplierName">-</div></div>
                            <div class="print-info-row"><div class="print-label">Mobile</div><div class="print-value" id="printSupplierMobile">-</div></div>
                            <div class="print-info-row"><div class="print-label">Email</div><div class="print-value" id="printSupplierEmail">-</div></div>
                            <div class="print-info-row"><div class="print-label">GST</div><div class="print-value" id="printSupplierGst">-</div></div>
                            <div class="print-info-row"><div class="print-label">PAN</div><div class="print-value" id="printSupplierPan">-</div></div>
                            <div class="print-info-row"><div class="print-label">DL / FL</div><div class="print-value" id="printSupplierDlFl">-</div></div>
                            <div class="print-info-row"><div class="print-label">Status</div><div class="print-value" id="printSupplierStatus">-</div></div>
                            <div class="print-info-row"><div class="print-label">Address</div><div class="print-value" id="printSupplierAddress">-</div></div>
                        </div>

                        <div class="print-box">
                            <div class="print-section-title">Bank Details</div>
                            <div class="print-info-row"><div class="print-label">Bank</div><div class="print-value" id="printBankName">-</div></div>
                            <div class="print-info-row"><div class="print-label">Account No</div><div class="print-value" id="printAccountNo">-</div></div>
                            <div class="print-info-row"><div class="print-label">Branch</div><div class="print-value" id="printBankBranch">-</div></div>
                            <div class="print-info-row"><div class="print-label">IFSC</div><div class="print-value" id="printIfsc">-</div></div>
                            <div class="print-info-row"><div class="print-label">UPI</div><div class="print-value" id="printUpi">-</div></div>
                        </div>
                    </div>

                    <div class="print-section-title">Ledger Summary</div>
                    <div class="print-kpi-grid">
                        <div class="print-kpi"><span>Opening Outstanding</span><strong id="printKpiOpening">₹0.00</strong></div>
                        <div class="print-kpi"><span>Purchase Amount</span><strong id="printKpiPurchase">₹0.00</strong></div>
                        <div class="print-kpi"><span>Purchase Paid</span><strong id="printKpiPaid">₹0.00</strong></div>
                        <div class="print-kpi"><span>Purchase Due</span><strong id="printKpiPurchaseDue">₹0.00</strong></div>
                        <div class="print-kpi"><span>Total Due</span><strong id="printKpiDue">₹0.00</strong></div>
                    </div>

                    <div class="print-section-title">Purchase Details</div>
                    <table class="print-table print-purchase-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Bill No</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th class="print-text-end">Sub Total</th>
                                <th class="print-text-end">Grand Total</th>
                                <th class="print-text-end">Paid</th>
                                <th class="print-text-end">Due</th>
                                <th>Status / Notes</th>
                            </tr>
                        </thead>
                        <tbody id="printPurchaseTableBody">
                            <tr><td colspan="9" class="print-text-center">No purchases found.</td></tr>
                        </tbody>
                    </table>

                    <div class="print-footer">
                        <div>This is a system generated supplier statement.</div>
                        <div>Printed by <?= htmlspecialchars($printBranchName, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>


            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php
$rightbarPath = BASE_PATH . 'includes/rightbar.php';
if (file_exists($rightbarPath)) { include $rightbarPath; }

$scriptsPath = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath)) { include $scriptsPath; }
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.MASTER_FILE = "suppliers";
    window.SUPPLIER_PRINT_BRANCH_NAME = <?= json_encode($printBranchName); ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/supplier-view.js?v=<?= time(); ?>"></script>

</body>
</html>
