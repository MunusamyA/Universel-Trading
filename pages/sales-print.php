<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

/** @var PDO $pdo */

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {
    die('Invalid sales document.');
}

function printColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
        AND COLUMN_NAME = :column_name
    ");
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column
    ]);

    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
}

function printCurrentBusinessId(): int
{
    return function_exists('currentBusinessId') ? (int)currentBusinessId() : (int)($_SESSION['business_id'] ?? 0);
}

function printCurrentBranchId(): int
{
    return function_exists('currentBranchId') ? (int)currentBranchId() : (int)($_SESSION['branch_id'] ?? 0);
}

function salesPrintDocumentTypes(): array
{
    return [
        1 => ['label' => 'Quotation', 'permission_key' => 'sales_quotation', 'legacy_keys' => ['quotation']],
        2 => ['label' => 'Proforma Bill', 'permission_key' => 'sales_proforma_bill', 'legacy_keys' => ['proforma_bill']],
        3 => ['label' => 'Sales Bill', 'permission_key' => 'sales_bill', 'legacy_keys' => ['sale_order']],
        4 => ['label' => 'Direct Sale', 'permission_key' => 'sales_direct_sale', 'legacy_keys' => ['sales']],
        5 => ['label' => 'Tax Invoice', 'permission_key' => 'sales_final_invoice', 'legacy_keys' => ['sales_invoice']],
    ];
}

function salesPrintHasPermission(int $documentType, string $action): bool
{
    $types = salesPrintDocumentTypes();

    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!function_exists('hasPermission')) {
        return true;
    }

    if (!isset($types[$documentType])) {
        return false;
    }

    $keys = array_merge([$types[$documentType]['permission_key']], $types[$documentType]['legacy_keys'] ?? []);
    foreach ($keys as $key) {
        if (hasPermission($key, $action)) {
            return true;
        }
    }

    return false;
}

function money($amount): string
{
    return number_format((float)$amount, 2, '.', ',');
}

function qtyText($qty): string
{
    $qty = (float)$qty;
    return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDatePrint($date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }

    $time = strtotime($date);
    return $time ? date('d-m-Y', $time) : h($date);
}

$businessId = printCurrentBusinessId();
$branchId = printCurrentBranchId();

if ($businessId <= 0 || $branchId <= 0) {
    die('Invalid business / branch session.');
}

$zoneSelect = printColumnExists($pdo, 'customers', 'zone_id') ? 'c.zone_id' : 'NULL AS zone_id';

$stmt = $pdo->prepare("
    SELECT
        s.*,
        COALESCE(c.customer_name, 'Walk-in Customer') AS customer_name,
        c.mobile AS customer_mobile,
        c.gst_number AS customer_gst,
        c.address AS customer_address,
        c.city AS customer_city,
        c.state AS customer_state,
        c.pincode AS customer_pincode,
        $zoneSelect
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    WHERE s.id = :id
    AND s.business_id = :business_id
    AND s.branch_id = :branch_id
    LIMIT 1
");
$stmt->execute([
    ':id' => $saleId,
    ':business_id' => $businessId,
    ':branch_id' => $branchId
]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Sales document not found.');
}

$documentType = (int)$sale['document_type'];

if (!salesPrintHasPermission($documentType, 'print') && !salesPrintHasPermission($documentType, 'view')) {
    die('You do not have permission to print this document.');
}

$types = salesPrintDocumentTypes();
$documentTitle = $types[$documentType]['label'] ?? 'Sales Document';

if ((int)($sale['invoice_type'] ?? 1) === 2 && $documentType === 5) {
    $documentTitle = 'Invoice';
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

$paymentsStmt = $pdo->prepare("
    SELECT sp.*, pm.mode_name
    FROM sales_payments sp
    LEFT JOIN payment_modes pm ON pm.id = sp.payment_mode_id
    WHERE sp.sales_id = :sales_id
    AND sp.business_id = :business_id
    AND sp.branch_id = :branch_id
    AND sp.status = 1
    ORDER BY sp.id ASC
");
$paymentsStmt->execute([
    ':sales_id' => $saleId,
    ':business_id' => $businessId,
    ':branch_id' => $branchId
]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

$business = [
    'name' => 'Universal ERP',
    'address' => '',
    'mobile' => '',
    'email' => '',
    'gst_number' => '',
    'logo' => ''
];

try {
    $businessStmt = $pdo->prepare("
        SELECT *
        FROM businesses
        WHERE id = :business_id
        LIMIT 1
    ");
    $businessStmt->execute([':business_id' => $businessId]);
    $businessRow = $businessStmt->fetch(PDO::FETCH_ASSOC);

    if ($businessRow) {
        $business['name'] = $businessRow['business_name'] ?? $businessRow['company_name'] ?? $business['name'];
        $business['address'] = $businessRow['address'] ?? '';
        $business['mobile'] = $businessRow['mobile'] ?? $businessRow['phone'] ?? '';
        $business['email'] = $businessRow['email'] ?? '';
        $business['gst_number'] = $businessRow['gst_number'] ?? '';
        $business['logo'] = $businessRow['logo'] ?? $businessRow['logo_path'] ?? '';
    }
} catch (Throwable $e) {
    // Keep default business values when optional columns differ.
}

try {
    $settingsStmt = $pdo->prepare("
        SELECT *
        FROM invoice_settings
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $settingsStmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {
        $business['name'] = $settings['company_name'] ?? $settings['business_name'] ?? $business['name'];
        $business['address'] = $settings['address'] ?? $business['address'];
        $business['mobile'] = $settings['mobile'] ?? $settings['phone'] ?? $business['mobile'];
        $business['email'] = $settings['email'] ?? $business['email'];
        $business['gst_number'] = $settings['gst_number'] ?? $business['gst_number'];
        $business['logo'] = $settings['logo'] ?? $settings['logo_path'] ?? $business['logo'];
    }
} catch (Throwable $e) {
    // Invoice settings optional.
}

$customerAddressParts = array_filter([
    $sale['customer_address'] ?? '',
    $sale['customer_city'] ?? '',
    $sale['customer_state'] ?? '',
    $sale['customer_pincode'] ?? ''
]);

$page_title = $documentTitle . ' - ' . ($sale['sales_no'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        body {
            background: #f5f5f5;
            color: #111;
            font-size: 12px;
        }
        .print-toolbar {
            max-width: 210mm;
            margin: 12px auto;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .print-sheet {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto 16px;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 0 8px rgba(0,0,0,.12);
        }
        .company-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
        }
        .doc-title {
            border: 1px solid #111;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            padding: 6px;
            text-transform: uppercase;
            margin: 8px 0;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #222;
            padding: 5px;
            vertical-align: top;
        }
        .print-table th {
            background: #f1f1f1;
            font-weight: 700;
        }
        .no-border td,
        .no-border th {
            border: 0 !important;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .muted {
            color: #666;
        }
        .summary-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .summary-table td {
            border: 1px solid #222;
            padding: 5px;
        }
        .signature-box {
            height: 70px;
            border: 1px solid #222;
            display: flex;
            align-items: end;
            justify-content: center;
            padding-bottom: 8px;
            font-weight: 700;
        }
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }
            body {
                background: #fff;
            }
            .print-toolbar {
                display: none !important;
            }
            .print-sheet {
                margin: 0;
                max-width: 100%;
                min-height: auto;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
<div class="print-toolbar">
    <a href="<?= BASE_URL; ?>pages/sales.php?id=<?= (int)$saleId; ?>&mode=edit" class="btn btn-light">
        <i class="mdi mdi-arrow-left me-1"></i> Back
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print();">
        <i class="mdi mdi-printer me-1"></i> Print
    </button>
</div>

<div class="print-sheet">

    <table class="print-table no-border">
        <tr>
            <td style="width: 70%;">
                <h1 class="company-title"><?= h($business['name']); ?></h1>
                <?php if (!empty($business['address'])): ?>
                    <div><?= nl2br(h($business['address'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($business['mobile'])): ?>
                    <div>Mobile: <?= h($business['mobile']); ?></div>
                <?php endif; ?>
                <?php if (!empty($business['email'])): ?>
                    <div>Email: <?= h($business['email']); ?></div>
                <?php endif; ?>
                <?php if (!empty($business['gst_number'])): ?>
                    <div>GSTIN: <?= h($business['gst_number']); ?></div>
                <?php endif; ?>
            </td>
            <td class="text-right" style="width: 30%;">
                <?php if (!empty($business['logo'])): ?>
                    <img src="<?= h(BASE_URL . ltrim($business['logo'], '/')); ?>" style="max-width:100px; max-height:70px;" alt="Logo">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="doc-title"><?= h($documentTitle); ?></div>

    <table class="print-table">
        <tr>
            <td style="width: 50%;">
                <strong>Bill To</strong><br>
                <strong><?= h($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong><br>
                <?php if (!empty($sale['customer_mobile'])): ?>
                    Mobile: <?= h($sale['customer_mobile']); ?><br>
                <?php endif; ?>
                <?php if (!empty($sale['customer_gst'])): ?>
                    GSTIN: <?= h($sale['customer_gst']); ?><br>
                <?php endif; ?>
                <?php if ($customerAddressParts): ?>
                    <?= h(implode(', ', $customerAddressParts)); ?>
                <?php endif; ?>
            </td>
            <td style="width: 50%;">
                <table class="print-table no-border">
                    <tr>
                        <td><strong>Document No</strong></td>
                        <td>: <?= h($sale['sales_no'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date</strong></td>
                        <td>: <?= formatDatePrint($sale['sales_date'] ?? ''); ?></td>
                    </tr>
                    <?php if (!empty($sale['due_date'])): ?>
                    <tr>
                        <td><strong>Due Date</strong></td>
                        <td>: <?= formatDatePrint($sale['due_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($sale['validity_date'])): ?>
                    <tr>
                        <td><strong>Validity</strong></td>
                        <td>: <?= formatDatePrint($sale['validity_date']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Invoice Type</strong></td>
                        <td>: <?= ((int)($sale['invoice_type'] ?? 1) === 1) ? 'GST' : 'Non-GST'; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <table class="print-table">
        <thead>
        <tr>
            <th style="width: 30px;">#</th>
            <th>Product</th>
            <th>Batch</th>
            <th>HSN</th>
            <th class="text-right">Unit</th>
            <th class="text-right">Loose</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Rate</th>
            <th class="text-right">Disc</th>
            <th class="text-right">Taxable</th>
            <th class="text-right">GST</th>
            <th class="text-right">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr>
                <td colspan="12" class="text-center">No items found.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($items as $index => $item): ?>
            <?php
                $discountText = ((int)($item['discount_type'] ?? 1) === 1)
                    ? qtyText($item['discount_value'] ?? 0) . '%'
                    : '₹' . money($item['discount_value'] ?? 0);

                $gstText = qtyText($item['gst_percentage'] ?? 0) . '%';
                $taxAmount = (float)($item['cgst_amount'] ?? 0) + (float)($item['sgst_amount'] ?? 0) + (float)($item['igst_amount'] ?? 0);
            ?>
            <tr>
                <td class="text-center"><?= $index + 1; ?></td>
                <td>
                    <strong><?= h($item['product_name'] ?? ''); ?></strong>
                    <?php if (!empty($item['product_code'])): ?>
                        <br><span class="muted"><?= h($item['product_code']); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= h($item['purchase_batch_no'] ?? '-'); ?>
                    <?php if (!empty($item['purchase_bill_no'])): ?>
                        <br><span class="muted"><?= h($item['purchase_bill_no']); ?></span>
                    <?php endif; ?>
                </td>
                <td><?= h($item['hsn_code'] ?? '-'); ?></td>
                <td class="text-right"><?= qtyText($item['unit_qty'] ?? 0); ?></td>
                <td class="text-right"><?= qtyText($item['loose_qty'] ?? 0); ?></td>
                <td class="text-right"><?= qtyText($item['qty'] ?? 0); ?></td>
                <td class="text-right"><?= money($item['selling_rate'] ?? 0); ?></td>
                <td class="text-right"><?= h($discountText); ?></td>
                <td class="text-right"><?= money($item['taxable_amount'] ?? 0); ?></td>
                <td class="text-right">
                    <?= h($gstText); ?><br>
                    <span class="muted"><?= money($taxAmount); ?></span>
                </td>
                <td class="text-right"><?= money($item['line_total'] ?? 0); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <br>

    <table class="summary-table">
        <tr>
            <td>Sub Total</td>
            <td class="text-right">₹<?= money($sale['sub_total'] ?? 0); ?></td>
        </tr>
        <tr>
            <td>Discount</td>
            <td class="text-right">₹<?= money($sale['discount_amount'] ?? 0); ?></td>
        </tr>
        <tr>
            <td>Taxable Amount</td>
            <td class="text-right">₹<?= money($sale['taxable_amount'] ?? 0); ?></td>
        </tr>
        <?php if ((float)($sale['cgst_amount'] ?? 0) > 0): ?>
        <tr>
            <td>CGST</td>
            <td class="text-right">₹<?= money($sale['cgst_amount']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)($sale['sgst_amount'] ?? 0) > 0): ?>
        <tr>
            <td>SGST</td>
            <td class="text-right">₹<?= money($sale['sgst_amount']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)($sale['igst_amount'] ?? 0) > 0): ?>
        <tr>
            <td>IGST</td>
            <td class="text-right">₹<?= money($sale['igst_amount']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)($sale['shipping_charges'] ?? 0) > 0): ?>
        <tr>
            <td>Shipping</td>
            <td class="text-right">₹<?= money($sale['shipping_charges']); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)($sale['round_off'] ?? 0) != 0): ?>
        <tr>
            <td>Round Off</td>
            <td class="text-right">₹<?= money($sale['round_off']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><strong>Grand Total</strong></td>
            <td class="text-right"><strong>₹<?= money($sale['grand_total'] ?? 0); ?></strong></td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="text-right">₹<?= money($sale['paid_amount'] ?? 0); ?></td>
        </tr>
        <tr>
            <td>Due</td>
            <td class="text-right">₹<?= money($sale['due_amount'] ?? 0); ?></td>
        </tr>
    </table>

    <?php if ($payments): ?>
        <br>
        <strong>Payment Details</strong>
        <table class="print-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Mode</th>
                <th>Date</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $index => $payment): ?>
                <tr>
                    <td class="text-center"><?= $index + 1; ?></td>
                    <td><?= h($payment['mode_name'] ?? ''); ?></td>
                    <td><?= formatDatePrint($payment['payment_date'] ?? ''); ?></td>
                    <td><?= h($payment['reference_no'] ?? ''); ?></td>
                    <td class="text-right">₹<?= money($payment['payment_amount'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($sale['notes']) || !empty($sale['terms'])): ?>
        <br>
        <table class="print-table">
            <?php if (!empty($sale['notes'])): ?>
            <tr>
                <td><strong>Notes:</strong><br><?= nl2br(h($sale['notes'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($sale['terms'])): ?>
            <tr>
                <td><strong>Terms:</strong><br><?= nl2br(h($sale['terms'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <br><br>

    <table class="print-table no-border">
        <tr>
            <td style="width: 55%;">
                <strong>Declaration</strong><br>
                <span class="muted">Goods once sold will be taken back only as per company policy.</span>
            </td>
            <td style="width: 45%;">
                <div class="signature-box">Authorized Signature</div>
            </td>
        </tr>
    </table>

</div>

<script>
    window.addEventListener('load', function () {
        const autoPrint = new URLSearchParams(window.location.search).get('print');
        if (autoPrint === '1') {
            window.print();
        }
    });
</script>
</body>
</html>
