<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$customerId = (int)($_GET['id'] ?? $_GET['customer_id'] ?? 0);
$page_title = 'Customer View | Universal ERP';
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
    <style id="customerPrintPageSize">@page { size: A4 landscape; margin: 8mm; }</style>
    <style>
        .customer-view-summary .card-body {
            padding: 14px 12px;
        }
        .customer-view-summary h4 {
            margin-bottom: 2px;
            font-size: 20px;
        }
        .customer-profile-line {
            line-height: 1.8;
        }
        .sales-action-group .btn {
            margin-left: 3px;
        }
        .customer-print-report {
            display: none;
        }
        .screen-sales-table th,
        .screen-sales-table td {
            white-space: nowrap;
        }
        .print-format-btn-group .btn {
            min-width: 92px;
        }
        .print-format-hint {
            font-size: 11px;
            color: #64748b;
        }
        @media print {
            html,
            body {
                background: #fff !important;
                color: #111827 !important;
                font-size: 11px !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body * {
                visibility: hidden !important;
            }
            #customerPrintReport,
            #customerPrintReport * {
                visibility: visible !important;
            }
            #customerPrintReport {
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
            .customer-screen-wrapper {
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
            body.customer-print-a3 {
                font-size: 12px !important;
            }
            body.customer-print-a3 .print-company {
                font-size: 21px !important;
            }
            body.customer-print-a3 .print-title {
                font-size: 15px !important;
            }
            body.customer-print-a3 .print-meta {
                font-size: 11px !important;
            }
            body.customer-print-a3 .print-section-title {
                font-size: 13px !important;
            }
            body.customer-print-a3 .print-info-row {
                grid-template-columns: 115px 1fr !important;
                font-size: 11.5px !important;
                line-height: 1.65 !important;
            }
            body.customer-print-a3 .print-kpi {
                min-height: 56px !important;
                padding: 9px 10px !important;
            }
            body.customer-print-a3 .print-kpi span {
                font-size: 10px !important;
            }
            body.customer-print-a3 .print-kpi strong {
                font-size: 16px !important;
            }
            body.customer-print-a3 .print-table {
                font-size: 11.5px !important;
            }
            body.customer-print-a3 .print-table th {
                font-size: 10px !important;
            }
            body.customer-print-a3 .print-table th,
            body.customer-print-a3 .print-table td {
                padding: 7px 7px !important;
            }
            body.customer-print-a3 .print-footer {
                font-size: 10px !important;
                margin-top: 14px !important;
            }
            body.customer-print-a4 .print-table {
                font-size: 10px !important;
            }
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
                grid-template-columns: 1.15fr .85fr;
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
                grid-template-columns: 90px 1fr;
                gap: 6px;
                line-height: 1.55;
                border-bottom: 1px dashed #e2e8f0;
                padding: 2px 0;
            }
            .print-info-row:last-child {
                border-bottom: 0;
            }
            .print-label {
                font-weight: 800;
                color: #334155 !important;
            }
            .print-value {
                color: #111827 !important;
                word-break: break-word;
            }
            .print-kpi-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
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
            .print-table tbody tr:nth-child(even) td {
                background: #f8fafc !important;
            }
            .print-text-end {
                text-align: right !important;
            }
            .print-text-center {
                text-align: center !important;
            }
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
            .print-sales-table th:nth-child(1),
            .print-sales-table td:nth-child(1) { width: 4%; }
            .print-sales-table th:nth-child(2),
            .print-sales-table td:nth-child(2) { width: 13%; }
            .print-sales-table th:nth-child(3),
            .print-sales-table td:nth-child(3) { width: 10%; }
            .print-sales-table th:nth-child(4),
            .print-sales-table td:nth-child(4) { width: 8%; }
            .print-sales-table th:nth-child(5),
            .print-sales-table td:nth-child(5) { width: 9%; }
            .print-sales-table th:nth-child(6),
            .print-sales-table td:nth-child(6) { width: 8%; }
            .print-sales-table th:nth-child(7),
            .print-sales-table td:nth-child(7) { width: 10%; }
            .print-sales-table th:nth-child(8),
            .print-sales-table td:nth-child(8) { width: 9%; }
            .print-sales-table th:nth-child(9),
            .print-sales-table td:nth-child(9) { width: 9%; }
            .print-sales-table th:nth-child(10),
            .print-sales-table td:nth-child(10) { width: 10%; }
            .print-sales-table th:nth-child(11),
            .print-sales-table td:nth-child(11) { width: 10%; }
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

                <div class="customer-screen-wrapper">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-flex align-items-center justify-content-between">
                                <div>
                                    <h4 class="mb-0" id="customerViewTitle">Customer View</h4>
                                    <p class="text-muted mb-0 mt-1" id="customerViewNote">Sales details and payment status</p>
                                </div>

                                <div class="page-title-right">
                                    <a href="<?= BASE_URL; ?>pages/customers.php" class="btn btn-light" id="backToCustomersBtn">
                                        <i class="mdi mdi-arrow-left me-1"></i> Customers
                                    </a>
                                    <div class="btn-group print-format-btn-group" role="group" aria-label="Print format">
                                        <button type="button" class="btn btn-outline-secondary print-customer-format-btn" data-format="a4">
                                            <i class="mdi mdi-printer me-1"></i> A4 Print
                                        </button>
                                        <button type="button" class="btn btn-outline-dark print-customer-format-btn" data-format="a3">
                                            <i class="mdi mdi-printer-pos me-1"></i> A3 Print
                                        </button>
                                    </div>
                                    <a href="#" class="btn btn-success d-none" id="receiveCustomerPaymentBtn">
                                        <i class="mdi mdi-cash-plus me-1"></i> Receive Payment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row customer-view-summary">
                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center">
                                <div class="card-body text-muted">
                                    <h4 class="text-warning" id="viewOpeningBalance">₹0.00</h4>
                                    Opening Balance
                                    <div><small class="text-muted" id="viewOpeningDueText">Due: ₹0.00</small></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center">
                                <div class="card-body text-muted">
                                    <h4 class="text-info" id="viewOverallSales">₹0.00</h4>
                                    Overall Sales
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center">
                                <div class="card-body text-muted">
                                    <h4 class="text-success" id="viewPaidAmount">₹0.00</h4>
                                    Paid Amount
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card text-center">
                                <div class="card-body text-muted">
                                    <h4 class="text-danger" id="viewDueAmount">₹0.00</h4>
                                    Due Amount
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="font-size-15 mb-3">Customer Details</h5>
                                    <div id="customerProfileBox" class="customer-profile-line text-muted">
                                        Loading customer details...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="font-size-15 mb-0">Sales Summary</h5>
                                        <span class="badge bg-primary-subtle text-primary" id="totalSalesCountBadge">0 Sales</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-centered table-nowrap mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Total Sales</th>
                                                    <th class="text-end">Sales Paid</th>
                                                    <th class="text-end">Sales Due</th>
                                                    <th class="text-end">Opening Paid</th>
                                                    <th class="text-end">Opening Due</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong id="summarySalesTotal">₹0.00</strong></td>
                                                    <td class="text-end text-success"><strong id="summarySalesPaid">₹0.00</strong></td>
                                                    <td class="text-end text-danger"><strong id="summarySalesDue">₹0.00</strong></td>
                                                    <td class="text-end text-success"><strong id="summaryOpeningPaid">₹0.00</strong></td>
                                                    <td class="text-end text-danger"><strong id="summaryOpeningDue">₹0.00</strong></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="font-size-15 mb-1">Sales Details</h5>
                                    <p class="text-muted mb-0">View, print and collect pending payments from customer sales.</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered mb-0 screen-sales-table">
                                    <thead>
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Document</th>
                                            <th>Date</th>
                                            <th class="text-end">Sub Total</th>
                                            <th class="text-end">Tax</th>
                                            <th class="text-end">Grand Total</th>
                                            <th class="text-end">Paid</th>
                                            <th class="text-end">Due</th>
                                            <th>Payment</th>
                                            <th>Status</th>
                                            <th width="160" class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="customerSalesTableBody">
                                        <tr>
                                            <td colspan="11" class="text-center text-muted">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="customerPrintReport" class="customer-print-report">
                    <div class="print-header">
                        <div>
                            <div class="print-company"><?= htmlspecialchars($printCompanyName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="print-title">Customer Account Statement</div>
                        </div>
                        <div class="print-meta">
                            <div><strong>Generated:</strong> <span id="printGeneratedAt">-</span></div>
                            <div><strong>Format:</strong> <span id="printFormatLabel">A4 Landscape</span></div>
                            <div><strong>Customer ID:</strong> <span id="printCustomerId">-</span></div>
                        </div>
                    </div>

                    <div class="print-info-grid">
                        <div class="print-box">
                            <div class="print-section-title">Customer Details</div>
                            <div class="print-info-row"><div class="print-label">Name</div><div class="print-value" id="printCustomerName">-</div></div>
                            <div class="print-info-row"><div class="print-label">Mobile</div><div class="print-value" id="printCustomerMobile">-</div></div>
                            <div class="print-info-row"><div class="print-label">Email</div><div class="print-value" id="printCustomerEmail">-</div></div>
                            <div class="print-info-row"><div class="print-label">GST</div><div class="print-value" id="printCustomerGst">-</div></div>
                            <div class="print-info-row"><div class="print-label">Zone</div><div class="print-value" id="printCustomerZone">-</div></div>
                            <div class="print-info-row"><div class="print-label">Status</div><div class="print-value" id="printCustomerStatus">-</div></div>
                            <div class="print-info-row"><div class="print-label">Address</div><div class="print-value" id="printCustomerAddress">-</div></div>
                        </div>

                        <div class="print-box">
                            <div class="print-section-title">Ledger Summary</div>
                            <div class="print-info-row"><div class="print-label">Opening</div><div class="print-value print-text-end" id="printOpeningBalance">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Sales</div><div class="print-value print-text-end" id="printOverallSales">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Paid</div><div class="print-value print-text-end" id="printPaidAmount">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Due</div><div class="print-value print-text-end" id="printDueAmount">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Sales Paid</div><div class="print-value print-text-end" id="printSalesPaid">₹0.00</div></div>
                            <div class="print-info-row"><div class="print-label">Sales Due</div><div class="print-value print-text-end" id="printSalesDue">₹0.00</div></div>
                        </div>
                    </div>

                    <div class="print-kpi-grid">
                        <div class="print-kpi"><span>Opening Balance</span><strong id="printKpiOpeningBalance">₹0.00</strong></div>
                        <div class="print-kpi"><span>Overall Sales</span><strong id="printKpiOverallSales">₹0.00</strong></div>
                        <div class="print-kpi"><span>Paid Amount</span><strong id="printKpiPaidAmount">₹0.00</strong></div>
                        <div class="print-kpi"><span>Due Amount</span><strong id="printKpiDueAmount">₹0.00</strong></div>
                        <div class="print-kpi"><span>Total Documents</span><strong id="printKpiSalesCount">0</strong></div>
                    </div>

                    <div class="print-section-title">Sales Details</div>
                    <table class="print-table print-sales-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Document No</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th class="print-text-end">Sub Total</th>
                                <th class="print-text-end">Tax</th>
                                <th class="print-text-end">Grand Total</th>
                                <th class="print-text-end">Paid</th>
                                <th class="print-text-end">Due</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="printSalesTableBody">
                            <tr>
                                <td colspan="11" class="print-text-center">No sales records found.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="print-footer">
                        <div>This is a system generated customer statement.</div>
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

$scriptsPath1 = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath1)) {
    include $scriptsPath1;
}
?>

<script>
    window.BASE_URL = <?= json_encode(BASE_URL); ?>;
    window.CUSTOMER_ID = <?= (int)$customerId; ?>;
</script>
<script src="<?= BASE_URL; ?>pages-js/customer-view.js?v=<?= time(); ?>"></script>

</body>
</html>
