<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

/** @var PDO $pdo */

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'list_products':
        listProducts($pdo);
        break;

    case 'get_product':
        getProduct($pdo);
        break;

    case 'save_product':
        verifyCsrfToken();
        saveProduct($pdo);
        break;

    case 'delete_product':
        verifyCsrfToken();
        deleteProduct($pdo);
        break;

    case 'get_categories':
        getCategories($pdo);
        break;

    case 'get_sub_categories':
        getSubCategories($pdo);
        break;

    case 'get_hsn_codes':
        getHsnCodes($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function requireProductPermission($action = 'view')
{
    if (isPlatformOwner()) {
        return;
    }

    if (function_exists('hasPermission') && !hasPermission('products', $action)) {
        jsonResponse(false, 'Permission denied.');
    }
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

function getCategories(PDO $pdo)
{
    requireProductPermission('view');

    $scope = getScope();

    $stmt = $pdo->prepare("
        SELECT id, category_name
        FROM categories
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY category_name ASC
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Categories loaded.', [
        'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getSubCategories(PDO $pdo)
{
    requireProductPermission('view');

    $scope = getScope();
    $categoryId = (int)($_GET['category_id'] ?? 0);

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($categoryId > 0) {
        $where .= " AND category_id = :category_id ";
        $params[':category_id'] = $categoryId;
    }

    $stmt = $pdo->prepare("
        SELECT id, category_id, sub_category_name
        FROM sub_categories
        $where
        ORDER BY sub_category_name ASC
    ");

    $stmt->execute($params);

    jsonResponse(true, 'Sub categories loaded.', [
        'sub_categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getHsnCodes(PDO $pdo)
{
    requireProductPermission('view');

    $scope = getScope();

    $stmt = $pdo->prepare("
        SELECT
            id,
            hsn_code,
            hsn_description,
            cgst_percentage,
            sgst_percentage,
            igst_percentage
        FROM hsn_codes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY hsn_code ASC
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'HSN codes loaded.', [
        'hsn_codes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function listProducts(PDO $pdo)
{
    requireProductPermission('view');

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $categoryId = (int)($_GET['category_id'] ?? 0);

    $where = "
        WHERE p.business_id = :business_id
        AND p.branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND p.status = :status ";
        $params[':status'] = $status;
    }

    if ($categoryId > 0) {
        $where .= " AND p.category_id = :category_id ";
        $params[':category_id'] = $categoryId;
    }

    if ($search !== '') {
        $where .= "
            AND (
                p.product_code LIKE :search
                OR p.product_name LIKE :search
                OR c.category_name LIKE :search
                OR sc.sub_category_name LIKE :search
                OR h.hsn_code LIKE :search
            )
        ";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.category_id,
            p.sub_category_id,
            p.hsn_id,
            p.product_code,
            p.product_name,
            p.base_unit,
            p.box_label,
            p.default_pieces_per_box,
            p.markup_percentage,
            p.minimum_stock,
            p.status,
            p.created_at,
            c.category_name,
            sc.sub_category_name,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN sub_categories sc ON sc.id = p.sub_category_id
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        $where
        ORDER BY p.id DESC
    ");

    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_products,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_products,
            SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_products,
            COALESCE(SUM(minimum_stock), 0) AS minimum_stock_total
        FROM products
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ");

    $statsStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Products loaded.', [
        'products' => $products,
        'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
    ]);
}

function getProduct(PDO $pdo)
{
    requireProductPermission('view');

    $scope = getScope();
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($productId <= 0) {
        jsonResponse(false, 'Invalid product.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM products
        WHERE id = :product_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");

    $stmt->execute([
        ':product_id' => $productId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        jsonResponse(false, 'Product not found.');
    }

    jsonResponse(true, 'Product loaded.', [
        'product' => $product
    ]);
}

function saveProduct(PDO $pdo)
{
    $scope = getScope();
    $productId = (int)($_POST['product_id'] ?? 0);

    requireProductPermission($productId > 0 ? 'edit' : 'add');

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $subCategoryId = (int)($_POST['sub_category_id'] ?? 0);
    $hsnId = (int)($_POST['hsn_id'] ?? 0);

    $productCode = strtoupper(cleanInput($_POST['product_code'] ?? ''));
    $productName = cleanInput($_POST['product_name'] ?? '');
    $baseUnit = cleanInput($_POST['base_unit'] ?? 'Piece');
    $boxLabel = cleanInput($_POST['box_label'] ?? 'Box');
    $defaultPiecesPerBox = (float)($_POST['default_pieces_per_box'] ?? 1);
    $markupPercentage = (float)($_POST['markup_percentage'] ?? 0);
    $minimumStock = (float)($_POST['minimum_stock'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if ($categoryId <= 0) {
        jsonResponse(false, 'Please select category.');
    }

    if ($subCategoryId <= 0) {
        jsonResponse(false, 'Please select sub category.');
    }

    if ($hsnId <= 0) {
        jsonResponse(false, 'Please select HSN.');
    }

    if ($productName === '') {
        jsonResponse(false, 'Please enter product name.');
    }

    if ($baseUnit === '') {
        $baseUnit = 'Piece';
    }

    if ($boxLabel === '') {
        $boxLabel = 'Box';
    }

    if ($defaultPiecesPerBox <= 0) {
        jsonResponse(false, 'Default pieces per box must be greater than zero.');
    }

    if ($markupPercentage < 0 || $minimumStock < 0) {
        jsonResponse(false, 'Markup and minimum stock cannot be negative.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    validateCategory($pdo, $scope, $categoryId);
    validateSubCategory($pdo, $scope, $categoryId, $subCategoryId);
    validateHsn($pdo, $scope, $hsnId);

    if ($productCode === '') {
        $productCode = generateProductCode($pdo, $scope['business_id'], $scope['branch_id']);
    }

    try {
        if ($productId > 0) {
            $stmt = $pdo->prepare("
                UPDATE products
                SET category_id = :category_id,
                    sub_category_id = :sub_category_id,
                    hsn_id = :hsn_id,
                    product_code = :product_code,
                    product_name = :product_name,
                    base_unit = :base_unit,
                    box_label = :box_label,
                    default_pieces_per_box = :default_pieces_per_box,
                    markup_percentage = :markup_percentage,
                    minimum_stock = :minimum_stock,
                    status = :status
                WHERE id = :product_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':category_id' => $categoryId,
                ':sub_category_id' => $subCategoryId,
                ':hsn_id' => $hsnId,
                ':product_code' => $productCode,
                ':product_name' => $productName,
                ':base_unit' => $baseUnit,
                ':box_label' => $boxLabel,
                ':default_pieces_per_box' => $defaultPiecesPerBox,
                ':markup_percentage' => $markupPercentage,
                ':minimum_stock' => $minimumStock,
                ':status' => $status,
                ':product_id' => $productId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Product updated successfully.', [
                'product_id' => $productId
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO products
            (
                business_id,
                branch_id,
                category_id,
                sub_category_id,
                hsn_id,
                product_code,
                product_name,
                base_unit,
                box_label,
                default_pieces_per_box,
                markup_percentage,
                minimum_stock,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :category_id,
                :sub_category_id,
                :hsn_id,
                :product_code,
                :product_name,
                :base_unit,
                :box_label,
                :default_pieces_per_box,
                :markup_percentage,
                :minimum_stock,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':category_id' => $categoryId,
            ':sub_category_id' => $subCategoryId,
            ':hsn_id' => $hsnId,
            ':product_code' => $productCode,
            ':product_name' => $productName,
            ':base_unit' => $baseUnit,
            ':box_label' => $boxLabel,
            ':default_pieces_per_box' => $defaultPiecesPerBox,
            ':markup_percentage' => $markupPercentage,
            ':minimum_stock' => $minimumStock,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Product added successfully.', [
            'product_id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Product save failed.');
    }
}

function deleteProduct(PDO $pdo)
{
    requireProductPermission('delete');

    $scope = getScope();
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($productId <= 0) {
        jsonResponse(false, 'Invalid product.');
    }

    $usedTables = [
        'purchase_items' => 'purchase',
        'sales_document_items' => 'sales'
    ];

    foreach ($usedTables as $table => $label) {
        if (!tableExists($pdo, $table)) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total_used
            FROM $table
            WHERE product_id = :product_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':product_id' => $productId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        if ((int)($stmt->fetch(PDO::FETCH_ASSOC)['total_used'] ?? 0) > 0) {
            jsonResponse(false, 'Product already used in ' . $label . '. You can make it inactive instead.');
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM products
        WHERE id = :product_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':product_id' => $productId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Product deleted successfully.');
}

function validateCategory(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $categoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid category selected.');
    }
}

function validateSubCategory(PDO $pdo, array $scope, $categoryId, $subCategoryId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM sub_categories
        WHERE id = :id
        AND category_id = :category_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $subCategoryId,
        ':category_id' => $categoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid sub category selected.');
    }
}

function validateHsn(PDO $pdo, array $scope, $hsnId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM hsn_codes
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $hsnId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid HSN selected.');
    }
}

function generateProductCode(PDO $pdo, $businessId, $branchId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS next_no
        FROM products
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNo = (int)($row['next_no'] ?? 1);

    return 'PRD' . str_pad((string)$nextNo, 4, '0', STR_PAD_LEFT);
}

function tableExists(PDO $pdo, $tableName)
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute([':table_name' => $tableName]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Exception $e) {
        return false;
    }
}
