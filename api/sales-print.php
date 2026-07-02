<?php
/*
|--------------------------------------------------------------------------
| Sales FPDF Print API
|--------------------------------------------------------------------------
| URL:
| api/sales-print.php?id=SALE_ID
| api/sales-print.php?id=SALE_ID&document_type=2
|
| This file directly outputs FPDF.
| Do not call this URL by AJAX.
*/

require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

/** @var PDO $pdo */

// FPDF cannot send PDF if warning/space is already output.
if (ob_get_level() === 0) {
    ob_start();
}

$salesId = (int)($_GET['id'] ?? $_GET['sales_id'] ?? 0);
$documentTypeOverride = (int)($_GET['document_type'] ?? $_GET['documentType'] ?? 0);

if ($salesId <= 0) {
    exit('Invalid sales invoice.');
}

$businessId = function_exists('currentBusinessId') ? (int)currentBusinessId() : (int)($_SESSION['business_id'] ?? 0);
$branchId   = function_exists('currentBranchId') ? (int)currentBranchId() : (int)($_SESSION['branch_id'] ?? 0);

if ($businessId <= 0 || $branchId <= 0) {
    exit('Invalid business / branch session.');
}

function sfpColumnExists(PDO $pdo, string $table, string $column): bool
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

    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function sfpSelectColumn(PDO $pdo, string $tableAlias, string $tableName, string $columnName, string $alias, string $default = "''"): string
{
    if (sfpColumnExists($pdo, $tableName, $columnName)) {
        return $tableAlias . '.' . $columnName . ' AS ' . $alias;
    }

    return $default . ' AS ' . $alias;
}

function sfpAddress(array $parts): string
{
    $clean = [];

    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }

    return implode(', ', $clean);
}

function sfpStateText($state): string
{
    $state = trim((string)$state);

    if ($state === '') {
        return '33-Tamil Nadu';
    }

    if (stripos($state, 'tamil') !== false && strpos($state, '33') === false) {
        return '33-Tamil Nadu';
    }

    return $state;
}

function sfpDocumentTitle(int $documentType): string
{
    switch ($documentType) {
        case 1:
            return 'Quotation';
        case 2:
            return 'Proforma Bill';
        case 3:
            return 'Sales Bill';
        case 4:
            return 'Direct Sale';
        case 5:
            return 'Final Invoice';
        default:
            return 'Final Invoice';
    }
}

function sfpCanPrint(int $documentType): bool
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!function_exists('hasPermission')) {
        return true;
    }

    $types = [
        1 => ['sales_quotation', 'quotation'],
        2 => ['sales_proforma_bill', 'proforma_bill'],
        3 => ['sales_bill', 'sale_order'],
        4 => ['sales_direct_sale', 'sales'],
        5 => ['sales_final_invoice', 'sales_invoice'],
    ];

    if (!isset($types[$documentType])) {
        return false;
    }

    foreach ($types[$documentType] as $key) {
        if (hasPermission($key, 'print') || hasPermission($key, 'view')) {
            return true;
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Fetch invoice header
|--------------------------------------------------------------------------
*/
$customerEmailSelect = sfpSelectColumn($pdo, 'c', 'customers', 'email', 'customer_email');
$customerGstSelect = sfpSelectColumn($pdo, 'c', 'customers', 'gst_number', 'customer_gst_number');
$customerAddressSelect = sfpSelectColumn($pdo, 'c', 'customers', 'address', 'customer_address');
$customerCitySelect = sfpSelectColumn($pdo, 'c', 'customers', 'city', 'customer_city');
$customerStateSelect = sfpSelectColumn($pdo, 'c', 'customers', 'state', 'customer_state');
$customerPincodeSelect = sfpSelectColumn($pdo, 'c', 'customers', 'pincode', 'customer_pincode');

$businessOwnerSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'owner_name', 'owner_name');
$businessEmailSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'email', 'business_email');
$businessGstSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'gst_number', 'business_gst_number');
$businessAddressSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'address', 'business_address');
$businessCitySelect = sfpSelectColumn($pdo, 'b', 'businesses', 'city', 'business_city');
$businessStateSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'state', 'business_state');
$businessPincodeSelect = sfpSelectColumn($pdo, 'b', 'businesses', 'pincode', 'business_pincode');

$branchMobileSelect = sfpSelectColumn($pdo, 'br', 'branches', 'mobile', 'branch_mobile');
$branchEmailSelect = sfpSelectColumn($pdo, 'br', 'branches', 'email', 'branch_email');
$branchAddressSelect = sfpSelectColumn($pdo, 'br', 'branches', 'address', 'branch_address');
$branchCitySelect = sfpSelectColumn($pdo, 'br', 'branches', 'city', 'branch_city');
$branchStateSelect = sfpSelectColumn($pdo, 'br', 'branches', 'state', 'branch_state');
$branchPincodeSelect = sfpSelectColumn($pdo, 'br', 'branches', 'pincode', 'branch_pincode');

$stmt = $pdo->prepare("
    SELECT
        s.*,

        COALESCE(c.customer_name, 'Walk-in Customer') AS customer_name,
        c.mobile AS customer_mobile,
        $customerEmailSelect,
        $customerGstSelect,
        $customerAddressSelect,
        $customerCitySelect,
        $customerStateSelect,
        $customerPincodeSelect,

        b.business_name,
        b.mobile AS business_mobile,
        $businessOwnerSelect,
        $businessEmailSelect,
        $businessGstSelect,
        $businessAddressSelect,
        $businessCitySelect,
        $businessStateSelect,
        $businessPincodeSelect,

        br.branch_name,
        $branchMobileSelect,
        $branchEmailSelect,
        $branchAddressSelect,
        $branchCitySelect,
        $branchStateSelect,
        $branchPincodeSelect,

        inv.terms AS invoice_setting_terms,
        inv.footer_text,
        inv.logo_path,
        inv.signature_path

    FROM sales s
    LEFT JOIN customers c
        ON c.id = s.customer_id
       AND c.business_id = s.business_id
       AND c.branch_id = s.branch_id
    INNER JOIN businesses b
        ON b.id = s.business_id
    INNER JOIN branches br
        ON br.id = s.branch_id
       AND br.business_id = s.business_id
    LEFT JOIN invoice_settings inv
        ON inv.business_id = s.business_id
       AND inv.branch_id = s.branch_id
    WHERE s.id = :sales_id
      AND s.business_id = :business_id
      AND s.branch_id = :branch_id
      AND s.status != 3
    LIMIT 1
");

$stmt->execute([
    ':sales_id'    => $salesId,
    ':business_id' => $businessId,
    ':branch_id'   => $branchId
]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Sales invoice not found.');
}

$documentType = ($documentTypeOverride >= 1 && $documentTypeOverride <= 5)
    ? $documentTypeOverride
    : (int)($row['document_type'] ?? 5);

if (!sfpCanPrint($documentType)) {
    exit('You do not have permission to print this document.');
}

/*
|--------------------------------------------------------------------------
| Fetch invoice items
|--------------------------------------------------------------------------
| Uses only sales_items to avoid unknown product-column errors.
*/
$itemStmt = $pdo->prepare("
    SELECT *
    FROM sales_items
    WHERE sales_id = :sales_id
      AND business_id = :business_id
      AND branch_id = :branch_id
      AND status = 1
    ORDER BY id ASC
");

$itemStmt->execute([
    ':sales_id'    => $salesId,
    ':business_id' => $businessId,
    ':branch_id'   => $branchId
]);

$itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Map DB data to FPDF design variables
|--------------------------------------------------------------------------
*/
$customerFullAddress = sfpAddress([
    ($row['delivery_address'] ?? '') ?: ($row['customer_address'] ?? ''),
    $row['customer_city'] ?? '',
    $row['customer_state'] ?? '',
    $row['customer_pincode'] ?? ''
]);

$businessFullAddress = sfpAddress([
    ($row['branch_address'] ?? '') ?: ($row['business_address'] ?? ''),
    ($row['branch_city'] ?? '') ?: ($row['business_city'] ?? ''),
    ($row['branch_state'] ?? '') ?: ($row['business_state'] ?? ''),
    ($row['branch_pincode'] ?? '') ?: ($row['business_pincode'] ?? '')
]);

$companyState = sfpStateText(($row['branch_state'] ?? '') ?: ($row['business_state'] ?? ''));
$customerState = sfpStateText($row['customer_state'] ?? '');

$documentTitle = sfpDocumentTitle($documentType);

if ((int)($row['invoice_type'] ?? 1) === 2 && $documentType === 5) {
    $documentTitle = 'Invoice';
}

$invoice = [
    'id' => $row['id'],
    'invoice_number' => $row['sales_no'],
    'invoice_no' => $row['sales_no'],
    'bill_no' => $row['sales_no'],
    'sales_no' => $row['sales_no'],
    'invoice_date' => $row['sales_date'],
    'date' => $row['sales_date'],
    'created_at' => $row['created_at'] ?? '',
    'document_type' => $documentType,
    'document_title' => $documentTitle,

    'customer_name' => $row['customer_name'],
    'customer_phone' => $row['customer_mobile'],
    'customer_mobile' => $row['customer_mobile'],
    'customer_email' => $row['customer_email'],
    'customer_gst_number' => $row['customer_gst_number'],
    'customer_address' => $customerFullAddress,
    'customer_state' => $customerState,
    'place_of_supply' => $customerState,

    'shop_name' => $row['business_name'],
    'shop_address' => $businessFullAddress,
    'shop_phone' => ($row['branch_mobile'] ?? '') ?: ($row['business_mobile'] ?? ''),
    'shop_gstin' => $row['business_gst_number'],

    'sub_total' => (float)($row['sub_total'] ?? 0),
    'subtotal' => (float)($row['sub_total'] ?? 0),
    'discount_amount' => (float)($row['discount_amount'] ?? 0),
    'taxable_amount' => (float)($row['taxable_amount'] ?? 0),
    'cgst_amount' => (float)($row['cgst_amount'] ?? 0),
    'sgst_amount' => (float)($row['sgst_amount'] ?? 0),
    'igst_amount' => (float)($row['igst_amount'] ?? 0),
    'tax_amount' => (float)($row['tax_amount'] ?? 0),
    'round_off' => (float)($row['round_off'] ?? 0),
    'grand_total' => (float)($row['grand_total'] ?? 0),
    'total' => (float)($row['grand_total'] ?? 0),
    'final_amount' => (float)($row['grand_total'] ?? 0),
    'paid_amount' => (float)($row['paid_amount'] ?? 0),
    'due_amount' => (float)($row['due_amount'] ?? 0),
    'terms' => $row['terms'] ?? ''
];

$settings = [
    'company_name' => $row['business_name'],
    'business_name' => $row['business_name'],
    'shop_name' => $row['business_name'],
    'company_address' => $businessFullAddress,
    'address' => $businessFullAddress,
    'company_phone' => ($row['branch_mobile'] ?? '') ?: ($row['business_mobile'] ?? ''),
    'phone' => ($row['branch_mobile'] ?? '') ?: ($row['business_mobile'] ?? ''),
    'company_email' => ($row['branch_email'] ?? '') ?: ($row['business_email'] ?? ''),
    'gst_number' => $row['business_gst_number'],
    'gstin' => $row['business_gst_number'],
    'company_gstin' => $row['business_gst_number'],
    'state' => $companyState,
    'company_state' => $companyState,
    'invoice_terms' => ($row['terms'] ?? '') ?: (($row['invoice_setting_terms'] ?? '') ?: 'Thanks for doing business with us!'),
    'terms' => ($row['terms'] ?? '') ?: (($row['invoice_setting_terms'] ?? '') ?: 'Thanks for doing business with us!'),
    'footer_text' => $row['footer_text'] ?? '',
    'logo_path' => $row['logo_path'] ?? '',
    'signature_path' => $row['signature_path'] ?? ''
];

$invoice_settings = $settings;

$invoiceItems = [];

foreach ($itemRows as $item) {
    $qty = (float)($item['qty'] ?? 0);
    $sellingRate = (float)($item['selling_rate'] ?? $item['rate'] ?? 0);
    $unit = $item['base_unit'] ?? $item['unit_label'] ?? 'PCS';

    $invoiceItems[] = [
        'id' => $item['id'] ?? 0,
        'product_id' => $item['product_id'] ?? 0,

        'name' => $item['product_name'] ?? '',
        'item_name' => $item['product_name'] ?? '',
        'product_name' => $item['product_name'] ?? '',
        'product_name_snapshot' => $item['product_name'] ?? '',

        'hsn' => $item['hsn_code'] ?? '',
        'hsn_code' => $item['hsn_code'] ?? '',
        'item_hsn_code' => $item['hsn_code'] ?? '',

        'mrp' => $sellingRate,
        'product_mrp' => $sellingRate,

        'qty' => $qty,
        'quantity' => $qty,
        'unit' => $unit ?: 'PCS',
        'item_unit' => $unit ?: 'PCS',
        'product_unit' => $unit ?: 'PCS',

        'price' => $sellingRate,
        'unit_price' => $sellingRate,
        'rate' => $sellingRate,
        'sale_rate' => $sellingRate,

        'discount_amount' => (float)($item['discount_amount'] ?? 0),
        'discount' => (float)($item['discount_amount'] ?? 0),
        'disc' => (float)($item['discount_amount'] ?? 0),
        'discount_rate' => (float)($item['discount_value'] ?? 0),
        'discount_percent' => (float)($item['discount_value'] ?? 0),
        'disc_rate' => (float)($item['discount_value'] ?? 0),

        'gst_amount' => (float)($item['gst_amount'] ?? 0),
        'gst' => (float)($item['gst_amount'] ?? 0),
        'tax_amount' => (float)($item['gst_amount'] ?? 0),
        'total_tax' => (float)($item['gst_amount'] ?? 0),
        'tax_value' => (float)($item['gst_amount'] ?? 0),

        'gst_rate' => (float)($item['gst_percentage'] ?? 0),
        'gst_percentage' => (float)($item['gst_percentage'] ?? 0),
        'tax_rate' => (float)($item['gst_percentage'] ?? 0),
        'tax_percent' => (float)($item['gst_percentage'] ?? 0),

        'cgst_percentage' => (float)($item['cgst_percentage'] ?? 0),
        'sgst_percentage' => (float)($item['sgst_percentage'] ?? 0),
        'igst_percentage' => (float)($item['igst_percentage'] ?? 0),
        'cgst_amount' => (float)($item['cgst_amount'] ?? 0),
        'sgst_amount' => (float)($item['sgst_amount'] ?? 0),
        'igst_amount' => (float)($item['igst_amount'] ?? 0),

        'taxable_amount' => (float)($item['taxable_amount'] ?? 0),
        'taxable' => (float)($item['taxable_amount'] ?? 0),
        'taxable_value' => (float)($item['taxable_amount'] ?? 0),

        'amount' => (float)($item['line_total'] ?? 0),
        'line_total' => (float)($item['line_total'] ?? 0),
        'total_with_gst' => (float)($item['line_total'] ?? 0),
        'net_amount' => (float)($item['line_total'] ?? 0)
    ];
}

$invoice_items = $invoiceItems;
$items = $invoiceItems;

$bank_accounts = [];
$banks = [];
$accounts = [];

$printInvoiceNo = (string)($invoice['invoice_number'] ?? ('INV-' . $salesId));

$designFile = BASE_PATH . 'pages/sm-traders-tax-invoice.php';

if (!is_file($designFile)) {
    exit('FPDF design file not found: pages/sm-traders-tax-invoice.php');
}

define('INVOICE_DESIGN_LOADED', true);

require $designFile;

if (!isset($pdf) || !is_object($pdf)) {
    exit('PDF object not created by FPDF design.');
}

$safeInvoiceNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $printInvoiceNo);
if ($safeInvoiceNo === '') {
    $safeInvoiceNo = 'INV-' . $salesId;
}

$outputTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($invoice['document_title'] ?? 'Invoice'));
if ($outputTitle === '') {
    $outputTitle = 'Invoice';
}

// Clear accidental output before sending PDF.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$pdf->Output('I', $outputTitle . '_' . $safeInvoiceNo . '.pdf');
exit;
