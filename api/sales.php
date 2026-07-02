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
    case 'payment_page_init':
        paymentPageInit($pdo);
        break;
    case 'list_customer_payments':
        listCustomerPayments($pdo);
        break;
    case 'get_customer_payment':
        getCustomerPayment($pdo);
        break;
    case 'save_customer_payment':
        verifyCsrfToken();
        saveCustomerPayment($pdo);
        break;
    case 'cancel_customer_payment':
        verifyCsrfToken();
        cancelCustomerPayment($pdo);
        break;
    case 'list_customer_due_documents':
        listCustomerDueDocuments($pdo);
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



function salesDocumentLabelByType($typeId)
{
    $types = salesDocumentTypes();
    return isset($types[(int)$typeId]) ? $types[(int)$typeId]['label'] : 'Document';
}

function listCustomerDueDocuments(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $includePaid = (int)($_GET['include_paid'] ?? 0);

    if ($customerId <= 0) {
        jsonResponse(false, 'Invalid customer.');
    }

    $whereDue = $includePaid === 1 ? "" : "AND s.due_amount > 0";

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.sales_no,
            s.document_type,
            s.sales_date,
            s.grand_total,
            s.paid_amount,
            s.due_amount,
            s.payment_status,
            s.status
        FROM sales s
        WHERE s.business_id = :business_id
        AND s.branch_id = :branch_id
        AND s.customer_id = :customer_id
        AND s.status IN (1,2)
        $whereDue
        ORDER BY s.sales_date ASC, s.id ASC
        LIMIT 500
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['document_label'] = salesDocumentLabelByType((int)$row['document_type']);
    }

    jsonOk('Customer due documents loaded.', ['rows' => $rows]);
}

function getPaymentModeSummary(PDO $pdo, array $scope, $paymentId)
{
    $splits = fetchCustomerPaymentSplits($pdo, $scope, $paymentId);
    if (!$splits) {
        return '';
    }

    $parts = [];
    foreach ($splits as $split) {
        $parts[] = ($split['mode_name'] ?: 'Mode') . ' ₹' . number_format((float)$split['amount'], 2);
    }

    return implode(', ', $parts);
}


function paymentPageInit(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    ensureDefaultPaymentModes($pdo, $scope);

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $salesId = (int)($_GET['sales_id'] ?? 0);

    $data = [
        'payment_modes' => [],
        'customers' => [],
        'selected_customer' => null,
        'selected_sale' => null,
        'payments' => []
    ];

    $modes = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY mode_name ASC
    ");
    $modes->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $data['payment_modes'] = $modes->fetchAll(PDO::FETCH_ASSOC);

    if ($salesId > 0) {
        $saleStmt = $pdo->prepare("
            SELECT s.id, s.sales_no, s.customer_id, s.document_type, s.sales_date,
                   s.grand_total, s.paid_amount, s.due_amount, s.payment_status,
                   c.customer_name, c.mobile AS customer_mobile,
                   COALESCE(c.opening_due, c.opening_outstanding, 0) AS opening_due,
                   COALESCE(c.current_outstanding, c.total_outstanding, 0) AS total_outstanding
            FROM sales s
            INNER JOIN customers c ON c.id = s.customer_id
            WHERE s.id = :sales_id
            AND s.business_id = :business_id
            AND s.branch_id = :branch_id
            LIMIT 1
        ");
        $saleStmt->execute([
            ':sales_id' => $salesId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $data['selected_sale'] = $saleStmt->fetch(PDO::FETCH_ASSOC);
        if ($data['selected_sale']) {
            $data['selected_sale']['document_label'] = salesDocumentLabelByType((int)$data['selected_sale']['document_type']);
            $customerId = (int)$data['selected_sale']['customer_id'];
        }
    }

    if ($customerId > 0) {
        $data['selected_customer'] = getCustomerPaymentSnapshot($pdo, $scope, $customerId);
        $data['payments'] = fetchCustomerPaymentRows($pdo, $scope, $customerId, 100);
    }

    jsonOk('Payment page loaded.', $data);
}

function listCustomerPayments(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $search = cleanInput($_GET['search'] ?? '');

    $where = "WHERE cp.business_id = :business_id AND cp.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($customerId > 0) {
        $where .= " AND cp.customer_id = :customer_id";
        $params[':customer_id'] = $customerId;
    }

    if ($search !== '') {
        $where .= " AND (cp.payment_no LIKE :search_payment OR c.customer_name LIKE :search_customer OR c.mobile LIKE :search_mobile OR s.sales_no LIKE :search_sales)";
        $params[':search_payment'] = '%' . $search . '%';
        $params[':search_customer'] = '%' . $search . '%';
        $params[':search_mobile'] = '%' . $search . '%';
        $params[':search_sales'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT cp.*, c.customer_name, c.mobile AS customer_mobile,
               CASE
                   WHEN COALESCE(split_count.cnt, 0) > 1 THEN 'Split'
                   ELSE COALESCE(split_one.mode_name, pm.mode_name)
               END AS mode_name,
               s.sales_no, s.document_type
        FROM customer_payments cp
        INNER JOIN customers c ON c.id = cp.customer_id
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN (
            SELECT payment_id, COUNT(*) AS cnt
            FROM customer_payment_splits
            WHERE status = 1
            GROUP BY payment_id
        ) split_count ON split_count.payment_id = cp.id
        LEFT JOIN (
            SELECT cps.payment_id, MAX(pm2.mode_name) AS mode_name
            FROM customer_payment_splits cps
            LEFT JOIN payment_modes pm2 ON pm2.id = cps.payment_mode_id
            WHERE cps.status = 1
            GROUP BY cps.payment_id
        ) split_one ON split_one.payment_id = cp.id
        LEFT JOIN sales s ON s.id = cp.sales_id
        $where
        ORDER BY cp.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['document_label'] = !empty($row['document_type']) ? salesDocumentLabelByType((int)$row['document_type']) : '';
        $row['split_summary'] = getPaymentModeSummary($pdo, $scope, (int)$row['id']);
    }

    jsonOk('Payments loaded.', ['rows' => $rows]);
}

function getCustomerPayment(PDO $pdo)
{
    requireSalesPermission('view');

    $scope = getScope();
    $paymentId = (int)($_GET['id'] ?? 0);

    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment id.');
    }

    $stmt = $pdo->prepare("
        SELECT cp.*, c.customer_name, c.mobile AS customer_mobile,
               CASE
                   WHEN COALESCE(split_count.cnt, 0) > 1 THEN 'Split'
                   ELSE COALESCE(split_one.mode_name, pm.mode_name)
               END AS mode_name,
               s.sales_no, s.document_type
        FROM customer_payments cp
        INNER JOIN customers c ON c.id = cp.customer_id
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN (
            SELECT payment_id, COUNT(*) AS cnt
            FROM customer_payment_splits
            WHERE status = 1
            GROUP BY payment_id
        ) split_count ON split_count.payment_id = cp.id
        LEFT JOIN (
            SELECT cps.payment_id, MAX(pm2.mode_name) AS mode_name
            FROM customer_payment_splits cps
            LEFT JOIN payment_modes pm2 ON pm2.id = cps.payment_mode_id
            WHERE cps.status = 1
            GROUP BY cps.payment_id
        ) split_one ON split_one.payment_id = cp.id
        LEFT JOIN sales s ON s.id = cp.sales_id
        WHERE cp.id = :id
        AND cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        jsonResponse(false, 'Payment not found.');
    }

    $alloc = $pdo->prepare("
        SELECT cpa.*, s.sales_no
        FROM customer_payment_allocations cpa
        LEFT JOIN sales s ON s.id = cpa.sales_id
        WHERE cpa.payment_id = :payment_id
        AND cpa.business_id = :business_id
        AND cpa.branch_id = :branch_id
        ORDER BY cpa.id ASC
    ");
    $alloc->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Payment loaded.', [
        'payment' => $payment,
        'allocations' => $alloc->fetchAll(PDO::FETCH_ASSOC),
        'splits' => fetchCustomerPaymentSplits($pdo, $scope, $paymentId),
        'customer' => getCustomerPaymentSnapshot($pdo, $scope, (int)$payment['customer_id'])
    ]);
}


function readPaymentSplitsFromRequest()
{
    $raw = $_POST['split_payments'] ?? '';
    $rows = [];

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $modeId = (int)($row['payment_mode_id'] ?? 0);
                $amount = round2($row['amount'] ?? 0);
                $ref = cleanInput($row['reference_no'] ?? '');

                if ($modeId > 0 && $amount > 0) {
                    $rows[] = [
                        'payment_mode_id' => $modeId,
                        'amount' => $amount,
                        'reference_no' => $ref
                    ];
                }
            }
        }
    }

    if (!$rows) {
        $modeId = (int)($_POST['payment_mode_id'] ?? 0);
        $amount = round2($_POST['amount'] ?? 0);
        $ref = cleanInput($_POST['reference_no'] ?? '');

        if ($modeId > 0 && $amount > 0) {
            $rows[] = [
                'payment_mode_id' => $modeId,
                'amount' => $amount,
                'reference_no' => $ref
            ];
        }
    }

    return $rows;
}

function saveCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId, array $splits)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return;
    }

    $old = $pdo->prepare("
        UPDATE customer_payment_splits
        SET status = 2
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND payment_id = :payment_id
        AND status = 1
    ");
    $old->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId
    ]);

    foreach ($splits as $split) {
        $stmt = $pdo->prepare("
            INSERT INTO customer_payment_splits
            (
                business_id, branch_id, payment_id,
                payment_mode_id, amount, reference_no, status
            )
            VALUES
            (
                :business_id, :branch_id, :payment_id,
                :payment_mode_id, :amount, :reference_no, 1
            )
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':payment_id' => $paymentId,
            ':payment_mode_id' => $split['payment_mode_id'],
            ':amount' => $split['amount'],
            ':reference_no' => $split['reference_no']
        ]);
    }
}

function fetchCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT cps.*, pm.mode_name
        FROM customer_payment_splits cps
        LEFT JOIN payment_modes pm ON pm.id = cps.payment_mode_id
        WHERE cps.payment_id = :payment_id
        AND cps.business_id = :business_id
        AND cps.branch_id = :branch_id
        AND cps.status = 1
        ORDER BY cps.id ASC
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cancelCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE customer_payment_splits
        SET status = 2
        WHERE payment_id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}


function saveCustomerPayment(PDO $pdo)
{
    requireSalesPermission('add');

    $scope = getScope();
    $userId = currentUserIdSafe();

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $paymentType = (int)($_POST['payment_type'] ?? 0);
    $salesId = (int)($_POST['sales_id'] ?? 0);
    $paymentDate = cleanDateOrNull($_POST['payment_date'] ?? '') ?: date('Y-m-d');
    $referenceNo = cleanInput($_POST['reference_no'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');

    $splits = readPaymentSplitsFromRequest();
    $amount = round2(array_sum(array_column($splits, 'amount')));

    if (!in_array($paymentType, [1,2,3], true)) {
        jsonResponse(false, 'Invalid payment type.');
    }

    if (!$splits || $amount <= 0) {
        jsonResponse(false, 'Add at least one valid payment split.');
    }

    if ($paymentType === 1 && $salesId <= 0) {
        jsonResponse(false, 'Select particular document for individual payment.');
    }

    $primaryModeId = (int)$splits[0]['payment_mode_id'];

    try {
        $pdo->beginTransaction();

        if ($paymentType === 1) {
            $sale = getSaleForUpdate($pdo, $scope, $salesId);
            if (!$sale) {
                throw new Exception('Sales document not found.');
            }
            $customerId = (int)$sale['customer_id'];
            requireSalesDocumentPermission((int)$sale['document_type'], 'view', 'You do not have permission for this document type.');
        }

        if ($customerId <= 0) {
            throw new Exception('Select customer.');
        }

        $customer = getCustomerForPaymentUpdate($pdo, $scope, $customerId);
        if (!$customer) {
            throw new Exception('Customer not found.');
        }

        if ($paymentId > 0) {
            $old = getCustomerPaymentForUpdate($pdo, $scope, $paymentId);
            if (!$old) {
                throw new Exception('Payment not found.');
            }

            if ((int)$old['status'] !== 1) {
                throw new Exception('Cancelled payment cannot be edited.');
            }

            reversePaymentAllocations($pdo, $scope, $paymentId);

            $paymentNo = $old['payment_no'];
            $upd = $pdo->prepare("
                UPDATE customer_payments
                SET customer_id = :customer_id,
                    payment_type = :payment_type,
                    sales_id = :sales_id,
                    payment_date = :payment_date,
                    payment_mode_id = :payment_mode_id,
                    amount = :amount,
                    reference_no = :reference_no,
                    notes = :notes,
                    updated_by = :updated_by,
                    status = 1
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':customer_id' => $customerId,
                ':payment_type' => $paymentType,
                ':sales_id' => $paymentType === 1 ? $salesId : null,
                ':payment_date' => $paymentDate,
                ':payment_mode_id' => $primaryModeId,
                ':amount' => $amount,
                ':reference_no' => $referenceNo,
                ':notes' => $notes,
                ':updated_by' => $userId,
                ':id' => $paymentId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        } else {
            $paymentNo = nextCustomerPaymentNo($pdo, $scope);
            $ins = $pdo->prepare("
                INSERT INTO customer_payments
                (
                    business_id, branch_id, payment_no, customer_id,
                    payment_type, sales_id, payment_date, payment_mode_id,
                    amount, reference_no, notes, status, created_by
                )
                VALUES
                (
                    :business_id, :branch_id, :payment_no, :customer_id,
                    :payment_type, :sales_id, :payment_date, :payment_mode_id,
                    :amount, :reference_no, :notes, 1, :created_by
                )
            ");
            $ins->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':payment_no' => $paymentNo,
                ':customer_id' => $customerId,
                ':payment_type' => $paymentType,
                ':sales_id' => $paymentType === 1 ? $salesId : null,
                ':payment_date' => $paymentDate,
                ':payment_mode_id' => $primaryModeId,
                ':amount' => $amount,
                ':reference_no' => $referenceNo,
                ':notes' => $notes,
                ':created_by' => $userId
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        saveCustomerPaymentSplits($pdo, $scope, $paymentId, $splits);
        applyCustomerPaymentAllocation($pdo, $scope, $paymentId, $customerId, $paymentType, $salesId, $amount);
        updateCustomerOutstanding($pdo, $scope, $customerId);

        addActivityLog($pdo, $scope, $userId, 'customer_payment', $paymentId > 0 ? 'SAVE' : 'CREATE', 'Payment ' . $paymentNo . ' saved.', $paymentId, $paymentNo);

        $pdo->commit();

        jsonOk('Payment saved successfully.', [
            'payment_id' => $paymentId,
            'payment_no' => $paymentNo,
            'amount' => $amount,
            'splits' => $splits,
            'customer' => getCustomerPaymentSnapshot($pdo, $scope, $customerId)
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function cancelCustomerPayment(PDO $pdo)
{
    requireSalesPermission('delete');

    $scope = getScope();
    $userId = currentUserIdSafe();
    $paymentId = (int)($_POST['id'] ?? 0);
    $reason = cleanInput($_POST['reason'] ?? '');

    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment id.');
    }

    try {
        $pdo->beginTransaction();

        $payment = getCustomerPaymentForUpdate($pdo, $scope, $paymentId);
        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        if ((int)$payment['status'] !== 1) {
            throw new Exception('Payment already cancelled.');
        }

        reversePaymentAllocations($pdo, $scope, $paymentId);
        cancelCustomerPaymentSplits($pdo, $scope, $paymentId);

        $upd = $pdo->prepare("
            UPDATE customer_payments
            SET status = 2,
                cancelled_by = :cancelled_by,
                cancelled_at = NOW(),
                cancel_reason = :cancel_reason
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $upd->execute([
            ':cancelled_by' => $userId,
            ':cancel_reason' => $reason,
            ':id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        updateCustomerOutstanding($pdo, $scope, (int)$payment['customer_id']);
        addActivityLog($pdo, $scope, $userId, 'customer_payment', 'CANCEL', 'Payment ' . $payment['payment_no'] . ' cancelled.', $paymentId, $payment['payment_no']);

        $pdo->commit();

        jsonOk('Payment cancelled and reversed successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function getCustomerPaymentSnapshot(PDO $pdo, array $scope, $customerId)
{
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.customer_name,
            c.mobile,
            COALESCE(c.opening_balance, c.opening_outstanding, 0) AS opening_balance,
            COALESCE(c.opening_paid, 0) AS opening_paid,
            COALESCE(c.opening_due, c.opening_outstanding, 0) AS opening_due,
            COALESCE((
                SELECT SUM(due_amount)
                FROM sales
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND customer_id = :customer_id
                AND status IN (1,2)
            ), 0) AS sales_due,
            COALESCE(c.current_outstanding, c.total_outstanding, 0) AS total_outstanding
        FROM customers c
        WHERE c.id = :customer_id2
        AND c.business_id = :business_id2
        AND c.branch_id = :branch_id2
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

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchCustomerPaymentRows(PDO $pdo, array $scope, $customerId, $limit = 100)
{
    $stmt = $pdo->prepare("
        SELECT cp.*, pm.mode_name, s.sales_no
        FROM customer_payments cp
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN sales s ON s.id = cp.sales_id
        WHERE cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        AND cp.customer_id = :customer_id
        ORDER BY cp.id DESC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerForPaymentUpdate(PDO $pdo, array $scope, $customerId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customers
        WHERE id = :customer_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':customer_id' => $customerId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerPaymentForUpdate(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customer_payments
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function applyCustomerPaymentAllocation(PDO $pdo, array $scope, $paymentId, $customerId, $paymentType, $salesId, $amount)
{
    $remaining = round2($amount);

    if ($paymentType === 1) {
        $sale = getSaleForUpdate($pdo, $scope, $salesId);
        if (!$sale || (int)$sale['customer_id'] !== (int)$customerId) {
            throw new Exception('Invalid sales document for this customer.');
        }

        $due = round2($sale['due_amount'] ?? 0);
        if ($remaining - $due > 0.01) {
            throw new Exception('Payment amount cannot be greater than selected document due.');
        }

        allocateToSale($pdo, $scope, $paymentId, $customerId, $salesId, $remaining);
        return;
    }

    if ($paymentType === 3) {
        $customer = getCustomerForPaymentUpdate($pdo, $scope, $customerId);
        $openingDue = round2($customer['opening_due'] ?? $customer['opening_outstanding'] ?? 0);

        if ($remaining - $openingDue > 0.01) {
            throw new Exception('Payment amount cannot be greater than opening balance due.');
        }

        if ($remaining > 0) {
            allocateToOpeningBalance($pdo, $scope, $paymentId, $customerId, $remaining);
        }
        return;
    }

    // Overall payment rule:
    // First adjust Direct Sale / Final Invoice documents, then reduce opening due with remaining amount.
    if ($paymentType === 2 && $remaining > 0) {
        $stmt = $pdo->prepare("
            SELECT id, due_amount
            FROM sales
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND customer_id = :customer_id
            AND status IN (1,2)
            AND document_type IN (4,5)
            AND due_amount > 0
            ORDER BY sales_date ASC, id ASC
            FOR UPDATE
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId
        ]);

        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sales as $sale) {
            if ($remaining <= 0) {
                break;
            }

            $due = round2($sale['due_amount'] ?? 0);
            if ($due <= 0) {
                continue;
            }

            $apply = min($due, $remaining);
            allocateToSale($pdo, $scope, $paymentId, $customerId, (int)$sale['id'], $apply);
            $remaining = round2($remaining - $apply);
        }

        if ($remaining > 0) {
            $customer = getCustomerForPaymentUpdate($pdo, $scope, $customerId);
            $openingDue = round2($customer['opening_due'] ?? $customer['opening_outstanding'] ?? 0);

            if ($openingDue > 0) {
                $payOpening = min($openingDue, $remaining);
                allocateToOpeningBalance($pdo, $scope, $paymentId, $customerId, $payOpening);
                $remaining = round2($remaining - $payOpening);
            }
        }

        if ($remaining > 0.01) {
            throw new Exception('Payment amount is greater than total customer due.');
        }
    }
}

function allocateToOpeningBalance(PDO $pdo, array $scope, $paymentId, $customerId, $amount)
{
    $amount = round2($amount);
    if ($amount <= 0) {
        return;
    }

    $ins = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            1, NULL, :allocated_amount, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':allocated_amount' => $amount
    ]);

    $upd = $pdo->prepare("
        UPDATE customers
        SET opening_paid = COALESCE(opening_paid, 0) + :paid_amount,
            opening_due = GREATEST(COALESCE(opening_balance, opening_outstanding, 0) - (COALESCE(opening_paid, 0) + :paid_amount_again), 0)
        WHERE id = :customer_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $upd->execute([
        ':paid_amount' => $amount,
        ':paid_amount_again' => $amount,
        ':customer_id' => $customerId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function allocateToSale(PDO $pdo, array $scope, $paymentId, $customerId, $salesId, $amount)
{
    $amount = round2($amount);
    if ($amount <= 0) {
        return;
    }

    $sale = getSaleForUpdate($pdo, $scope, $salesId);
    if (!$sale) {
        throw new Exception('Sales document not found while allocating payment.');
    }

    $due = round2($sale['due_amount'] ?? 0);
    if ($amount - $due > 0.01) {
        throw new Exception('Payment allocation cannot be greater than bill due.');
    }

    $ins = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $salesId,
        ':allocated_amount' => $amount
    ]);

    $newPaid = round2((float)($sale['paid_amount'] ?? 0) + $amount);
    $newDue = round2((float)($sale['grand_total'] ?? 0) - $newPaid);
    if ($newDue < 0) {
        $newDue = 0;
    }
    $status = $newPaid <= 0 ? 0 : ($newDue > 0 ? 1 : 2);

    $upd = $pdo->prepare("
        UPDATE sales
        SET paid_amount = :paid_amount,
            due_amount = :due_amount,
            payment_status = :payment_status
        WHERE id = :sales_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $upd->execute([
        ':paid_amount' => $newPaid,
        ':due_amount' => $newDue,
        ':payment_status' => $status,
        ':sales_id' => $salesId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function reversePaymentAllocations(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customer_payment_allocations
        WHERE payment_id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY id DESC
        FOR UPDATE
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allocations as $allocation) {
        $amount = round2($allocation['allocated_amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }

        if ((int)$allocation['allocation_type'] === 1) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET opening_paid = GREATEST(COALESCE(opening_paid, 0) - :amount, 0),
                    opening_due = GREATEST(COALESCE(opening_balance, opening_outstanding, 0) - GREATEST(COALESCE(opening_paid, 0) - :amount_again, 0), 0)
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':amount' => $amount,
                ':amount_again' => $amount,
                ':customer_id' => (int)$allocation['customer_id'],
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        } elseif ((int)$allocation['allocation_type'] === 2 && (int)$allocation['sales_id'] > 0) {
            $sale = getSaleForUpdate($pdo, $scope, (int)$allocation['sales_id']);
            if ($sale) {
                $newPaid = round2(max(0, (float)($sale['paid_amount'] ?? 0) - $amount));
                $newDue = round2((float)($sale['grand_total'] ?? 0) - $newPaid);
                if ($newDue < 0) {
                    $newDue = 0;
                }
                $status = $newPaid <= 0 ? 0 : ($newDue > 0 ? 1 : 2);

                $upd = $pdo->prepare("
                    UPDATE sales
                    SET paid_amount = :paid_amount,
                        due_amount = :due_amount,
                        payment_status = :payment_status
                    WHERE id = :sales_id
                    AND business_id = :business_id
                    AND branch_id = :branch_id
                ");
                $upd->execute([
                    ':paid_amount' => $newPaid,
                    ':due_amount' => $newDue,
                    ':payment_status' => $status,
                    ':sales_id' => (int)$allocation['sales_id'],
                    ':business_id' => $scope['business_id'],
                    ':branch_id' => $scope['branch_id']
                ]);
            }
        }

        $rev = $pdo->prepare("
            UPDATE customer_payment_allocations
            SET status = 2,
                reversed_at = NOW()
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $rev->execute([
            ':id' => (int)$allocation['id'],
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    }
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
    $isConvertMode = ($mode === 'convert');

    if (!isset(salesDocumentTypes()[$documentType])) {
        jsonResponse(false, 'Invalid document type.');
    }

    if ($isConvertMode) {
        if ($sourceSaleId <= 0 || $sourceType <= 0 || $targetType <= 0) {
            jsonResponse(false, 'Invalid conversion request.');
        }

        // IMPORTANT:
        // Convert / Generate Final Invoice must UPDATE the same sales row.
        // Do not insert duplicate sales row.
        $documentType = $targetType;
        $saleId = $sourceSaleId;

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

    if (!$isConvertMode && $saleId <= 0) {
        requireSalesDocumentPermission($documentType, 'add', 'You do not have permission to create this document type.');
    }

    $shouldDeductStock = in_array($documentType, [4,5], true);

    try {
        $pdo->beginTransaction();

        $oldSale = null;
        $oldCustomerId = 0;
        $oldDocumentType = 0;

        if ($saleId > 0) {
            $oldSale = getSaleForUpdate($pdo, $scope, $saleId);
            if (!$oldSale) {
                throw new Exception('Sale not found.');
            }

            $oldCustomerId = (int)$oldSale['customer_id'];
            $oldDocumentType = (int)$oldSale['document_type'];

            if ($isConvertMode) {
                if ($oldDocumentType !== $sourceType) {
                    throw new Exception('Source document type mismatch. Please reload and try again.');
                }

                if ((int)($oldSale['conversion_status'] ?? 0) === 1 && (int)($oldSale['converted_to_sale_id'] ?? 0) > 0) {
                    throw new Exception('This document is already converted.');
                }
            } else {
                // Existing normal edit keeps document type locked.
                $documentType = $oldDocumentType;
                requireSalesDocumentPermission($documentType, 'edit', 'You do not have permission to edit this document type.');
            }

            if ((int)$oldSale['stock_deducted'] === 1) {
                requireSalesPermission('adjust');
                reverseSaleStock($pdo, $scope, $saleId);
            }

            // Reverse old individual sales page payments before saving new payment rows.
            markOldSaleRowsInactive($pdo, $scope, $saleId);
            markOldPaymentsInactive($pdo, $scope, $saleId);

            if ($isConvertMode) {
                // Same row update. Generate the new document number for the target type.
                $salesNo = reserveDocumentNumber($pdo, $scope, $documentType, $invoiceType, $saleId);

                // Release old register number as converted/closed, not reusable.
                $oldReg = $pdo->prepare("
                    UPDATE invoice_number_register
                    SET status = 3
                    WHERE business_id = :business_id
                    AND branch_id = :branch_id
                    AND sales_id = :sales_id
                    AND document_type = :old_document_type
                ");
                $oldReg->execute([
                    ':business_id' => $scope['business_id'],
                    ':branch_id' => $scope['branch_id'],
                    ':sales_id' => $saleId,
                    ':old_document_type' => $oldDocumentType
                ]);

                attachDocumentNumberToSale($pdo, $scope, $documentType, $invoiceType, $salesNo, $saleId);
            } else {
                $salesNo = $oldSale['sales_no'];
            }
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
                    sales_no = :sales_no,
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
                ':updated_by' => $userId,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            if ($isConvertMode) {
                updateIfColumnExists($pdo, $scope, $saleId, [
                    'source_sale_id' => $sourceSaleId,
                    'source_document_type' => $sourceType,
                    'converted_to_sale_id' => null,
                    'converted_to_document_type' => $documentType,
                    'conversion_status' => 1
                ]);
            }
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

        insertSalePagePaymentReceipt($pdo, $scope, $saleId, $customerId, $computedPayments, $userId);

        updateCustomerOutstanding($pdo, $scope, $customerId);
        if ($oldCustomerId > 0 && $oldCustomerId !== $customerId) {
            updateCustomerOutstanding($pdo, $scope, $oldCustomerId);
        }

        $logAction = $isConvertMode ? 'CONVERT_UPDATE' : ($saleId > 0 ? 'SAVE' : 'CREATE');
        $logText = $isConvertMode
            ? ('Sales ' . $salesNo . ' converted by updating same document.')
            : ('Sales ' . $salesNo . ' saved.');

        addActivityLog($pdo, $scope, $userId, 'sales', $logAction, $logText, $saleId, $salesNo);

        $pdo->commit();

        jsonOk($isConvertMode ? 'Document updated successfully. No duplicate created.' : 'Sale saved successfully.', [
            'id' => $saleId,
            'sales_no' => $salesNo,
            'grand_total' => $grandTotal,
            'paid_amount' => round2($paidAmount),
            'due_amount' => $dueAmount,
            'sale' => [
                'id' => $saleId,
                'sales_no' => $salesNo,
                'document_type' => $documentType,
                'conversion_status' => $isConvertMode ? 1 : 0,
                'converted_to_sale_id' => 0
            ],
            'clear_session' => 1
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


function nextCustomerPaymentNo(PDO $pdo, array $scope)
{
    $prefix = 'PAY';

    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(payment_no, LENGTH(:prefix_like) + 2) AS UNSIGNED)), 0) + 1
            FROM customer_payments
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND payment_no LIKE :like_pattern
            FOR UPDATE
        ");
        $stmt->execute([
            ':prefix_like' => $prefix,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':like_pattern' => $prefix . '-%'
        ]);
        $nextNo = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $nextNo = 1;
    }

    if ($nextNo <= 0) {
        $nextNo = 1;
    }

    return $prefix . '-' . str_pad((string)$nextNo, 5, '0', STR_PAD_LEFT);
}

function insertCustomerPaymentForSale(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    $paymentNo = nextCustomerPaymentNo($pdo, $scope);

    $stmt = $pdo->prepare("
        INSERT INTO customer_payments
        (
            business_id, branch_id, payment_no, customer_id,
            payment_type, sales_id, payment_date, payment_mode_id,
            amount, reference_no, notes, status, created_by
        )
        VALUES
        (
            :business_id, :branch_id, :payment_no, :customer_id,
            1, :sales_id, :payment_date, :payment_mode_id,
            :amount, :reference_no, :notes, 1, :created_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_no' => $paymentNo,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':payment_date' => $payment['payment_date'],
        ':payment_mode_id' => $payment['payment_mode_id'],
        ':amount' => $payment['payment_amount'],
        ':reference_no' => $payment['reference_no'],
        ':notes' => $payment['notes'],
        ':created_by' => $userId > 0 ? $userId : null
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    $alloc = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $alloc->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':allocated_amount' => $payment['payment_amount']
    ]);

    return $paymentId;
}

function reverseCustomerPaymentsForSale(PDO $pdo, array $scope, $saleId, $userId = 0, $reason = 'Sales edited or cancelled')
{
    if (!tableExists($pdo, 'customer_payments') || !tableExists($pdo, 'customer_payment_allocations')) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM customer_payments
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND sales_id = :sales_id
        AND payment_type = 1
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId
    ]);

    $paymentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$paymentIds) {
        return;
    }

    $placeholders = [];
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    foreach ($paymentIds as $index => $paymentId) {
        $key = ':payment_id_' . $index;
        $placeholders[] = $key;
        $params[$key] = $paymentId;
    }

    $allocationSql = "
        UPDATE customer_payment_allocations
        SET status = 2,
            reversed_at = NOW()
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND payment_id IN (" . implode(',', $placeholders) . ")
        AND status = 1
    ";
    $alloc = $pdo->prepare($allocationSql);
    $alloc->execute($params);

    $paymentParams = $params;
    $paymentParams[':cancelled_by'] = $userId > 0 ? $userId : null;
    $paymentParams[':cancel_reason'] = $reason;

    if (tableExists($pdo, 'customer_payment_splits')) {
        $splitSql = "
            UPDATE customer_payment_splits
            SET status = 2
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND payment_id IN (" . implode(',', $placeholders) . ")
            AND status = 1
        ";
        $split = $pdo->prepare($splitSql);
        $split->execute($params);
    }

    $paymentSql = "
        UPDATE customer_payments
        SET status = 2,
            cancelled_by = :cancelled_by,
            cancelled_at = NOW(),
            cancel_reason = :cancel_reason
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND id IN (" . implode(',', $placeholders) . ")
        AND status = 1
    ";
    $pay = $pdo->prepare($paymentSql);
    $pay->execute($paymentParams);
}

function tableExists(PDO $pdo, $tableName)
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
    ");
    $stmt->execute([':table_name' => $tableName]);

    $cache[$tableName] = (int)$stmt->fetchColumn() > 0;
    return $cache[$tableName];
}


function markOldPaymentsInactive(PDO $pdo, array $scope, $saleId)
{
    reverseCustomerPaymentsForSale($pdo, $scope, $saleId, currentUserIdSafe(), 'Sales document edited / regenerated');

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


function insertSalePagePaymentReceipt(PDO $pdo, array $scope, $saleId, $customerId, array $payments, $userId)
{
    if (!$payments) {
        return;
    }

    // Keep old sales_payments rows for existing screens / get_sale compatibility.
    foreach ($payments as $payment) {
        insertLegacySalePayment($pdo, $scope, $saleId, $customerId, $payment, $userId);
    }

    if (!tableExists($pdo, 'customer_payments') || !tableExists($pdo, 'customer_payment_allocations')) {
        return;
    }

    $totalAmount = round2(array_sum(array_column($payments, 'payment_amount')));
    if ($totalAmount <= 0) {
        return;
    }

    $paymentNo = nextCustomerPaymentNo($pdo, $scope);
    $firstPayment = $payments[0];

    $stmt = $pdo->prepare("
        INSERT INTO customer_payments
        (
            business_id, branch_id, payment_no, customer_id,
            payment_type, sales_id, payment_date, payment_mode_id,
            amount, reference_no, notes, status, created_by
        )
        VALUES
        (
            :business_id, :branch_id, :payment_no, :customer_id,
            1, :sales_id, :payment_date, :payment_mode_id,
            :amount, :reference_no, :notes, 1, :created_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_no' => $paymentNo,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':payment_date' => $firstPayment['payment_date'],
        ':payment_mode_id' => $firstPayment['payment_mode_id'],
        ':amount' => $totalAmount,
        ':reference_no' => $firstPayment['reference_no'],
        ':notes' => $firstPayment['notes'],
        ':created_by' => $userId > 0 ? $userId : null
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    if (tableExists($pdo, 'customer_payment_splits')) {
        foreach ($payments as $payment) {
            $split = $pdo->prepare("
                INSERT INTO customer_payment_splits
                (
                    business_id, branch_id, payment_id,
                    payment_mode_id, amount, reference_no, status
                )
                VALUES
                (
                    :business_id, :branch_id, :payment_id,
                    :payment_mode_id, :amount, :reference_no, 1
                )
            ");
            $split->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':payment_id' => $paymentId,
                ':payment_mode_id' => $payment['payment_mode_id'],
                ':amount' => $payment['payment_amount'],
                ':reference_no' => $payment['reference_no']
            ]);
        }
    }

    $alloc = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $alloc->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':allocated_amount' => $totalAmount
    ]);
}

function insertLegacySalePayment(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
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


function insertSalePayment(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    insertSalePagePaymentReceipt($pdo, $scope, $saleId, $customerId, [$payment], $userId);
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
        $openingColumn = '0';
        if (columnExists($pdo, 'customers', 'opening_due')) {
            $openingColumn = 'opening_due';
        } elseif (columnExists($pdo, 'customers', 'opening_outstanding')) {
            $openingColumn = 'opening_outstanding';
        } elseif (columnExists($pdo, 'customers', 'opening_balance')) {
            $openingColumn = 'opening_balance';
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE($openingColumn, 0) +
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
        $outstanding = round2((float)$stmt->fetchColumn());

        if (columnExists($pdo, 'customers', 'current_outstanding')) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET current_outstanding = :outstanding
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':outstanding' => $outstanding,
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'customers', 'total_outstanding')) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET total_outstanding = :outstanding
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':outstanding' => $outstanding,
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }
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
