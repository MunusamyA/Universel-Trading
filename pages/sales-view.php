<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

/** @var PDO $pdo */

$page_title = 'Sales View | Universal ERP';
$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {
    header('Location: ' . BASE_URL . 'pages/sales-list.php');
    exit;
}

$businessId = function_exists('currentBusinessId') ? (int)currentBusinessId() : (int)($_SESSION['business_id'] ?? 0);
$branchId   = function_exists('currentBranchId') ? (int)currentBranchId() : (int)($_SESSION['branch_id'] ?? 0);

if ($businessId <= 0 || $branchId <= 0) {
    die('Invalid business or branch session.');
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moneyView($value): string
{
    return '₹' . number_format((float)($value ?? 0), 2);
}

function qtyView($value): string
{
    return number_format((float)($value ?? 0), 2);
}

function dateView($value): string
{
    if (empty($value) || $value === '0000-00-00') {
        return '-';
    }
    return date('d-m-Y', strtotime($value));
}

function documentLabelView($type): string
{
    $labels = [
        1 => 'Quotation',
        2 => 'Proforma Bill',
        3 => 'Sales Bill',
        4 => 'Direct Sale',
        5 => 'Final Invoice'
    ];
    return $labels[(int)$type] ?? 'Sales Document';
}

function invoiceLabelView($type): string
{
    return (int)$type === 1 ? 'GST Invoice' : 'Non-GST Invoice';
}

function statusBadgeView($status): string
{
    $status = (int)$status;
    if ($status === 1) {
        return '<span class="badge bg-warning">Draft / Open</span>';
    }
    if ($status === 2) {
        return '<span class="badge bg-success">Completed</span>';
    }
    if ($status === 3) {
        return '<span class="badge bg-danger">Deleted</span>';
    }
    if ($status === 4) {
        return '<span class="badge bg-secondary">Cancelled</span>';
    }
    return '<span class="badge bg-light text-dark">Unknown</span>';
}

$saleStmt = $pdo->prepare("
    SELECT
        s.*,
        c.customer_name,
        c.mobile AS customer_mobile,
        c.gst_number,
        c.address,
        c.city,
        c.state,
        c.pincode
    FROM sales s
    INNER JOIN customers c ON c.id = s.customer_id
    WHERE s.id = :id
    AND s.business_id = :business_id
    AND s.branch_id = :branch_id
    LIMIT 1
");
$saleStmt->execute([
    ':id' => $saleId,
    ':business_id' => $businessId,
    ':branch_id' => $branchId
]);
$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Sales document not found.');
}

$itemsStmt = $pdo->prepare("
    SELECT *
    FROM sales_items
    WHERE sales_id = :sales_id
    AND business_id = :business_id
    AND branch_id = :branch_id
    AND status = 1
    ORDER BY id ASC
");
$itemsStmt->execute([
    ':sales_id' => $saleId,
    ':business_id' => $businessId,
    ':branch_id' => $branchId
]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$payments = [];
try {
    $payStmt = $pdo->prepare("
        SELECT sp.*, pm.mode_name
        FROM sales_payments sp
        LEFT JOIN payment_modes pm ON pm.id = sp.payment_mode_id
        WHERE sp.sales_id = :sales_id
        AND sp.business_id = :business_id
        AND sp.branch_id = :branch_id
        AND sp.status = 1
        ORDER BY sp.id ASC
    ");
    $payStmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $payments = [];
}

$customerAddressParts = array_filter([
    $sale['address'] ?? '',
    $sale['city'] ?? '',
    $sale['state'] ?? '',
    $sale['pincode'] ?? ''
]);
$customerAddress = implode(', ', $customerAddressParts);
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .view-label {
            color: #74788d;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .view-value {
            font-size: 15px;
            font-weight: 600;
            color: #2b2f38;
            word-break: break-word;
        }
        .view-box {
            border: 1px solid #e9edf3;
            border-radius: 8px;
            padding: 12px;
            height: 100%;
            background: #fff;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 7px 0;
            border-bottom: 1px dashed #e6e8ef;
        }
        .summary-row:last-child {
            border-bottom: 0;
        }
        .bottom-summary-box {
            max-width: 520px;
            margin-left: auto;
        }
        .notes-view-box {
            height: auto !important;
            min-height: 120px;
            margin-bottom: 16px;
        }
        .table th {
            background: #f3f6f9;
            color: #1f2d3d;
            font-weight: 700;
        }
        @media print {
            .vertical-menu,
            .navbar-header,
            .footer,
            .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .page-content {
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body data-sidebar="dark">

<?php include BASE_PATH . 'includes/pre-loader.php'; ?>

<div id="layout-wrapper">
    <?php include BASE_PATH . 'includes/topbar.php'; ?>

    <div class="vertical-menu no-print">
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
                                <h4 class="mb-0">Sales View</h4>
                                <p class="text-muted mb-0 mt-1">Read-only complete sales document details</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL; ?>pages/sales-list.php?document_type=<?= (int)$sale['document_type']; ?>" class="btn btn-light">
                                    <i class="mdi mdi-arrow-left me-1"></i> Back
                                </a>
                                <a href="<?= BASE_URL; ?>pages/sales-print.php?id=<?= (int)$saleId; ?>&print=1" target="_blank" class="btn btn-danger">
                                    <i class="mdi mdi-file-pdf-box me-1"></i> Print PDF
                                </a>
                                <button type="button" class="btn btn-primary" onclick="window.print();">
                                    <i class="mdi mdi-printer me-1"></i> Print View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-1"><?= e($sale['sales_no'] ?? ''); ?></h4>
                                <div class="text-muted">
                                    <?= e(documentLabelView($sale['document_type'] ?? 0)); ?> | <?= e(invoiceLabelView($sale['invoice_type'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?= statusBadgeView($sale['status'] ?? 0); ?>
                                <div class="small text-muted mt-2">View Only</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="view-box">
                                    <div class="view-label">Sales Date</div>
                                    <div class="view-value"><?= e(dateView($sale['sales_date'] ?? '')); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="view-box">
                                    <div class="view-label">Due / Validity Date</div>
                                    <div class="view-value"><?= e(dateView($sale['due_date'] ?? ($sale['validity_date'] ?? ''))); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="view-box">
                                    <div class="view-label">Customer</div>
                                    <div class="view-value"><?= e($sale['customer_name'] ?? '-'); ?></div>
                                    <div class="small text-muted"><?= e($sale['customer_mobile'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="view-box">
                                    <div class="view-label">GST No</div>
                                    <div class="view-value"><?= e($sale['gst_number'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="view-box">
                                    <div class="view-label">Customer Address</div>
                                    <div class="view-value"><?= e($customerAddress ?: '-'); ?></div>
                                    <?php if (!empty($sale['delivery_address'])): ?>
                                        <hr>
                                        <div class="view-label">Delivery Address</div>
                                        <div class="view-value"><?= nl2br(e($sale['delivery_address'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-3">Item Details</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-centered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Batch / Bill</th>
                                        <th class="text-end">Purchase Rate</th>
                                        <th class="text-end">Unit</th>
                                        <th class="text-end">Qty / Unit</th>
                                        <th class="text-end">Loose</th>
                                        <th class="text-end">Total Qty</th>
                                        <th class="text-end">Sale Rate</th>
                                        <th class="text-end">Gross</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">GST</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$items): ?>
                                        <tr>
                                            <td colspan="13" class="text-center text-muted">No items found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $index => $item): ?>
                                            <tr>
                                                <td><?= $index + 1; ?></td>
                                                <td>
                                                    <strong><?= e($item['product_name'] ?? '-'); ?></strong><br>
                                                    <small class="text-muted"><?= e($item['product_code'] ?? ''); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= e($item['purchase_batch_no'] ?? '-'); ?></strong><br>
                                                    <small class="text-muted">Bill: <?= e($item['purchase_bill_no'] ?? '-'); ?></small>
                                                </td>
                                                <td class="text-end"><?= moneyView($item['purchase_price'] ?? 0); ?></td>
                                                <td class="text-end"><?= qtyView($item['unit_qty'] ?? 0); ?></td>
                                                <td class="text-end"><?= qtyView($item['qty_per_unit'] ?? 0); ?></td>
                                                <td class="text-end"><?= qtyView($item['loose_qty'] ?? 0); ?></td>
                                                <td class="text-end"><strong><?= qtyView($item['qty'] ?? 0); ?></strong></td>
                                                <td class="text-end"><?= moneyView($item['selling_rate'] ?? 0); ?></td>
                                                <td class="text-end"><?= moneyView($item['gross_amount'] ?? 0); ?></td>
                                                <td class="text-end"><?= moneyView($item['discount_amount'] ?? 0); ?></td>
                                                <td class="text-end">
                                                    <?= moneyView($item['gst_amount'] ?? 0); ?><br>
                                                    <small class="text-muted"><?= qtyView($item['gst_percentage'] ?? 0); ?>%</small>
                                                </td>
                                                <td class="text-end"><strong><?= moneyView($item['line_total'] ?? 0); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <h5 class="mb-3">Payment Details</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-centered mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Mode</th>
                                                <th>Date</th>
                                                <th>Reference</th>
                                                <th class="text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$payments): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No payment received.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($payments as $index => $payment): ?>
                                                    <tr>
                                                        <td><?= $index + 1; ?></td>
                                                        <td><?= e($payment['mode_name'] ?? '-'); ?></td>
                                                        <td><?= e(dateView($payment['payment_date'] ?? '')); ?></td>
                                                        <td><?= e($payment['reference_no'] ?? '-'); ?></td>
                                                        <td class="text-end"><?= moneyView($payment['payment_amount'] ?? 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <h5 class="mb-3">Notes</h5>
                                <div class="view-box notes-view-box">
                                    <div class="view-label">Remarks</div>
                                    <div class="view-value"><?= nl2br(e($sale['notes'] ?? '-')); ?></div>
                                    <?php if (!empty($sale['terms'])): ?>
                                        <hr>
                                        <div class="view-label">Terms</div>
                                        <div><?= nl2br(e($sale['terms'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3 mb-4">
                            <div class="col-12">
                                <div class="view-box bottom-summary-box">
                                    <h5 class="mb-3">Amount Summary</h5>
                                    <div class="summary-row">
                                        <span>Sub Total</span>
                                        <strong><?= moneyView($sale['sub_total'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Discount</span>
                                        <strong><?= moneyView($sale['discount_amount'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Tax Amount</span>
                                        <strong><?= moneyView($sale['tax_amount'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Shipping Charges</span>
                                        <strong><?= moneyView($sale['shipping_charges'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Round Off</span>
                                        <strong><?= moneyView($sale['round_off'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row fs-5">
                                        <span>Grand Total</span>
                                        <strong><?= moneyView($sale['grand_total'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Paid</span>
                                        <strong class="text-success"><?= moneyView($sale['paid_amount'] ?? 0); ?></strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Due</span>
                                        <strong class="text-danger"><?= moneyView($sale['due_amount'] ?? 0); ?></strong>
                                    </div>
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

<?php
$rightbarPath = BASE_PATH . 'includes/rightbar.php';
if (file_exists($rightbarPath)) {
    include $rightbarPath;
}

$scriptsPath = BASE_PATH . 'includes/scripts.php';
if (file_exists($scriptsPath)) {
    include $scriptsPath;
}
?>

</body>
</html>
