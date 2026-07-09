<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$purchaseId = (int)($_GET['id'] ?? $_GET['purchase_id'] ?? 0);
$printMode = (int)($_GET['print'] ?? 0) === 1 ? 1 : 0;
$printFormat = strtolower((string)($_GET['format'] ?? $_GET['print_format'] ?? 'a4'));
$printFormat = $printFormat === 'a3' ? 'a3' : 'a4';
$page_title = 'Purchase View | Universal ERP';

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
    <style id="purchasePrintPageSize">@page { size: A4 landscape; margin: 8mm; }</style>
    <style>
        .view-label { color:#74788d; font-size:12px; margin-bottom:3px; }
        .view-value { font-weight:600; word-break:break-word; }
        .summary-card .card-body { padding:16px; }
        .screen-items-table th,
        .screen-items-table td { white-space: nowrap; }
        .print-format-btn-group .btn { min-width: 92px; }
        .purchase-print-report { display:none; }

        @media print {
            html, body {
                background:#fff !important;
                color:#111827 !important;
                font-size:11px !important;
                -webkit-print-color-adjust:exact !important;
                print-color-adjust:exact !important;
            }
            body * { visibility:hidden !important; }
            #purchasePrintReport, #purchasePrintReport * { visibility:visible !important; }
            #purchasePrintReport {
                display:block !important;
                position:absolute !important;
                left:0 !important;
                top:0 !important;
                width:100% !important;
                padding:0 !important;
                margin:0 !important;
                background:#fff !important;
            }
            .vertical-menu, .navbar-header, .page-title-box, .footer, .right-bar, .rightbar-overlay, #preloader, .purchase-screen-wrapper, .no-print {
                display:none !important;
            }
            .main-content, .page-content, .container-fluid {
                margin:0 !important;
                padding:0 !important;
                width:100% !important;
                max-width:100% !important;
            }
            body.purchase-print-a3 { font-size:12px !important; }
            body.purchase-print-a3 .print-company { font-size:21px !important; }
            body.purchase-print-a3 .print-title { font-size:15px !important; }
            body.purchase-print-a3 .print-meta { font-size:11px !important; }
            body.purchase-print-a3 .print-section-title { font-size:13px !important; }
            body.purchase-print-a3 .print-info-row { grid-template-columns:115px 1fr !important; font-size:11.5px !important; line-height:1.65 !important; }
            body.purchase-print-a3 .print-kpi { min-height:56px !important; padding:9px 10px !important; }
            body.purchase-print-a3 .print-kpi span { font-size:10px !important; }
            body.purchase-print-a3 .print-kpi strong { font-size:16px !important; }
            body.purchase-print-a3 .print-table { font-size:11.5px !important; }
            body.purchase-print-a3 .print-table th { font-size:10px !important; }
            body.purchase-print-a3 .print-table th, body.purchase-print-a3 .print-table td { padding:7px 7px !important; }
            body.purchase-print-a3 .print-footer { font-size:10px !important; margin-top:14px !important; }
            body.purchase-print-a4 .print-table { font-size:10px !important; }
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
            .print-kpi-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:6px; margin-bottom:8px; }
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
            .print-items-table th:nth-child(1), .print-items-table td:nth-child(1) { width:4%; }
            .print-items-table th:nth-child(2), .print-items-table td:nth-child(2) { width:27%; }
            .print-items-table th:nth-child(3), .print-items-table td:nth-child(3) { width:9%; }
            .print-items-table th:nth-child(4), .print-items-table td:nth-child(4) { width:9%; }
            .print-items-table th:nth-child(5), .print-items-table td:nth-child(5) { width:12%; }
            .print-items-table th:nth-child(6), .print-items-table td:nth-child(6) { width:12%; }
            .print-items-table th:nth-child(7), .print-items-table td:nth-child(7) { width:12%; }
            .print-items-table th:nth-child(8), .print-items-table td:nth-child(8) { width:15%; }
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
                                <h4 class="mb-0">Purchase View</h4>
                                <p class="text-muted mb-0 mt-1">Purchase bill details and stock item summary</p>
                            </div>
                            <div>
                                <a href="<?= BASE_URL; ?>pages/purchases.php" class="btn btn-light me-1">
                                    <i class="mdi mdi-arrow-left me-1"></i> Purchase List
                                </a>
                                <div class="btn-group print-format-btn-group" role="group" aria-label="Purchase print format">
                                    <button type="button" class="btn btn-outline-secondary print-purchase-format-btn" data-format="a4">
                                        <i class="mdi mdi-printer me-1"></i> A4 Print
                                    </button>
                                    <button type="button" class="btn btn-outline-dark print-purchase-format-btn" data-format="a3">
                                        <i class="mdi mdi-printer-pos me-1"></i> A3 Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="purchaseId" value="<?= (int)$purchaseId; ?>">
                <input type="hidden" id="printMode" value="<?= (int)$printMode; ?>">
                <input type="hidden" id="printFormat" value="<?= htmlspecialchars($printFormat, ENT_QUOTES, 'UTF-8'); ?>">

                <div id="purchaseViewAlert" class="alert alert-info">Loading purchase details...</div>

                <div id="purchaseViewContent" class="purchase-screen-wrapper" style="display:none;">

                    <div class="row">
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-primary" id="grandTotalText">₹0.00</h4>
                                    <p class="text-muted mb-0">Grand Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-success" id="paidText">₹0.00</h4>
                                    <p class="text-muted mb-0">Paid Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-danger" id="dueAmountTop">₹0.00</h4>
                                    <p class="text-muted mb-0">Due Amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card summary-card text-center">
                                <div class="card-body">
                                    <h4 class="text-info" id="taxText">₹0.00</h4>
                                    <p class="text-muted mb-0">Tax Amount</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="mb-1" id="billNoText">-</h4>
                                    <div id="purchaseStatusBadge"></div>
                                </div>
                                <div class="text-end">
                                    <div class="view-label">Due Amount</div>
                                    <div class="view-value text-danger" id="dueText">₹0.00</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Purchase Date</div>
                                    <div class="view-value" id="purchaseDateText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Due Date</div>
                                    <div class="view-value" id="dueDateText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Supplier</div>
                                    <div class="view-value" id="supplierNameText">-</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="view-label">Batch No</div>
                                    <div class="view-value" id="batchNoText">-</div>
                                </div>
                                <div class="col-md-12 mb-0">
                                    <div class="view-label">Notes</div>
                                    <div class="view-value" id="notesText">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="font-size-15 mb-3">Amount Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-centered table-nowrap mb-0">
                                    <thead>
                                    <tr>
                                        <th class="text-end">Sub Total</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Tax</th>
                                        <th class="text-end">Round Off</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td class="text-end" id="subTotalText">₹0.00</td>
                                        <td class="text-end" id="discountText">₹0.00</td>
                                        <td class="text-end" id="taxSummaryText">₹0.00</td>
                                        <td class="text-end" id="roundOffText">₹0.00</td>
                                        <td class="text-end fw-bold" id="grandTotalSummaryText">₹0.00</td>
                                        <td class="text-end text-success" id="paidSummaryText">₹0.00</td>
                                        <td class="text-end text-danger fw-bold" id="dueSummaryText">₹0.00</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-size-15 mb-0">Purchase Items</h5>
                                <span class="badge bg-info" id="itemCountBadge">0 Items</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered screen-items-table mb-0">
                                    <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Product</th>
                                        <th>HSN</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">GST</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                    <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

                <div id="purchasePrintReport" class="purchase-print-report">
                    <div class="print-header">
                        <div>
                            <div class="print-company"><?= htmlspecialchars($printCompanyName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="print-title">Purchase Bill Statement</div>
                        </div>
                        <div class="print-meta">
                            <div><strong>Format:</strong> <span id="printPurchaseFormatText">A4 Landscape</span></div>
                            <div><strong>Printed:</strong> <span id="printGeneratedAt">-</span></div>
                            <div><strong>Branch:</strong> <?= htmlspecialchars($printBranchName, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <div class="print-info-grid">
                        <div class="print-box">
                            <div class="print-section-title">Purchase Details</div>
                            <div class="print-info-row"><div class="print-label">Bill No</div><div class="print-value" id="printBillNo">-</div></div>
                            <div class="print-info-row"><div class="print-label">Purchase Date</div><div class="print-value" id="printPurchaseDate">-</div></div>
                            <div class="print-info-row"><div class="print-label">Due Date</div><div class="print-value" id="printDueDate">-</div></div>
                            <div class="print-info-row"><div class="print-label">Batch No</div><div class="print-value" id="printBatchNo">-</div></div>
                            <div class="print-info-row"><div class="print-label">Status</div><div class="print-value" id="printPurchaseStatus">-</div></div>
                        </div>
                        <div class="print-box">
                            <div class="print-section-title">Supplier / Notes</div>
                            <div class="print-info-row"><div class="print-label">Supplier</div><div class="print-value" id="printSupplierName">-</div></div>
                            <div class="print-info-row"><div class="print-label">Purchase ID</div><div class="print-value" id="printPurchaseId">-</div></div>
                            <div class="print-info-row"><div class="print-label">Notes</div><div class="print-value" id="printNotes">-</div></div>
                        </div>
                    </div>

                    <div class="print-section-title">Amount Summary</div>
                    <div class="print-kpi-grid">
                        <div class="print-kpi"><span>Sub Total</span><strong id="printKpiSubTotal">₹0.00</strong></div>
                        <div class="print-kpi"><span>Discount</span><strong id="printKpiDiscount">₹0.00</strong></div>
                        <div class="print-kpi"><span>Tax</span><strong id="printKpiTax">₹0.00</strong></div>
                        <div class="print-kpi"><span>Grand Total</span><strong id="printKpiGrandTotal">₹0.00</strong></div>
                        <div class="print-kpi"><span>Paid</span><strong id="printKpiPaid">₹0.00</strong></div>
                        <div class="print-kpi"><span>Due</span><strong id="printKpiDue">₹0.00</strong></div>
                    </div>

                    <div class="print-section-title">Item Details</div>
                    <table class="print-table print-items-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>HSN</th>
                            <th class="print-text-end">Qty</th>
                            <th class="print-text-end">Rate</th>
                            <th class="print-text-end">Discount</th>
                            <th class="print-text-end">GST</th>
                            <th class="print-text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody id="printItemsTableBody">
                        <tr><td colspan="8" class="print-text-center">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="print-footer">
                        <div>This is a system generated purchase statement.</div>
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
    window.MASTER_FILE = "purchases";
</script>
<script src="<?= BASE_URL; ?>pages-js/purchase-view.js?v=<?= time(); ?>"></script>

</body>
</html>
