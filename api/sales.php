<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_document_permissions':
        getSalesDocumentPermissions($pdo);
        break;
    case 'list_sales':
        listSales($pdo);
        break;
    case 'get_sale':
        getSale($pdo);
        break;
    case 'save_sale':
        verifyCsrfToken();
        saveSale($pdo);
        break;
    case 'delete_sale':
        verifyCsrfToken();
        deleteSale($pdo, false);
        break;
    case 'cancel_sale':
        verifyCsrfToken();
        deleteSale($pdo, true);
        break;
    case 'search_customers':
        searchCustomers($pdo);
        break;
    case 'search_products':
        searchProducts($pdo);
        break;
    case 'get_product_batches':
        getProductBatches($pdo);
        break;
    case 'get_payment_modes':
        getPaymentModes($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function getScope()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    return [
        'business_id' => $businessId,
        'branch_id' => $branchId
    ];
}

function currentUserIdSafe()
{
    if (function_exists('currentUserId')) {
        return (int)currentUserId();
    }

    return (int)($_SESSION['user_id'] ?? 0);
}

function requireSalesPermission($action = 'view')
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    if (function_exists('hasPermission')) {
        $businessId = function_exists('currentBusinessId') ? (int)currentBusinessId() : 0;
        $branchId = function_exists('currentBranchId') ? (int)currentBranchId() : 0;

        $moduleKeys = [
            'sales',
            'sales_' . $businessId . '_' . $branchId,
            'sales_list',
            'sales_list_' . $businessId . '_' . $branchId,
            'sales_invoice',
            'sales-bill',
            'quotation',
            'proforma_bill'
        ];

        foreach ($moduleKeys as $moduleKey) {
            if (hasPermission($moduleKey, $action)) {
                return;
            }
        }

        jsonResponse(false, 'Permission denied.');
    }
}


function salesDocumentTypes()
{
    return [
        1 => ['label' => 'Quotation', 'permission_key' => 'sales_quotation', 'legacy_keys' => ['quotation']],
        2 => ['label' => 'Proforma Bill', 'permission_key' => 'sales_proforma_bill', 'legacy_keys' => ['proforma_bill']],
        3 => ['label' => 'Sales Bill', 'permission_key' => 'sales_bill', 'legacy_keys' => ['sale_order']],
        4 => ['label' => 'Direct Sale', 'permission_key' => 'sales_direct_sale', 'legacy_keys' => ['sales']],
        5 => ['label' => 'Final Invoice', 'permission_key' => 'sales_final_invoice', 'legacy_keys' => ['sales_invoice']],
    ];
}

function hasSalesDocumentPermission($documentType, $action)
{
    $types = salesDocumentTypes();

    if (!isset($types[$documentType])) {
        return false;
    }

    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    if (!function_exists('hasPermission')) {
        return true;
    }

    $keys = array_merge([$types[$documentType]['permission_key']], $types[$documentType]['legacy_keys'] ?? []);
    foreach ($keys as $key) {
        if (hasPermission($key, $action)) {
            return true;
        }
    }

    return false;
}

function requireSalesDocumentPermission($documentType, $action, $message = 'Document type permission denied.')
{
    if (!hasSalesDocumentPermission((int)$documentType, $action)) {
        jsonResponse(false, $message);
    }
}


function salesDocumentPermissionPayload()
{
    $types = salesDocumentTypes();
    $permissions = [];
    $allowedDocumentTypes = [];

    foreach ($types as $typeId => $type) {
        $permissions[$typeId] = [
            'view' => hasSalesDocumentPermission($typeId, 'view'),
            'add' => hasSalesDocumentPermission($typeId, 'add'),
            'edit' => hasSalesDocumentPermission($typeId, 'edit'),
            'delete' => hasSalesDocumentPermission($typeId, 'delete'),
            'print' => hasSalesDocumentPermission($typeId, 'print'),
            'convert' => hasSalesDocumentPermission($typeId, 'convert'),
            'generate_invoice' => hasSalesDocumentPermission($typeId, 'generate_invoice'),
        ];

        if ($permissions[$typeId]['add']) {
            $allowedDocumentTypes[] = $typeId;
        }
    }

    return [
        'document_types' => $types,
        'allowed_document_types' => $allowedDocumentTypes,
        'permissions' => $permissions
    ];
}

function getSalesDocumentPermissions(PDO $pdo)
{
    requireSalesPermission('view');
    jsonOk('Document permissions loaded.', salesDocumentPermissionPayload());
}


function updateIfColumnExists(PDO $pdo, array $scope, $saleId, array $values)
{
    $sets = [];
    $params = [
        ':id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    foreach ($values as $column => $value) {
        if (columnExists($pdo, 'sales', $column)) {
            $param = ':' . $column;
            $sets[] = "$column = $param";
            $params[$param] = $value;
        }
    }

    if (!$sets) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE sales
        SET " . implode(', ', $sets) . "
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $stmt->execute($params);
}


function jsonOk($message, $data = [])
{
    jsonResponse(true, $message, $data);
}

function readPayload()
{
    $payload = $_POST['payload'] ?? '';
    if ($payload === '') {
        $raw = file_get_contents('php://input');
        $decodedRaw = json_decode($raw, true);
        if (is_array($decodedRaw)) {
            return $decodedRaw;
        }
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
        jsonResponse(false, 'Invalid sales payload.');
    }

    return $data;
}

function cleanDateOrNull($date)
{
    $date = trim((string)$date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

function toFloat($value, $default = 0.0)
{
    if ($value === null || $value === '') {
        return (float)$default;
    }

    return round((float)$value, 4);
}

function round2($value)
{
    return round((float)$value, 2);
}

function listSales(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $documentType = (int)($_GET['document_type'] ?? 0);
    $status = (int)($_GET['status'] ?? 0);
    $fromDate = cleanDateOrNull($_GET['from_date'] ?? '');
    $toDate = cleanDateOrNull($_GET['to_date'] ?? '');

    $where = "WHERE s.business_id = :business_id AND s.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($documentType > 0) {
        $where .= " AND s.document_type = :document_type";
        $params[':document_type'] = $documentType;
    }

    if ($status > 0) {
        $where .= " AND s.status = :status";
        $params[':status'] = $status;
    }

    if ($fromDate) {
        $where .= " AND s.sales_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND s.sales_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($search !== '') {
        $where .= " AND (s.sales_no LIKE :search_sales_no OR c.customer_name LIKE :search_customer OR c.mobile LIKE :search_mobile)";
        $params[':search_sales_no'] = '%' . $search . '%';
        $params[':search_customer'] = '%' . $search . '%';
        $params[':search_mobile'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            c.customer_name,
            c.mobile AS customer_mobile
        FROM sales s
        INNER JOIN customers c ON c.id = s.customer_id
        $where
        ORDER BY s.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);

    jsonOk('Sales loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getSale(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid sales id.');
    }

    $stmt = $pdo->prepare("
        SELECT s.*, c.customer_name, c.mobile AS customer_mobile, c.gst_number, c.address
        FROM sales s
        INNER JOIN customers c ON c.id = s.customer_id
        WHERE s.id = :id AND s.business_id = :business_id AND s.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        jsonResponse(false, 'Sale not found.');
    }

    requireSalesDocumentPermission((int)$sale['document_type'], 'view', 'You do not have permission to view this document type.');

    $itemsStmt = $pdo->prepare("
        SELECT *
        FROM sales_items
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
        ORDER BY id ASC
    ");
    $itemsStmt->execute([
        ':sales_id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $payStmt = $pdo->prepare("
        SELECT sp.*, pm.mode_name
        FROM sales_payments sp
        INNER JOIN payment_modes pm ON pm.id = sp.payment_mode_id
        WHERE sp.sales_id = :sales_id AND sp.business_id = :business_id AND sp.branch_id = :branch_id AND sp.status = 1
        ORDER BY sp.id ASC
    ");
    $payStmt->execute([
        ':sales_id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Sale loaded.', [
        'sale' => $sale,
        'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
        'payments' => $payStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function searchCustomers(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $q = cleanInput($_GET['q'] ?? '');
    $zoneId = (int)($_GET['zone_id'] ?? 0);
    $hasZoneId = columnExists($pdo, 'customers', 'zone_id');

    $where = "business_id = :business_id AND branch_id = :branch_id AND status = 1";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($q !== '') {
        $where .= " AND (customer_name LIKE :q_customer OR mobile LIKE :q_mobile OR gst_number LIKE :q_gst)";
        $params[':q_customer'] = '%' . $q . '%';
        $params[':q_mobile'] = '%' . $q . '%';
        $params[':q_gst'] = '%' . $q . '%';
    }

    if ($zoneId > 0 && $hasZoneId) {
        $where .= " AND zone_id = :zone_id";
        $params[':zone_id'] = $zoneId;
    }

    $zoneSelect = $hasZoneId ? 'zone_id' : 'NULL AS zone_id';

    $stmt = $pdo->prepare("
        SELECT id, customer_name, mobile, gst_number, address, city, state, pincode, current_outstanding, $zoneSelect
        FROM customers
        WHERE $where
        ORDER BY customer_name ASC
        LIMIT 50
    ");
    $stmt->execute($params);

    jsonOk('Customers loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function searchProducts(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $q = cleanInput($_GET['q'] ?? '');

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $where = "p.business_id = :business_id AND p.branch_id = :branch_id AND p.status = 1";

    if ($q !== '') {
        $where .= " AND (p.product_code LIKE :q_code OR p.product_name LIKE :q_name)";
        $params[':q_code'] = '%' . $q . '%';
        $params[':q_name'] = '%' . $q . '%';
    }

    $wholesaleMarkupTypeSelect = columnExists($pdo, 'products', 'wholesale_markup_type')
        ? 'p.wholesale_markup_type'
        : 'NULL AS wholesale_markup_type';

    $wholesaleMarkupValueSelect = columnExists($pdo, 'products', 'wholesale_markup_value')
        ? 'p.wholesale_markup_value'
        : 'NULL AS wholesale_markup_value';

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.product_code,
            p.product_name,
            p.retail_price,
            p.wholesale_price,
            p.retail_markup_type,
            p.retail_markup_value,
            $wholesaleMarkupTypeSelect,
            $wholesaleMarkupValueSelect,
            p.base_unit,
            p.box_label,
            p.secondary_unit_label,
            p.secondary_unit_value,
            p.gst_type,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage,
            COALESCE(SUM(pi.available_qty), 0) AS available_qty
        FROM products p
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        LEFT JOIN purchase_items pi ON pi.product_id = p.id
            AND pi.business_id = p.business_id
            AND pi.branch_id = p.branch_id
            AND pi.available_qty > 0
            AND pi.status = 1
        WHERE $where
        GROUP BY p.id
        HAVING available_qty > 0
        ORDER BY p.product_name ASC
        LIMIT 20
    ");
    $stmt->execute($params);

    jsonOk('Products loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getProductBatches(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($productId <= 0) {
        jsonResponse(false, 'Invalid product.');
    }

    $wholesaleMarkupTypeSelect = columnExists($pdo, 'products', 'wholesale_markup_type')
        ? 'pr.wholesale_markup_type'
        : 'NULL AS wholesale_markup_type';

    $wholesaleMarkupValueSelect = columnExists($pdo, 'products', 'wholesale_markup_value')
        ? 'pr.wholesale_markup_value'
        : 'NULL AS wholesale_markup_value';

    $stmt = $pdo->prepare("
        SELECT 
            pi.id AS purchase_item_id,
            pi.purchase_id,
            pi.product_id,
            pi.product_code,
            pi.product_name,
            pi.available_qty,
            pi.purchase_price,
            pi.unit_conversion,
            pi.unit_label,
            pi.base_unit,
            pi.box_label,
            pi.expiry_date,
            pi.hsn_id,
            pi.hsn_code,
            pi.cgst_percentage,
            pi.sgst_percentage,
            pi.igst_percentage,
            p.batch_no,
            p.bill_no,
            p.purchase_date,
            pr.retail_price,
            pr.wholesale_price,
            pr.retail_markup_type,
            pr.retail_markup_value,
            $wholesaleMarkupTypeSelect,
            $wholesaleMarkupValueSelect
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        INNER JOIN products pr ON pr.id = pi.product_id
        WHERE pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.product_id = :product_id
        AND pi.available_qty > 0
        AND pi.status = 1
        ORDER BY p.purchase_date ASC, pi.id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId
    ]);

    jsonOk('Batches loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}


function columnExists(PDO $pdo, $tableName, $columnName)
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;

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
        ':table_name' => $tableName,
        ':column_name' => $columnName
    ]);

    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function ensureDefaultPaymentModes(PDO $pdo, array $scope)
{
    $defaultModes = ['Cash', 'UPI', 'Bank', 'Cheque'];

    foreach ($defaultModes as $modeName) {
        $check = $pdo->prepare("
            SELECT id
            FROM payment_modes
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND LOWER(mode_name) = LOWER(:mode_name)
            LIMIT 1
        ");
        $check->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':mode_name' => $modeName
        ]);

        if (!$check->fetchColumn()) {
            $insert = $pdo->prepare("
                INSERT INTO payment_modes
                (business_id, branch_id, mode_name, status)
                VALUES
                (:business_id, :branch_id, :mode_name, 1)
            ");
            $insert->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':mode_name' => $modeName
            ]);
        }
    }
}

function getPaymentModes(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();

    ensureDefaultPaymentModes($pdo, $scope);

    $stmt = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        AND LOWER(mode_name) IN ('cash', 'upi', 'bank', 'cheque')
        ORDER BY FIELD(LOWER(mode_name), 'cash', 'upi', 'bank', 'cheque')
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Payment modes loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function saveSale(PDO $pdo)
{
    $data = readPayload();
    $documentType = (int)($data['document_type'] ?? 0);
    $mode = cleanInput($data['mode'] ?? 'new');
    $sourceSaleId = (int)($data['source_id'] ?? 0);
    $sourceType = (int)($data['source_type'] ?? 0);
    $targetType = (int)($data['target_type'] ?? 0);

    $scope = getScope();
    $userId = currentUserIdSafe();

    $saleId = (int)($data['id'] ?? 0);

    if (!isset(salesDocumentTypes()[$documentType])) {
        jsonResponse(false, 'Invalid document type.');
    }

    if ($mode === 'convert') {
        if ($sourceSaleId <= 0 || $sourceType <= 0 || $targetType <= 0) {
            jsonResponse(false, 'Invalid conversion request.');
        }

        $documentType = $targetType;
        $saleId = 0;

        requireSalesDocumentPermission($sourceType, 'convert', 'You do not have permission to convert the source document.');
        requireSalesDocumentPermission($targetType, 'add', 'You do not have permission to generate the target document.');

        if ($targetType === 5) {
            requireSalesDocumentPermission($sourceType, 'generate_invoice', 'You do not have permission to generate final invoice from this document.');
        }
    }

    requireSalesPermission('view');

    $customerId = (int)($data['customer_id'] ?? 0);
    $invoiceType = (int)($data['invoice_type'] ?? 1);
    $salesDate = cleanDateOrNull($data['sales_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
    $validityDate = cleanDateOrNull($data['validity_date'] ?? '');
    $dueDate = cleanDateOrNull($data['due_date'] ?? '');
    $deliveryAddress = cleanInput($data['delivery_address'] ?? '');
    $shippingCharges = round2($data['shipping_charges'] ?? 0);
    $notes = cleanInput($data['notes'] ?? '');
    $terms = cleanInput($data['terms'] ?? '');
    $roundOff = round2($data['round_off'] ?? 0);
    $headerDiscountType = (int)($data['discount_type'] ?? 1);
    $headerDiscountValue = round2($data['discount_value'] ?? 0);
    $items = $data['items'] ?? [];
    $payments = $data['payments'] ?? [];

    if ($customerId <= 0) {
        jsonResponse(false, 'Select customer.');
    }

    if (!in_array($documentType, [1,2,3,4,5], true)) {
        jsonResponse(false, 'Invalid document type.');
    }

    if (!in_array($invoiceType, [1,2], true)) {
        jsonResponse(false, 'Invalid invoice type.');
    }

    if (!is_array($items) || count($items) === 0) {
        jsonResponse(false, 'Add at least one product.');
    }

    if ($mode !== 'convert' && $saleId <= 0) {
        requireSalesDocumentPermission($documentType, 'add', 'You do not have permission to create this document type.');
    }

    $shouldDeductStock = in_array($documentType, [4,5], true);

    try {
        $pdo->beginTransaction();

        if ($saleId > 0) {
            $oldSale = getSaleForUpdate($pdo, $scope, $saleId);
            if (!$oldSale) {
                throw new Exception('Sale not found.');
            }

            // Existing document type is locked during edit to avoid stock/invoice confusion.
            $documentType = (int)$oldSale['document_type'];
            requireSalesDocumentPermission($documentType, 'edit', 'You do not have permission to edit this document type.');

            if ((int)$oldSale['stock_deducted'] === 1) {
                requireSalesPermission('adjust');
                reverseSaleStock($pdo, $scope, $saleId);
            }

            markOldSaleRowsInactive($pdo, $scope, $saleId);
            markOldPaymentsInactive($pdo, $scope, $saleId);
            $salesNo = $oldSale['sales_no'];
        } else {
            $salesNo = reserveDocumentNumber($pdo, $scope, $documentType, $invoiceType, 0);
        }

        $computedItems = computeItems($pdo, $scope, $items, $invoiceType, $shouldDeductStock);

        $subTotal = array_sum(array_column($computedItems, 'gross_amount'));
        $itemDiscount = array_sum(array_column($computedItems, 'discount_amount'));
        $itemTaxable = array_sum(array_column($computedItems, 'taxable_amount'));
        $cgstAmount = array_sum(array_column($computedItems, 'cgst_amount'));
        $sgstAmount = array_sum(array_column($computedItems, 'sgst_amount'));
        $igstAmount = array_sum(array_column($computedItems, 'igst_amount'));
        $taxAmount = $cgstAmount + $sgstAmount + $igstAmount;

        $headerDiscountAmount = 0.0;
        if ($headerDiscountValue > 0) {
            $headerDiscountAmount = $headerDiscountType === 1
                ? round2($itemTaxable * $headerDiscountValue / 100)
                : $headerDiscountValue;
        }

        $grandTotal = round2($itemTaxable + $taxAmount - $headerDiscountAmount + $roundOff + $shippingCharges);
        if ($grandTotal < 0) {
            $grandTotal = 0;
        }

        $computedPayments = computePayments($payments, $grandTotal);
        $paidAmount = array_sum(array_column($computedPayments, 'payment_amount'));
        $dueAmount = round2($grandTotal - $paidAmount);
        $paymentStatus = $paidAmount <= 0 ? 0 : ($dueAmount > 0 ? 1 : 2);
        $finalStatus = in_array($documentType, [4,5], true) ? 2 : 1;

        if ($saleId > 0) {
            $stmt = $pdo->prepare("
                UPDATE sales SET
                    customer_id = :customer_id,
                    document_type = :document_type,
                    invoice_type = :invoice_type,
                    sales_date = :sales_date,
                    validity_date = :validity_date,
                    sub_total = :sub_total,
                    discount_type = :discount_type,
                    discount_value = :discount_value,
                    discount_amount = :discount_amount,
                    taxable_amount = :taxable_amount,
                    cgst_amount = :cgst_amount,
                    sgst_amount = :sgst_amount,
                    igst_amount = :igst_amount,
                    tax_amount = :tax_amount,
                    round_off = :round_off,
                    grand_total = :grand_total,
                    paid_amount = :paid_amount,
                    due_amount = :due_amount,
                    payment_status = :payment_status,
                    stock_deducted = :stock_deducted,
                    notes = :notes,
                    terms = :terms,
                    status = :status,
                    updated_by = :updated_by
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':customer_id' => $customerId,
                ':document_type' => $documentType,
                ':invoice_type' => $invoiceType,
                ':sales_date' => $salesDate,
                ':validity_date' => $validityDate,
                ':sub_total' => round2($subTotal),
                ':discount_type' => $headerDiscountType,
                ':discount_value' => $headerDiscountValue,
                ':discount_amount' => round2($headerDiscountAmount + $itemDiscount),
                ':taxable_amount' => round2($itemTaxable),
                ':cgst_amount' => round2($cgstAmount),
                ':sgst_amount' => round2($sgstAmount),
                ':igst_amount' => round2($igstAmount),
                ':tax_amount' => round2($taxAmount),
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => round2($paidAmount),
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':stock_deducted' => $shouldDeductStock ? 1 : 0,
                ':notes' => $notes,
                ':terms' => $terms,
                ':status' => $finalStatus,
                ':updated_by' => $userId,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sales
                (
                    business_id, branch_id, customer_id,
                    document_type, invoice_type, sales_no, sales_date, validity_date,
                    sub_total, discount_type, discount_value, discount_amount,
                    taxable_amount, cgst_amount, sgst_amount, igst_amount, tax_amount,
                    round_off, grand_total, paid_amount, due_amount, payment_status,
                    stock_deducted, notes, terms, status, created_by
                )
                VALUES
                (
                    :business_id, :branch_id, :customer_id,
                    :document_type, :invoice_type, :sales_no, :sales_date, :validity_date,
                    :sub_total, :discount_type, :discount_value, :discount_amount,
                    :taxable_amount, :cgst_amount, :sgst_amount, :igst_amount, :tax_amount,
                    :round_off, :grand_total, :paid_amount, :due_amount, :payment_status,
                    :stock_deducted, :notes, :terms, :status, :created_by
                )
            ");
            $stmt->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':customer_id' => $customerId,
                ':document_type' => $documentType,
                ':invoice_type' => $invoiceType,
                ':sales_no' => $salesNo,
                ':sales_date' => $salesDate,
                ':validity_date' => $validityDate,
                ':sub_total' => round2($subTotal),
                ':discount_type' => $headerDiscountType,
                ':discount_value' => $headerDiscountValue,
                ':discount_amount' => round2($headerDiscountAmount + $itemDiscount),
                ':taxable_amount' => round2($itemTaxable),
                ':cgst_amount' => round2($cgstAmount),
                ':sgst_amount' => round2($sgstAmount),
                ':igst_amount' => round2($igstAmount),
                ':tax_amount' => round2($taxAmount),
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => round2($paidAmount),
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':stock_deducted' => $shouldDeductStock ? 1 : 0,
                ':notes' => $notes,
                ':terms' => $terms,
                ':status' => $finalStatus,
                ':created_by' => $userId
            ]);
            $saleId = (int)$pdo->lastInsertId();

            attachDocumentNumberToSale($pdo, $scope, $documentType, $invoiceType, $salesNo, $saleId);

            if ($mode === 'convert') {
                updateIfColumnExists($pdo, $scope, $saleId, [
                    'source_sale_id' => $sourceSaleId,
                    'source_document_type' => $sourceType,
                    'conversion_status' => 0
                ]);

                updateIfColumnExists($pdo, $scope, $sourceSaleId, [
                    'converted_to_sale_id' => $saleId,
                    'converted_to_document_type' => $documentType,
                    'conversion_status' => 1
                ]);
            }
        }

        if (columnExists($pdo, 'sales', 'due_date')) {
            $dueStmt = $pdo->prepare("
                UPDATE sales
                SET due_date = :due_date
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $dueStmt->execute([
                ':due_date' => $dueDate,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'sales', 'delivery_address')) {
            $deliveryStmt = $pdo->prepare("
                UPDATE sales
                SET delivery_address = :delivery_address
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $deliveryStmt->execute([
                ':delivery_address' => $deliveryAddress,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'sales', 'shipping_charges')) {
            $shippingStmt = $pdo->prepare("
                UPDATE sales
                SET shipping_charges = :shipping_charges
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $shippingStmt->execute([
                ':shipping_charges' => $shippingCharges,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        foreach ($computedItems as $item) {
            insertSaleItem($pdo, $scope, $saleId, $item);

            if ($shouldDeductStock) {
                deductBatchStock($pdo, $scope, (int)$item['purchase_item_id'], (float)$item['qty']);
            }
        }

        foreach ($computedPayments as $payment) {
            insertSalePayment($pdo, $scope, $saleId, $customerId, $payment, $userId);
        }

        updateCustomerOutstanding($pdo, $scope, $customerId);

        addActivityLog($pdo, $scope, $userId, 'sales', $saleId > 0 ? 'SAVE' : 'CREATE', 'Sales ' . $salesNo . ' saved.', $saleId, $salesNo);

        $pdo->commit();

        jsonOk('Sale saved successfully.', [
            'id' => $saleId,
            'sales_no' => $salesNo,
            'grand_total' => $grandTotal,
            'paid_amount' => round2($paidAmount),
            'due_amount' => $dueAmount
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function getSaleForUpdate(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM sales
        WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function markOldSaleRowsInactive(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        UPDATE sales_items
        SET status = 2
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function markOldPaymentsInactive(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        UPDATE sales_payments
        SET status = 2
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function computeItems(PDO $pdo, array $scope, array $items, $invoiceType, $shouldDeductStock)
{
    $computed = [];

    foreach ($items as $index => $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $purchaseItemId = (int)($row['purchase_item_id'] ?? 0);

        if ($productId <= 0 || $purchaseItemId <= 0) {
            throw new Exception('Invalid product or batch in row ' . ($index + 1));
        }

        $unitQty = toFloat($row['unit_qty'] ?? 0);
        $qtyPerUnit = toFloat($row['qty_per_unit'] ?? 1);
        $looseQty = toFloat($row['loose_qty'] ?? 0);
        $qty = round($unitQty * $qtyPerUnit + $looseQty, 4);

        if ($qty <= 0) {
            throw new Exception('Total quantity must be greater than zero in row ' . ($index + 1));
        }

        $batch = getBatchForSale($pdo, $scope, $purchaseItemId, true);

        if (!$batch || (int)$batch['product_id'] !== $productId) {
            throw new Exception('Selected batch does not match product in row ' . ($index + 1));
        }

        if ($shouldDeductStock && (float)$batch['available_qty'] < $qty) {
            throw new Exception('Insufficient stock for ' . $batch['product_name'] . '. Available: ' . $batch['available_qty']);
        }

        $priceType = (int)($row['price_type'] ?? 1);
        $manualRate = toFloat($row['selling_rate'] ?? 0);
        $baseRate = 0.0;

        if ($priceType === 2) {
            $baseRate = toFloat($batch['wholesale_price'] ?? 0);
        } elseif ($priceType === 3) {
            $baseRate = $manualRate;
        } else {
            $priceType = 1;
            $baseRate = toFloat($batch['retail_price'] ?? 0);
        }

        if ($baseRate <= 0) {
            $baseRate = toFloat($batch['purchase_price'] ?? 0);
        }

        $markupType = (int)($row['markup_type'] ?? 1);
        $markupValue = toFloat($row['markup_value'] ?? 0);

        $sellingRate = $baseRate;
        if ($markupValue > 0) {
            if ($markupType === 1) {
                $sellingRate += ($baseRate * $markupValue / 100);
            } else {
                $markupType = 2;
                $sellingRate += $markupValue;
            }
        }

        if ($priceType === 3 && $manualRate > 0) {
            $sellingRate = $manualRate;
        }

        $sellingRate = round2($sellingRate);
        $grossAmount = round2($qty * $sellingRate);

        $discountType = (int)($row['discount_type'] ?? 1);
        $discountValue = toFloat($row['discount_value'] ?? 0);
        $discountAmount = 0.0;

        if ($discountValue > 0) {
            $discountAmount = $discountType === 1
                ? round2($grossAmount * $discountValue / 100)
                : round2($discountValue);
        }

        if ($discountAmount > $grossAmount) {
            $discountAmount = $grossAmount;
        }

        $taxableAmount = round2($grossAmount - $discountAmount);
        $gstPercentage = $invoiceType === 1 ? toFloat($row['gst_percentage'] ?? (($batch['cgst_percentage'] + $batch['sgst_percentage'] + $batch['igst_percentage']))) : 0.0;

        $cgstPercentage = 0.0;
        $sgstPercentage = 0.0;
        $igstPercentage = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $gstAmount = 0.0;

        if ($invoiceType === 1 && $gstPercentage > 0) {
            $cgstPercentage = toFloat($batch['cgst_percentage'] ?? 0);
            $sgstPercentage = toFloat($batch['sgst_percentage'] ?? 0);
            $igstPercentage = toFloat($batch['igst_percentage'] ?? 0);

            if ($igstPercentage > 0) {
                $igstAmount = round2($taxableAmount * $igstPercentage / 100);
            } else {
                if ($cgstPercentage <= 0 && $sgstPercentage <= 0) {
                    $cgstPercentage = round($gstPercentage / 2, 2);
                    $sgstPercentage = round($gstPercentage / 2, 2);
                }
                $cgstAmount = round2($taxableAmount * $cgstPercentage / 100);
                $sgstAmount = round2($taxableAmount * $sgstPercentage / 100);
            }

            $gstAmount = round2($cgstAmount + $sgstAmount + $igstAmount);
        }

        $lineTotal = round2($taxableAmount + $gstAmount);

        $computed[] = [
            'product_id' => $productId,
            'purchase_id' => (int)$batch['purchase_id'],
            'purchase_item_id' => $purchaseItemId,
            'purchase_batch_no' => $batch['batch_no'],
            'purchase_bill_no' => $batch['bill_no'],
            'purchase_date' => $batch['purchase_date'],
            'purchase_price' => round2($batch['purchase_price']),
            'product_code' => $batch['product_code'],
            'product_name' => $batch['product_name'],
            'hsn_id' => $batch['hsn_id'] > 0 ? (int)$batch['hsn_id'] : null,
            'hsn_code' => $batch['hsn_code'],
            'unit_qty' => $unitQty,
            'qty_per_unit' => $qtyPerUnit,
            'loose_qty' => $looseQty,
            'qty' => $qty,
            'price_type' => $priceType,
            'base_rate' => round2($baseRate),
            'markup_type' => $markupType,
            'markup_value' => $markupValue,
            'selling_rate' => $sellingRate,
            'gross_amount' => $grossAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'taxable_per_piece' => $qty > 0 ? round($taxableAmount / $qty, 4) : 0,
            'gst_percentage' => $gstPercentage,
            'cgst_percentage' => $cgstPercentage,
            'sgst_percentage' => $sgstPercentage,
            'igst_percentage' => $igstPercentage,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'gst_amount' => $gstAmount,
            'net_per_piece' => $qty > 0 ? round($lineTotal / $qty, 4) : 0,
            'line_total' => $lineTotal,
            'expiry_date' => $batch['expiry_date']
        ];
    }

    return $computed;
}

function getBatchForSale(PDO $pdo, array $scope, $purchaseItemId, $lock = false)
{
    $sql = "
        SELECT 
            pi.*,
            p.batch_no,
            p.bill_no,
            p.purchase_date,
            pr.retail_price,
            pr.wholesale_price,
            pr.retail_markup_type,
            pr.retail_markup_value,
            h.hsn_code AS product_hsn_code,
            h.cgst_percentage AS product_cgst_percentage,
            h.sgst_percentage AS product_sgst_percentage,
            h.igst_percentage AS product_igst_percentage
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        INNER JOIN products pr ON pr.id = pi.product_id
        LEFT JOIN hsn_codes h ON h.id = pr.hsn_id
        WHERE pi.id = :purchase_item_id
        AND pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.status = 1
        " . ($lock ? "FOR UPDATE" : "");

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':purchase_item_id' => $purchaseItemId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        if (empty($batch['hsn_code'])) {
            $batch['hsn_code'] = $batch['product_hsn_code'] ?? '';
        }
        if ((float)$batch['cgst_percentage'] <= 0) {
            $batch['cgst_percentage'] = $batch['product_cgst_percentage'] ?? 0;
        }
        if ((float)$batch['sgst_percentage'] <= 0) {
            $batch['sgst_percentage'] = $batch['product_sgst_percentage'] ?? 0;
        }
        if ((float)$batch['igst_percentage'] <= 0) {
            $batch['igst_percentage'] = $batch['product_igst_percentage'] ?? 0;
        }
    }

    return $batch;
}

function insertSaleItem(PDO $pdo, array $scope, $saleId, array $item)
{
    $stmt = $pdo->prepare("
        INSERT INTO sales_items
        (
            business_id, branch_id, sales_id,
            product_id, purchase_id, purchase_item_id,
            purchase_batch_no, purchase_bill_no, purchase_date, purchase_price,
            product_code, product_name, hsn_id, hsn_code,
            unit_qty, qty_per_unit, loose_qty, qty,
            price_type, base_rate, markup_type, markup_value, selling_rate,
            gross_amount, discount_type, discount_value, discount_amount,
            taxable_amount, taxable_per_piece,
            gst_percentage, cgst_percentage, sgst_percentage, igst_percentage,
            cgst_amount, sgst_amount, igst_amount, gst_amount,
            net_per_piece, line_total, expiry_date, status
        )
        VALUES
        (
            :business_id, :branch_id, :sales_id,
            :product_id, :purchase_id, :purchase_item_id,
            :purchase_batch_no, :purchase_bill_no, :purchase_date, :purchase_price,
            :product_code, :product_name, :hsn_id, :hsn_code,
            :unit_qty, :qty_per_unit, :loose_qty, :qty,
            :price_type, :base_rate, :markup_type, :markup_value, :selling_rate,
            :gross_amount, :discount_type, :discount_value, :discount_amount,
            :taxable_amount, :taxable_per_piece,
            :gst_percentage, :cgst_percentage, :sgst_percentage, :igst_percentage,
            :cgst_amount, :sgst_amount, :igst_amount, :gst_amount,
            :net_per_piece, :line_total, :expiry_date, 1
        )
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId,
        ':product_id' => $item['product_id'],
        ':purchase_id' => $item['purchase_id'],
        ':purchase_item_id' => $item['purchase_item_id'],
        ':purchase_batch_no' => $item['purchase_batch_no'],
        ':purchase_bill_no' => $item['purchase_bill_no'],
        ':purchase_date' => $item['purchase_date'],
        ':purchase_price' => $item['purchase_price'],
        ':product_code' => $item['product_code'],
        ':product_name' => $item['product_name'],
        ':hsn_id' => $item['hsn_id'],
        ':hsn_code' => $item['hsn_code'],
        ':unit_qty' => $item['unit_qty'],
        ':qty_per_unit' => $item['qty_per_unit'],
        ':loose_qty' => $item['loose_qty'],
        ':qty' => $item['qty'],
        ':price_type' => $item['price_type'],
        ':base_rate' => $item['base_rate'],
        ':markup_type' => $item['markup_type'],
        ':markup_value' => $item['markup_value'],
        ':selling_rate' => $item['selling_rate'],
        ':gross_amount' => $item['gross_amount'],
        ':discount_type' => $item['discount_type'],
        ':discount_value' => $item['discount_value'],
        ':discount_amount' => $item['discount_amount'],
        ':taxable_amount' => $item['taxable_amount'],
        ':taxable_per_piece' => $item['taxable_per_piece'],
        ':gst_percentage' => $item['gst_percentage'],
        ':cgst_percentage' => $item['cgst_percentage'],
        ':sgst_percentage' => $item['sgst_percentage'],
        ':igst_percentage' => $item['igst_percentage'],
        ':cgst_amount' => $item['cgst_amount'],
        ':sgst_amount' => $item['sgst_amount'],
        ':igst_amount' => $item['igst_amount'],
        ':gst_amount' => $item['gst_amount'],
        ':net_per_piece' => $item['net_per_piece'],
        ':line_total' => $item['line_total'],
        ':expiry_date' => $item['expiry_date']
    ]);
}

function deductBatchStock(PDO $pdo, array $scope, $purchaseItemId, $qty)
{
    $stmt = $pdo->prepare("
        UPDATE purchase_items
        SET 
            sold_qty = sold_qty + :sold_qty,
            available_qty = available_qty - :available_qty
        WHERE id = :purchase_item_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND available_qty >= :qty_check
    ");
    $stmt->execute([
        ':sold_qty' => $qty,
        ':available_qty' => $qty,
        ':purchase_item_id' => $purchaseItemId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':qty_check' => $qty
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new Exception('Stock deduction failed. Please check available quantity.');
    }
}

function reverseSaleStock(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        SELECT purchase_item_id, qty
        FROM sales_items
        WHERE sales_id = :sales_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        AND purchase_item_id IS NOT NULL
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $qty = (float)$item['qty'];
        if ($qty <= 0) {
            continue;
        }

        $update = $pdo->prepare("
            UPDATE purchase_items
            SET 
                sold_qty = GREATEST(sold_qty - :sold_qty, 0),
                available_qty = available_qty + :available_qty
            WHERE id = :purchase_item_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $update->execute([
            ':sold_qty' => $qty,
            ':available_qty' => $qty,
            ':purchase_item_id' => (int)$item['purchase_item_id'],
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    }

    $flag = $pdo->prepare("
        UPDATE sales
        SET stock_deducted = 0
        WHERE id = :sales_id AND business_id = :business_id AND branch_id = :branch_id
    ");
    $flag->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function computePayments(array $payments, $grandTotal)
{
    $computed = [];

    foreach ($payments as $row) {
        $modeId = (int)($row['payment_mode_id'] ?? 0);
        $amount = round2($row['payment_amount'] ?? 0);

        if ($modeId <= 0 || $amount <= 0) {
            continue;
        }

        $computed[] = [
            'payment_mode_id' => $modeId,
            'payment_date' => cleanDateOrNull($row['payment_date'] ?? date('Y-m-d')) ?: date('Y-m-d'),
            'payment_amount' => $amount,
            'reference_no' => cleanInput($row['reference_no'] ?? ''),
            'notes' => cleanInput($row['notes'] ?? '')
        ];
    }

    $paid = array_sum(array_column($computed, 'payment_amount'));
    if ($paid - $grandTotal > 0.01) {
        throw new Exception('Paid amount cannot be greater than grand total.');
    }

    return $computed;
}

function insertSalePayment(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    $stmt = $pdo->prepare("
        INSERT INTO sales_payments
        (
            business_id, branch_id, sales_id, customer_id,
            payment_mode_id, payment_date, payment_amount,
            reference_no, notes, status, received_by
        )
        VALUES
        (
            :business_id, :branch_id, :sales_id, :customer_id,
            :payment_mode_id, :payment_date, :payment_amount,
            :reference_no, :notes, 1, :received_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId,
        ':customer_id' => $customerId,
        ':payment_mode_id' => $payment['payment_mode_id'],
        ':payment_date' => $payment['payment_date'],
        ':payment_amount' => $payment['payment_amount'],
        ':reference_no' => $payment['reference_no'],
        ':notes' => $payment['notes'],
        ':received_by' => $userId
    ]);
}

function reserveDocumentNumber(PDO $pdo, array $scope, $documentType, $invoiceType, $saleId)
{
    $prefix = getDocumentPrefix($pdo, $scope, $documentType);

    $reusable = $pdo->prepare("
        SELECT id, document_no, number_value
        FROM invoice_number_register
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        AND status = 2
        ORDER BY number_value ASC
        LIMIT 1
        FOR UPDATE
    ");
    $reusable->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType
    ]);
    $row = $reusable->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare("
            UPDATE invoice_number_register
            SET status = 1, sales_id = :sales_id, deleted_sales_id = NULL, deleted_at = NULL
            WHERE id = :id
        ");
        $upd->execute([
            ':sales_id' => $saleId > 0 ? $saleId : null,
            ':id' => $row['id']
        ]);

        return $row['document_no'];
    }

    $maxStmt = $pdo->prepare("
        SELECT COALESCE(MAX(number_value), 0) + 1 AS next_no
        FROM invoice_number_register
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        FOR UPDATE
    ");
    $maxStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType
    ]);

    $nextNo = (int)$maxStmt->fetchColumn();
    if ($nextNo <= 0) {
        $nextNo = 1;
    }

    $documentNo = $prefix . '-' . str_pad((string)$nextNo, 3, '0', STR_PAD_LEFT);

    $ins = $pdo->prepare("
        INSERT INTO invoice_number_register
        (
            business_id, branch_id, document_type, invoice_type,
            prefix, number_value, document_no, sales_id, status
        )
        VALUES
        (
            :business_id, :branch_id, :document_type, :invoice_type,
            :prefix, :number_value, :document_no, :sales_id, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType,
        ':prefix' => $prefix,
        ':number_value' => $nextNo,
        ':document_no' => $documentNo,
        ':sales_id' => $saleId > 0 ? $saleId : null
    ]);

    return $documentNo;
}

function attachDocumentNumberToSale(PDO $pdo, array $scope, $documentType, $invoiceType, $documentNo, $saleId)
{
    $stmt = $pdo->prepare("
        UPDATE invoice_number_register
        SET sales_id = :sales_id, status = 1
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        AND document_no = :document_no
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType,
        ':document_no' => $documentNo
    ]);
}

function getDocumentPrefix(PDO $pdo, array $scope, $documentType)
{
    $defaults = [
        1 => 'QUO',
        2 => 'PRO',
        3 => 'SB',
        4 => 'DS',
        5 => 'INV'
    ];

    $fieldMap = [
        1 => 'quotation_prefix',
        2 => 'proforma_prefix',
        3 => 'sale_order_prefix',
        4 => 'invoice_prefix',
        5 => 'invoice_prefix'
    ];

    $field = $fieldMap[$documentType] ?? 'invoice_prefix';

    try {
        $stmt = $pdo->prepare("
            SELECT $field
            FROM invoice_settings
            WHERE business_id = :business_id AND branch_id = :branch_id
            LIMIT 1
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $prefix = trim((string)$stmt->fetchColumn());

        if ($prefix !== '') {
            return $documentType === 4 && $prefix === 'INV' ? 'DS' : $prefix;
        }
    } catch (Throwable $e) {}

    return $defaults[$documentType] ?? 'DOC';
}

function deleteSale(PDO $pdo, $cancel = false)
{
    requireSalesPermission('delete');

    if ($cancel) {
        requireSalesPermission('adjust');
    }

    $scope = getScope();
    $userId = currentUserIdSafe();
    $saleId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $reason = cleanInput($_POST['reason'] ?? '');

    if ($saleId <= 0) {
        jsonResponse(false, 'Invalid sales id.');
    }

    try {
        $pdo->beginTransaction();

        $sale = getSaleForUpdate($pdo, $scope, $saleId);
        if (!$sale) {
            throw new Exception('Sale not found.');
        }

        if ((int)$sale['status'] === 3 || (int)$sale['status'] === 4) {
            throw new Exception('Sale already closed.');
        }

        if ((int)$sale['stock_deducted'] === 1) {
            reverseSaleStock($pdo, $scope, $saleId);
        }

        markOldPaymentsInactive($pdo, $scope, $saleId);
        markOldSaleRowsInactive($pdo, $scope, $saleId);

        if ($cancel) {
            $stmt = $pdo->prepare("
                UPDATE sales
                SET status = 4,
                    cancelled_by = :user_id,
                    cancelled_at = NOW(),
                    cancel_reason = :reason,
                    stock_deducted = 0
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':reason' => $reason,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $reg = $pdo->prepare("
                UPDATE invoice_number_register
                SET status = 3
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND sales_id = :sales_id
            ");
            $reg->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':sales_id' => $saleId
            ]);

            addActivityLog($pdo, $scope, $userId, 'sales', 'CANCEL_INVOICE', 'Invoice ' . $sale['sales_no'] . ' cancelled.', $saleId, $sale['sales_no']);
            $message = 'Invoice cancelled. Stock returned. Invoice number locked.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE sales
                SET status = 3,
                    deleted_by = :user_id,
                    deleted_at = NOW(),
                    delete_reason = :reason,
                    stock_deducted = 0
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':reason' => $reason,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $reg = $pdo->prepare("
                UPDATE invoice_number_register
                SET status = 2,
                    sales_id = NULL,
                    deleted_sales_id = :deleted_sales_id,
                    deleted_at = NOW()
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND sales_id = :sales_id
            ");
            $reg->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':deleted_sales_id' => $saleId,
                ':sales_id' => $saleId
            ]);

            addActivityLog($pdo, $scope, $userId, 'sales', 'DELETE_INVOICE', 'Invoice ' . $sale['sales_no'] . ' deleted and number reusable.', $saleId, $sale['sales_no']);
            $message = 'Invoice deleted. Stock returned. Invoice number reusable.';
        }

        updateCustomerOutstanding($pdo, $scope, (int)$sale['customer_id']);

        $pdo->commit();

        jsonOk($message);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function updateCustomerOutstanding(PDO $pdo, array $scope, $customerId)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(opening_outstanding, 0) +
                   COALESCE((
                       SELECT SUM(due_amount)
                       FROM sales
                       WHERE business_id = :business_id
                       AND branch_id = :branch_id
                       AND customer_id = :customer_id
                       AND status IN (1,2)
                   ), 0) AS outstanding
            FROM customers
            WHERE id = :customer_id2
            AND business_id = :business_id2
            AND branch_id = :branch_id2
            LIMIT 1
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId,
            ':customer_id2' => $customerId,
            ':business_id2' => $scope['business_id'],
            ':branch_id2' => $scope['branch_id']
        ]);
        $outstanding = (float)$stmt->fetchColumn();

        $upd = $pdo->prepare("
            UPDATE customers
            SET current_outstanding = :outstanding
            WHERE id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $upd->execute([
            ':outstanding' => round2($outstanding),
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    } catch (Throwable $e) {}
}

function addActivityLog(PDO $pdo, array $scope, $userId, $module, $action, $description, $referenceId, $recordReference)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
            (
                business_id, branch_id, user_id, module_key, action_type,
                description, reference_id, record_reference, ip_address
            )
            VALUES
            (
                :business_id, :branch_id, :user_id, :module_key, :action_type,
                :description, :reference_id, :record_reference, :ip_address
            )
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':user_id' => $userId > 0 ? $userId : null,
            ':module_key' => $module,
            ':action_type' => $action,
            ':description' => $description,
            ':reference_id' => $referenceId,
            ':record_reference' => $recordReference,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Throwable $e) {}
}
