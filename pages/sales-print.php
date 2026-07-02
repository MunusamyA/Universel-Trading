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

function colExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
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

    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function currentBizId(): int
{
    return function_exists('currentBusinessId') ? (int)currentBusinessId() : (int)($_SESSION['business_id'] ?? 0);
}

function currentBrId(): int
{
    return function_exists('currentBranchId') ? (int)currentBranchId() : (int)($_SESSION['branch_id'] ?? 0);
}

function docTypes(): array
{
    return [
        1 => ['label' => 'Quotation', 'permission_key' => 'sales_quotation', 'legacy_keys' => ['quotation']],
        2 => ['label' => 'Proforma Bill', 'permission_key' => 'sales_proforma_bill', 'legacy_keys' => ['proforma_bill']],
        3 => ['label' => 'Sales Bill', 'permission_key' => 'sales_bill', 'legacy_keys' => ['sale_order']],
        4 => ['label' => 'Direct Sale', 'permission_key' => 'sales_direct_sale', 'legacy_keys' => ['sales']],
        5 => ['label' => 'Tax Invoice', 'permission_key' => 'sales_final_invoice', 'legacy_keys' => ['sales_invoice']],
    ];
}

function canPrintDoc(int $documentType): bool
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!function_exists('hasPermission')) {
        return true;
    }

    $types = docTypes();
    if (!isset($types[$documentType])) {
        return false;
    }

    $keys = array_merge([$types[$documentType]['permission_key']], $types[$documentType]['legacy_keys'] ?? []);
    foreach ($keys as $key) {
        if (hasPermission($key, 'print') || hasPermission($key, 'view')) {
            return true;
        }
    }

    return false;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float)$value, 2, '.', ',');
}

function qtyFormat($value): string
{
    $value = (float)$value;
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

function datePrint($date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($date);
    return $ts ? date('d-m-Y', $ts) : h($date);
}

function convertNumberToWordsIndian($number): string
{
    $number = (int)round((float)$number);

    if ($number === 0) {
        return 'Zero';
    }

    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty',
        50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    ];

    $twoDigits = function ($num) use ($words) {
        $num = (int)$num;
        if ($num < 21) {
            return $words[$num];
        }
        $ten = (int)(floor($num / 10) * 10);
        $one = $num % 10;
        return trim($words[$ten] . ' ' . $words[$one]);
    };

    $threeDigits = function ($num) use ($twoDigits, $words) {
        $num = (int)$num;
        $hundred = (int)floor($num / 100);
        $rest = $num % 100;
        $text = '';

        if ($hundred > 0) {
            $text .= $words[$hundred] . ' Hundred';
        }

        if ($rest > 0) {
            $text .= ($text ? ' ' : '') . $twoDigits($rest);
        }

        return trim($text);
    };

    $parts = [];

    $crore = (int)floor($number / 10000000);
    $number %= 10000000;

    $lakh = (int)floor($number / 100000);
    $number %= 100000;

    $thousand = (int)floor($number / 1000);
    $number %= 1000;

    $hundred = $number;

    if ($crore > 0) {
        $parts[] = $threeDigits($crore) . ' Crore';
    }
    if ($lakh > 0) {
        $parts[] = $threeDigits($lakh) . ' Lakh';
    }
    if ($thousand > 0) {
        $parts[] = $threeDigits($thousand) . ' Thousand';
    }
    if ($hundred > 0) {
        $parts[] = $threeDigits($hundred);
    }

    return trim(implode(' ', $parts));
}

$businessId = currentBizId();
$branchId = currentBrId();

if ($businessId <= 0 || $branchId <= 0) {
    die('Invalid business / branch session.');
}

$customerGstColumn = colExists($pdo, 'customers', 'gst_number') ? 'c.gst_number' : "'' AS gst_number";
$customerAddressColumn = colExists($pdo, 'customers', 'address') ? 'c.address' : "'' AS address";
$customerCityColumn = colExists($pdo, 'customers', 'city') ? 'c.city' : "'' AS city";
$customerStateColumn = colExists($pdo, 'customers', 'state') ? 'c.state' : "'' AS state";
$customerPincodeColumn = colExists($pdo, 'customers', 'pincode') ? 'c.pincode' : "'' AS pincode";

$stmt = $pdo->prepare("
    SELECT
        s.*,
        COALESCE(c.customer_name, 'Walk-in Customer') AS customer_name,
        c.mobile AS customer_mobile,
        $customerGstColumn,
        $customerAddressColumn,
        $customerCityColumn,
        $customerStateColumn,
        $customerPincodeColumn
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

$documentType = (int)($sale['document_type'] ?? 0);
if (!canPrintDoc($documentType)) {
    die('You do not have permission to print this document.');
}

$docTypes = docTypes();
$documentTitle = $docTypes[$documentType]['label'] ?? 'Sales Document';

if ((int)($sale['invoice_type'] ?? 1) === 2 && $documentType === 5) {
    $documentTitle = 'Invoice';
}

$fpdfPrintUrl = BASE_URL . 'api/sales-print.php?id=' . (int)$saleId . '&document_type=' . (int)$documentType;

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

$business = [
    'name' => 'S.M TRADERS',
    'address' => '',
    'mobile' => '',
    'email' => '',
    'gst_number' => '',
    'state' => '33-Tamil Nadu',
    'bank_name' => '',
    'bank_account_no' => '',
    'bank_ifsc' => '',
    'account_holder_name' => ''
];

try {
    $businessStmt = $pdo->prepare("SELECT * FROM businesses WHERE id = :business_id LIMIT 1");
    $businessStmt->execute([':business_id' => $businessId]);
    $businessRow = $businessStmt->fetch(PDO::FETCH_ASSOC);

    if ($businessRow) {
        $business['name'] = $businessRow['business_name'] ?? $businessRow['company_name'] ?? $business['name'];
        $business['address'] = $businessRow['address'] ?? $business['address'];
        $business['mobile'] = $businessRow['mobile'] ?? $businessRow['phone'] ?? $business['mobile'];
        $business['email'] = $businessRow['email'] ?? $business['email'];
        $business['gst_number'] = $businessRow['gst_number'] ?? $business['gst_number'];
        $business['state'] = $businessRow['state'] ?? $business['state'];
    }
} catch (Throwable $e) {
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
        $business['state'] = $settings['state'] ?? $business['state'];
        $business['bank_name'] = $settings['bank_name'] ?? $business['bank_name'];
        $business['bank_account_no'] = $settings['bank_account_no'] ?? $settings['account_no'] ?? $business['bank_account_no'];
        $business['bank_ifsc'] = $settings['ifsc_code'] ?? $settings['bank_ifsc'] ?? $business['bank_ifsc'];
        $business['account_holder_name'] = $settings['account_holder_name'] ?? $business['account_holder_name'];
    }
} catch (Throwable $e) {
}

$customerAddress = trim(implode(', ', array_filter([
    $sale['address'] ?? '',
    $sale['city'] ?? '',
    $sale['pincode'] ?? ''
])));

$placeOfSupply = trim((string)($sale['state'] ?? ''));
if ($placeOfSupply === '') {
    $placeOfSupply = $business['state'];
}

$totalQty = 0;
$totalMrp = 0;
$totalDiscount = 0;
$totalTax = 0;
$totalAmount = 0;

$taxBreakup = [];

foreach ($items as $item) {
    $qty = (float)($item['qty'] ?? 0);
    $totalQty += $qty;

    $lineDiscount = (float)($item['discount_amount'] ?? 0);
    $lineTax = (float)($item['cgst_amount'] ?? 0) + (float)($item['sgst_amount'] ?? 0) + (float)($item['igst_amount'] ?? 0);
    $lineTotal = (float)($item['line_total'] ?? 0);

    $totalDiscount += $lineDiscount;
    $totalTax += $lineTax;
    $totalAmount += $lineTotal;

    $gstRate = (float)($item['gst_percentage'] ?? 0);
    $taxable = (float)($item['taxable_amount'] ?? 0);

    if ($gstRate > 0) {
        if ((float)($item['igst_amount'] ?? 0) > 0) {
            $key = 'IGST_' . $gstRate;
            if (!isset($taxBreakup[$key])) {
                $taxBreakup[$key] = ['type' => 'IGST', 'rate' => $gstRate, 'taxable' => 0, 'amount' => 0];
            }
            $taxBreakup[$key]['taxable'] += $taxable;
            $taxBreakup[$key]['amount'] += (float)$item['igst_amount'];
        } else {
            $halfRate = $gstRate / 2;
            $cgstKey = 'CGST_' . $halfRate;
            $sgstKey = 'SGST_' . $halfRate;

            if (!isset($taxBreakup[$cgstKey])) {
                $taxBreakup[$cgstKey] = ['type' => 'CGST', 'rate' => $halfRate, 'taxable' => 0, 'amount' => 0];
            }
            if (!isset($taxBreakup[$sgstKey])) {
                $taxBreakup[$sgstKey] = ['type' => 'SGST', 'rate' => $halfRate, 'taxable' => 0, 'amount' => 0];
            }

            $taxBreakup[$cgstKey]['taxable'] += $taxable;
            $taxBreakup[$cgstKey]['amount'] += (float)($item['cgst_amount'] ?? 0);
            $taxBreakup[$sgstKey]['taxable'] += $taxable;
            $taxBreakup[$sgstKey]['amount'] += (float)($item['sgst_amount'] ?? 0);
        }
    }
}

$grandTotal = (float)($sale['grand_total'] ?? $totalAmount);
$roundOff = (float)($sale['round_off'] ?? 0);
$words = convertNumberToWordsIndian($grandTotal) . ' Rupees only';

$page_title = $documentTitle . ' - ' . ($sale['sales_no'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= h($page_title); ?></title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
        }
        .toolbar {
            width: 198mm;
            margin: 0 auto 8px;
            text-align: right;
        }
        .toolbar button,
        .toolbar a {
            display: inline-block;
            border: 1px solid #777;
            background: #f2f2f2;
            color: #000;
            padding: 6px 12px;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
            margin-left: 4px;
        }
        .invoice {
            width: 198mm;
            margin: 0 auto;
            border: 1px solid #000;
        }
        .top-title {
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 0 2px;
            border-bottom: 1px solid #000;
        }
        .company {
            text-align: center;
            padding: 6px 8px 5px;
            border-bottom: 1px solid #000;
            line-height: 1.35;
        }
        .company-name {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid #000;
        }
        .box {
            padding: 5px 6px;
            min-height: 105px;
        }
        .box + .box {
            border-left: 1px solid #000;
        }
        .box-title {
            font-weight: 700;
            margin-bottom: 6px;
        }
        .right-details {
            text-align: right;
            line-height: 1.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            border: 1px solid #000;
            padding: 4px 4px;
            vertical-align: top;
        }
        th {
            font-weight: 700;
            text-align: center;
        }
        .items-table th,
        .items-table td {
            font-size: 10.5px;
        }
        .items-table th {
            background: #fff;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .bold {
            font-weight: 700;
        }
        .no-border {
            border: 0 !important;
        }
        .no-top-border {
            border-top: 0 !important;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 0;
        }
        .summary-left {
            border-right: 1px solid #000;
        }
        .summary-table td,
        .summary-table th {
            font-size: 10.5px;
            padding: 4px;
        }
        .amount-summary td {
            padding: 5px;
        }
        .words {
            border-top: 1px solid #000;
            padding: 5px 6px;
            min-height: 42px;
            text-align: center;
        }
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid #000;
            min-height: 92px;
        }
        .bottom-grid > div {
            padding: 7px 8px;
        }
        .bottom-grid > div + div {
            border-left: 1px solid #000;
        }
        .small-line {
            line-height: 1.7;
        }
        .item-name {
            font-weight: 700;
        }
        @media print {
            @page {
                size: A4;
                margin: 6mm;
            }
            body {
                padding: 0;
            }
            .toolbar {
                display: none;
            }
            .invoice {
                width: 100%;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="<?= h(BASE_URL); ?>pages/sales.php?id=<?= (int)$saleId; ?>&mode=edit">Back</a>
    <button type="button" onclick="window.open('<?= h($fpdfPrintUrl); ?>', '_blank')">Print</button>
</div>

<div class="invoice">

    <div class="top-title"><?= h($documentTitle); ?></div>

    <div class="company">
        <div class="company-name"><?= h($business['name']); ?></div>
        <?php if (!empty($business['address'])): ?>
            <div><?= h($business['address']); ?></div>
        <?php endif; ?>
        <?php if (!empty($business['mobile'])): ?>
            <div>Phone no: <?= h($business['mobile']); ?></div>
        <?php endif; ?>
        <div>
            <?php if (!empty($business['gst_number'])): ?>
                GSTIN: <?= h($business['gst_number']); ?>,
            <?php endif; ?>
            State: <?= h($business['state']); ?>
        </div>
    </div>

    <div class="two-col">
        <div class="box">
            <div class="box-title">Bill To</div>
            <div class="bold"><?= h($sale['customer_name'] ?? 'Walk-in Customer'); ?></div>
            <?php if (!empty($customerAddress)): ?>
                <div class="small-line"><?= h($customerAddress); ?></div>
            <?php endif; ?>
            <?php if (!empty($sale['customer_mobile'])): ?>
                <div class="small-line">Contact No. : <?= h($sale['customer_mobile']); ?></div>
            <?php endif; ?>
            <?php if (!empty($sale['gst_number'])): ?>
                <div class="small-line">GSTIN : <?= h($sale['gst_number']); ?></div>
            <?php endif; ?>
            <div class="small-line">State: <?= h($placeOfSupply); ?></div>
        </div>

        <div class="box right-details">
            <div class="box-title">Invoice Details</div>
            <div><?= h($documentTitle); ?> No. : <?= h($sale['sales_no'] ?? ''); ?></div>
            <div>Date : <?= datePrint($sale['sales_date'] ?? ''); ?></div>
            <div>Place of supply: <?= h($placeOfSupply); ?></div>
        </div>
    </div>

    <table class="items-table">
        <thead>
        <tr>
            <th style="width: 25px;">#</th>
            <th style="width: 160px;">Item name</th>
            <th style="width: 65px;">HSN/ SAC</th>
            <th style="width: 55px;">MRP</th>
            <th style="width: 58px;">Quantity</th>
            <th style="width: 48px;">Unit</th>
            <th style="width: 70px;">Price/ Unit</th>
            <th style="width: 72px;">Discount</th>
            <th style="width: 70px;">GST</th>
            <th style="width: 80px;">Amount</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr>
                <td colspan="10" class="text-center">No items found.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($items as $index => $item): ?>
            <?php
                $qty = (float)($item['qty'] ?? 0);
                $mrp = (float)($item['mrp'] ?? $item['selling_rate'] ?? 0);
                $price = (float)($item['selling_rate'] ?? 0);
                $lineDiscount = (float)($item['discount_amount'] ?? 0);
                $discountRate = (float)($item['discount_value'] ?? 0);
                $gstRate = (float)($item['gst_percentage'] ?? 0);
                $taxAmount = (float)($item['cgst_amount'] ?? 0) + (float)($item['sgst_amount'] ?? 0) + (float)($item['igst_amount'] ?? 0);
            ?>
            <tr>
                <td class="text-center"><?= $index + 1; ?></td>
                <td>
                    <span class="item-name"><?= h($item['product_name'] ?? ''); ?></span>
                    <?php if (!empty($item['product_code'])): ?>
                        <br><?= h($item['product_code']); ?>
                    <?php endif; ?>
                </td>
                <td><?= h($item['hsn_code'] ?? ''); ?></td>
                <td class="text-right">₹ <?= money($mrp); ?></td>
                <td class="text-right"><?= qtyFormat($qty); ?></td>
                <td class="text-center"><?= h($item['unit_name'] ?? $item['unit'] ?? 'PCS'); ?></td>
                <td class="text-right">₹ <?= money($price); ?></td>
                <td class="text-right">₹ <?= money($lineDiscount); ?> (<?= qtyFormat($discountRate); ?>%)</td>
                <td class="text-right">₹ <?= money($taxAmount); ?> (<?= qtyFormat($gstRate); ?>%)</td>
                <td class="text-right">₹ <?= money($item['line_total'] ?? 0); ?></td>
            </tr>
        <?php endforeach; ?>

        <tr>
            <td></td>
            <td class="bold">Total</td>
            <td></td>
            <td></td>
            <td class="text-right bold"><?= qtyFormat($totalQty); ?></td>
            <td></td>
            <td></td>
            <td class="text-right bold">₹ <?= money($totalDiscount); ?></td>
            <td class="text-right bold">₹ <?= money($totalTax); ?></td>
            <td class="text-right bold">₹ <?= money($grandTotal); ?></td>
        </tr>
        </tbody>
    </table>

    <div class="summary-grid">
        <div class="summary-left">
            <table class="summary-table">
                <thead>
                <tr>
                    <th>Tax type</th>
                    <th>Taxable amount</th>
                    <th>Rate</th>
                    <th>Tax amount</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$taxBreakup): ?>
                    <tr>
                        <td colspan="4" class="text-center">No tax</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($taxBreakup as $tax): ?>
                    <tr>
                        <td><?= h($tax['type']); ?></td>
                        <td class="text-right">₹ <?= money($tax['taxable']); ?></td>
                        <td class="text-right"><?= qtyFormat($tax['rate']); ?>%</td>
                        <td class="text-right">₹ <?= money($tax['amount']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="words">
                <div class="bold">Invoice Amount In Words</div>
                <div><?= h($words); ?></div>
            </div>
        </div>

        <div>
            <table class="amount-summary">
                <tr>
                    <td>Sub Total</td>
                    <td class="text-right">₹ <?= money($sale['sub_total'] ?? 0); ?></td>
                </tr>
                <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
                <tr>
                    <td>Discount</td>
                    <td class="text-right">₹ <?= money($sale['discount_amount']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ((float)($sale['shipping_charges'] ?? 0) > 0): ?>
                <tr>
                    <td>Shipping</td>
                    <td class="text-right">₹ <?= money($sale['shipping_charges']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Round off</td>
                    <td class="text-right">₹ <?= money($roundOff); ?></td>
                </tr>
                <tr>
                    <td class="bold">Total</td>
                    <td class="text-right bold">₹ <?= money($grandTotal); ?></td>
                </tr>
                <?php if ((float)($sale['paid_amount'] ?? 0) > 0): ?>
                <tr>
                    <td>Paid</td>
                    <td class="text-right">₹ <?= money($sale['paid_amount']); ?></td>
                </tr>
                <tr>
                    <td>Balance</td>
                    <td class="text-right">₹ <?= money($sale['due_amount'] ?? 0); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="bottom-grid">
        <div>
            <div class="bold">Bank Details</div>
            <?php if (!empty($business['bank_name'])): ?>
                <div class="small-line">Name : <?= h($business['bank_name']); ?></div>
            <?php endif; ?>
            <?php if (!empty($business['bank_account_no'])): ?>
                <div class="small-line">Account No. : <?= h($business['bank_account_no']); ?></div>
            <?php endif; ?>
            <?php if (!empty($business['bank_ifsc'])): ?>
                <div class="small-line">IFSC code : <?= h($business['bank_ifsc']); ?></div>
            <?php endif; ?>
            <?php if (!empty($business['account_holder_name'])): ?>
                <div class="small-line">Account holder's name : <?= h($business['account_holder_name']); ?></div>
            <?php endif; ?>
        </div>

        <div>
            <div class="bold">Terms and Conditions</div>
            <div class="small-line">
                <?= !empty($sale['terms']) ? nl2br(h($sale['terms'])) : 'Thanks for doing business with us!'; ?>
            </div>
        </div>
    </div>

</div>

<script>
    window.addEventListener('load', function () {
        const params = new URLSearchParams(window.location.search);

        /*
         * print=1 opens this preview page from Sales / Sales List.
         * Do not browser-print this HTML page automatically.
         * Print button above opens api/sales-print.php FPDF.
         */
        if (params.get('print') === '1') {
            const printButton = document.querySelector('.toolbar button');
            if (printButton) {
                printButton.focus();
            }
        }
    });
</script>
</body>
</html>
