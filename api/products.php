<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

$stockMovementHelper = BASE_PATH . 'includes/stock-movement-helper.php';
if (file_exists($stockMovementHelper)) {
    require_once $stockMovementHelper;
}

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_page_context': getPageContext($pdo); break;
    case 'list_products': listProducts($pdo); break;
    case 'get_product': getProduct($pdo); break;
    case 'get_product_stock_details': getProductStockDetails($pdo); break;
    case 'save_product': verifyCsrfToken(); saveProduct($pdo); break;
    case 'delete_product': verifyCsrfToken(); deleteProduct($pdo); break;
    case 'get_categories': getCategories($pdo); break;
    case 'get_sub_categories': getSubCategories($pdo); break;
    case 'get_hsn_codes': getHsnCodes($pdo); break;
    case 'quick_add_category': verifyCsrfToken(); quickAddCategory($pdo); break;
    case 'quick_add_sub_category': verifyCsrfToken(); quickAddSubCategory($pdo); break;
    case 'quick_add_hsn': verifyCsrfToken(); quickAddHsn($pdo); break;
    default: jsonResponse(false, 'Invalid action.');
}

function productCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('products', (int)$actionCode);
}

function requireProductPermission($action = 1)
{
    $map = [
        'view' => 1,
        'list' => 2,
        'add' => 3,
        'create' => 3,
        'edit' => 4,
        'delete' => 5,
        'print' => 6,
        'export' => 7
    ];

    $actionCode = is_numeric($action) ? (int)$action : (int)($map[(string)$action] ?? 1);

    if (!productCan($actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function productDropdownAllowed()
{
    return productCan(1) || productCan(2) || productCan(3) || productCan(4);
}

function getPageContext(PDO $pdo)
{
    if (!productCan(1) && !productCan(2) && !productCan(3) && !productCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => productCan(1),
            'can_list' => productCan(2),
            'can_add' => productCan(3),
            'can_edit' => productCan(4),
            'can_delete' => productCan(5),
            'page_title' => 'Product Master',
            'page_note' => 'Manage products based on role permission.',
            'add_button_label' => 'Add Product',
            'add_form_title' => 'Add Product',
            'edit_form_title' => 'Edit Product',
            'list_url' => BASE_URL . 'pages/products.php',
            'form_url' => BASE_URL . 'pages/product-form.php'
        ]
    ]);
}

function getScope() {
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();
    if ($businessId <= 0 || $branchId <= 0) jsonResponse(false, 'Invalid business or branch session.');
    return ['business_id' => $businessId, 'branch_id' => $branchId];
}

function getCategories(PDO $pdo) {
    if (!productDropdownAllowed()) jsonResponse(false, 'Permission denied.');
    $scope = getScope();
    $stmt = $pdo->prepare("SELECT id, category_name FROM categories WHERE business_id=:business_id AND branch_id=:branch_id AND status=1 ORDER BY category_name ASC");
    $stmt->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'Categories loaded.', ['categories'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getSubCategories(PDO $pdo) {
    if (!productDropdownAllowed()) jsonResponse(false, 'Permission denied.');
    $scope = getScope();
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $where = "WHERE business_id=:business_id AND branch_id=:branch_id AND status=1";
    $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];
    if ($categoryId > 0) { $where .= " AND category_id=:category_id"; $params[':category_id']=$categoryId; }
    $stmt = $pdo->prepare("SELECT id, category_id, sub_category_name FROM sub_categories $where ORDER BY sub_category_name ASC");
    $stmt->execute($params);
    jsonResponse(true, 'Sub categories loaded.', ['sub_categories'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getHsnCodes(PDO $pdo) {
    if (!productDropdownAllowed()) jsonResponse(false, 'Permission denied.');
    $scope = getScope();
    $stmt = $pdo->prepare("SELECT id, hsn_code, hsn_description, cgst_percentage, sgst_percentage, igst_percentage FROM hsn_codes WHERE business_id=:business_id AND branch_id=:branch_id AND status=1 ORDER BY hsn_code ASC");
    $stmt->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'HSN codes loaded.', ['hsn_codes'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function listProducts(PDO $pdo) {
    if (!productCan(1) && !productCan(2)) jsonResponse(false, 'Permission denied.');
    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $categoryId = (int)($_GET['category_id'] ?? 0);

    $where = "WHERE p.business_id=:business_id AND p.branch_id=:branch_id";
    $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];

    if ($status === 1 || $status === 2) { $where .= " AND p.status=:status"; $params[':status']=$status; }
    if ($categoryId > 0) { $where .= " AND p.category_id=:category_id"; $params[':category_id']=$categoryId; }
    if ($search !== '') {
        /*
        |--------------------------------------------------------------------------
        | Unique PDO placeholders avoid SQLSTATE[HY093].
        |--------------------------------------------------------------------------
        */
        $where .= "
            AND (
                p.product_code LIKE :search_product_code
                OR p.product_name LIKE :search_product_name
                OR c.category_name LIKE :search_category_name
                OR sc.sub_category_name LIKE :search_sub_category_name
                OR h.hsn_code LIKE :search_hsn_code
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_product_code'] = $searchValue;
        $params[':search_product_name'] = $searchValue;
        $params[':search_category_name'] = $searchValue;
        $params[':search_sub_category_name'] = $searchValue;
        $params[':search_hsn_code'] = $searchValue;
    }

    $params[':stock_business_id'] = $scope['business_id'];
    $params[':stock_branch_id'] = $scope['branch_id'];

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            c.category_name,
            sc.sub_category_name,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage,
            COALESCE(st.available_stock, 0) AS available_stock
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN sub_categories sc ON sc.id=p.sub_category_id
        LEFT JOIN hsn_codes h ON h.id=p.hsn_id
        LEFT JOIN (
            SELECT
                pi.product_id,
                COALESCE(SUM(pi.available_qty), 0) AS available_stock
            FROM purchase_items pi
            INNER JOIN purchases pur ON pur.id = pi.purchase_id
                AND pur.business_id = pi.business_id
                AND pur.branch_id = pi.branch_id
            WHERE pi.business_id = :stock_business_id
            AND pi.branch_id = :stock_branch_id
            AND pi.status = 1
            AND pur.status = 1
            AND pi.available_qty > 0
            GROUP BY pi.product_id
        ) st ON st.product_id = p.id
        $where
        ORDER BY p.id DESC
    ");
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $canEdit = productCan(4);
    $canDelete = productCan(5);

    foreach ($products as &$product) {
        $product['can_edit'] = $canEdit;
        $product['can_delete'] = $canDelete;
    }
    unset($product);

    $stats = $pdo->prepare("SELECT COUNT(*) total_products, COALESCE(SUM(CASE WHEN status=1 THEN 1 ELSE 0 END),0) active_products, COALESCE(SUM(CASE WHEN status=2 THEN 1 ELSE 0 END),0) inactive_products, COALESCE(SUM(minimum_stock),0) minimum_stock_total FROM products WHERE business_id=:business_id AND branch_id=:branch_id");
    $stats->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);

    jsonResponse(true, 'Products loaded.', ['products'=>$products, 'stats'=>$stats->fetch(PDO::FETCH_ASSOC)]);
}

function getProduct(PDO $pdo) {
    if (!productCan(1) && !productCan(2) && !productCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();
    $id = (int)($_GET['product_id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid product.');

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            c.category_name,
            sc.sub_category_name,
            h.hsn_code,
            h.hsn_description,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN sub_categories sc ON sc.id = p.sub_category_id
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        WHERE p.id = :id
        AND p.business_id = :business_id
        AND p.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) jsonResponse(false, 'Product not found.');

    jsonResponse(true, 'Product loaded.', ['product' => $product]);
}

function getProductStockDetails(PDO $pdo) {
    if (!productCan(1) && !productCan(2) && !productCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();
    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) jsonResponse(false, 'Invalid product.');

    $productStmt = $pdo->prepare("
        SELECT
            p.id,
            p.product_code,
            p.product_name,
            p.base_unit,
            p.minimum_stock,
            p.cost_price,
            p.retail_price,
            p.wholesale_price,
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
        WHERE p.id = :product_id
        AND p.business_id = :business_id
        AND p.branch_id = :branch_id
        LIMIT 1
    ");
    $productStmt->execute([
        ':product_id' => $productId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) jsonResponse(false, 'Product not found.');

    $batchStmt = $pdo->prepare("
        SELECT
            pi.id AS purchase_item_id,
            pi.purchase_id,
            pi.product_id,
            pi.product_code,
            pi.product_name,
            pi.hsn_code,
            pi.box_qty,
            pi.loose_piece_qty,
            pi.qty,
            pi.free_qty,
            pi.unit_conversion,
            pi.stock_qty,
            pi.sold_qty,
            pi.available_qty,
            pi.purchase_price,
            pi.mrp,
            pi.line_total,
            pi.expiry_date,
            pi.unit_label,
            pi.base_unit,
            pi.box_label,
            p.bill_no,
            p.batch_no,
            p.purchase_date,
            p.grand_total,
            p.status AS purchase_status,
            s.supplier_name
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        WHERE pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.product_id = :product_id
        AND pi.status = 1
        AND p.status = 1
        AND pi.available_qty > 0
        ORDER BY p.purchase_date ASC, pi.id ASC
    ");
    $batchStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId
    ]);
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStock = 0;
    $totalSold = 0;
    $totalAvailable = 0;
    $stockValue = 0;

    foreach ($batches as $batch) {
        $totalStock += (float)($batch['stock_qty'] ?? 0);
        $totalSold += (float)($batch['sold_qty'] ?? 0);
        $totalAvailable += (float)($batch['available_qty'] ?? 0);
        $stockValue += ((float)($batch['available_qty'] ?? 0) * (float)($batch['purchase_price'] ?? 0));
    }

    jsonResponse(true, 'Stock details loaded.', [
        'product' => $product,
        'batches' => $batches,
        'summary' => [
            'total_stock' => round($totalStock, 4),
            'total_sold' => round($totalSold, 4),
            'total_available' => round($totalAvailable, 4),
            'stock_value' => round($stockValue, 2)
        ]
    ]);
}

function saveProduct(PDO $pdo) {
    $scope = getScope();
    $productId = (int)($_POST['product_id'] ?? 0);
    requireProductPermission($productId > 0 ? 4 : 3);

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $subCategoryId = (int)($_POST['sub_category_id'] ?? 0); // optional
    $hsnId = (int)($_POST['hsn_id'] ?? 0); // optional

    $productCode = strtoupper(cleanInput($_POST['product_code'] ?? ''));
    $productName = cleanInput($_POST['product_name'] ?? '');
    $baseUnit = cleanInput($_POST['base_unit'] ?? 'Piece');

    // Secondary unit is optional.
    // If no secondary unit is selected, label is saved as NULL and conversion is saved as 1.
    $secondaryUnitLabelRaw = cleanInput($_POST['secondary_unit_label'] ?? '');
    $secondaryUnitLabel = $secondaryUnitLabelRaw !== '' ? $secondaryUnitLabelRaw : null;
    $secondaryUnitValueRaw = trim((string)($_POST['secondary_unit_value'] ?? ''));
    $secondaryUnitValue = null;

    if ($secondaryUnitLabel !== null) {
        $secondaryUnitValue = round((float)$secondaryUnitValueRaw, 4);
        if ($secondaryUnitValue <= 0) {
            jsonResponse(false, 'Please enter secondary conversion value.');
        }
    }

    $boxLabel = null;
    $defaultPiecesPerBox = 1;
    $initialStock = round((float)($_POST['initial_stock'] ?? 0), 4);
    $initialStockExpiryDate = cleanInput($_POST['initial_stock_expiry_date'] ?? '');

    $enterMrp = round((float)($_POST['enter_mrp'] ?? 0), 2);
    $gstType = (int)($_POST['gst_type'] ?? 1);
    $discountType = 1;
    $discountValue = 0.00;

    $retailMarkupType = (int)($_POST['retail_markup_type'] ?? 1);
    $retailMarkupValue = round((float)($_POST['retail_markup_value'] ?? 0), 2);
    $retailPrice = round((float)($_POST['retail_price'] ?? 0), 2);

    $wholesaleMarkupType = (int)($_POST['wholesale_markup_type'] ?? 1);
    $wholesaleMarkupValue = round((float)($_POST['wholesale_markup_value'] ?? 0), 2);
    $wholesalePrice = round((float)($_POST['wholesale_price'] ?? 0), 2);

    $markupType = (int)($_POST['markup_type'] ?? 1);
    $markupValue = round((float)($_POST['markup_value'] ?? 0), 2);
    $minimumStock = round((float)($_POST['minimum_stock'] ?? 0), 2);
    $status = (int)($_POST['status'] ?? 1);
    $removeImage = (int)($_POST['remove_image'] ?? 0);

    if ($categoryId <= 0) jsonResponse(false, 'Please select category.');
    if ($productName === '') jsonResponse(false, 'Please enter product name.');
    if ($enterMrp <= 0) jsonResponse(false, 'Please enter MRP.');
    if ($baseUnit === '') jsonResponse(false, 'Please select base unit.');
    if ($initialStock < 0) jsonResponse(false, 'Initial stock cannot be negative.');
    if ($initialStockExpiryDate === '') $initialStockExpiryDate = null;

    if (!in_array($gstType, [1,2], true)) $gstType = 1;
    if (!in_array($discountType, [1,2], true)) $discountType = 1;
    if (!in_array($retailMarkupType, [1,2], true)) $retailMarkupType = 1;
    if (!in_array($wholesaleMarkupType, [1,2], true)) $wholesaleMarkupType = 1;
    if (!in_array($markupType, [1,2], true)) $markupType = 1;
    if (!in_array($status, [1,2], true)) $status = 1;

    validateCategory($pdo, $scope, $categoryId);
    if ($subCategoryId > 0) validateSubCategory($pdo, $scope, $categoryId, $subCategoryId);
    if ($hsnId > 0) validateHsn($pdo, $scope, $hsnId);

    $gstPercentage = $hsnId > 0 ? getHsnGstPercentage($pdo, $scope, $hsnId) : 0;
    $finalMrp = $gstType === 1 ? $enterMrp : round($enterMrp + (($enterMrp * $gstPercentage) / 100), 2);

    $costPrice = round((float)($_POST['cost_price'] ?? 0), 2);
    if ($costPrice <= 0) jsonResponse(false, 'Please enter purchase / stock price.');

    if ($retailPrice <= $costPrice) jsonResponse(false, 'Sale / retail price must be greater than stock price.');
    if ($wholesalePrice < $costPrice) jsonResponse(false, 'Wholesale price must be greater than or equal to stock price.');

    if ($productCode === '') $productCode = generateProductCode($pdo, $scope['business_id'], $scope['branch_id']);

    $oldImage = null;
    if ($productId > 0) {
        $oldStmt = $pdo->prepare("SELECT product_image FROM products WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
        $oldStmt->execute([':id'=>$productId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
        $oldImage = $oldStmt->fetchColumn();
    }

    $imagePath = $oldImage;
    if ($removeImage === 1) {
        deleteUploadedFile($oldImage);
        $imagePath = null;
    }
    if (!empty($_FILES['product_image']['name'])) {
        $newImage = uploadProductImage($_FILES['product_image']);
        if ($newImage) {
            deleteUploadedFile($oldImage);
            $imagePath = $newImage;
        }
    }

    $markupPercentage = $markupType === 1 ? $markupValue : 0;

    $productData = [
        'business_id' => $scope['business_id'],
        'branch_id' => $scope['branch_id'],
        'category_id' => $categoryId,
        'sub_category_id' => $subCategoryId > 0 ? $subCategoryId : null,
        'hsn_id' => $hsnId > 0 ? $hsnId : 0,
        'product_code' => $productCode,
        'product_name' => $productName,
        'product_image' => $imagePath,
        'enter_mrp' => $enterMrp,
        'gst_type' => $gstType,
        'final_mrp' => $finalMrp,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'cost_price' => $costPrice,
        'retail_markup_type' => $retailMarkupType,
        'retail_markup_value' => $retailMarkupValue,
        'retail_price' => $retailPrice,
        'wholesale_markup_type' => $wholesaleMarkupType,
        'wholesale_markup_value' => $wholesaleMarkupValue,
        'wholesale_price' => $wholesalePrice,
        'base_unit' => $baseUnit,
        'secondary_unit_label' => $secondaryUnitLabel,
        'secondary_unit_value' => $secondaryUnitValue,
        'default_pieces_per_box' => $defaultPiecesPerBox,
        'markup_type' => $markupType,
        'markup_value' => $markupValue,
        'markup_percentage' => $markupPercentage,
        'minimum_stock' => $minimumStock,
        'initial_stock' => $initialStock,
        'initial_stock_expiry_date' => $initialStockExpiryDate,
        'status' => $status
    ];

    try {
        $pdo->beginTransaction();

        if ($productId > 0) {
            dynamicUpdate(
                $pdo,
                'products',
                $productData,
                'id = :product_id AND business_id = :business_id AND branch_id = :branch_id',
                [
                    ':product_id' => $productId,
                    ':business_id' => $scope['business_id'],
                    ':branch_id' => $scope['branch_id']
                ]
            );
            $savedProductId = $productId;
        } else {
            $productData['created_by'] = currentUserId();
            $savedProductId = dynamicInsert($pdo, 'products', $productData);
        }

        if ($savedProductId <= 0) {
            throw new Exception('Product save failed.');
        }

        // Initial stock is stored like direct/opening purchase.
        // It creates purchases + purchase_items + stock_movements IN.
        saveInitialStockPurchase(
            $pdo,
            $scope,
            $savedProductId,
            $initialStock,
            $initialStockExpiryDate,
            $costPrice,
            $productName,
            $productCode,
            $baseUnit,
            $secondaryUnitLabel ?: '',
            $secondaryUnitValue !== null ? (float)$secondaryUnitValue : 0.0,
            $hsnId,
            $gstType,
            $gstPercentage,
            $retailPrice,
            $wholesalePrice
        );

        $pdo->commit();

        jsonResponse(true, $productId > 0 ? 'Product updated successfully.' : 'Product added successfully.', [
            'product_id' => $savedProductId,
            'redirect' => BASE_URL . 'pages/products.php'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage() ?: 'Product save failed.');
    }
}

function saveInitialStockBatch(PDO $pdo, array $scope, int $productId, float $quantity, $expiryDate, float $purchasePrice): void
{
    if ($productId <= 0) {
        return;
    }

    $quantity = round($quantity, 4);
    if ($quantity < 0) {
        $quantity = 0;
    }

    $check = $pdo->prepare("
        SELECT id
        FROM product_initial_batches
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND product_id = :product_id
        LIMIT 1
    ");
    $check->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId
    ]);

    $existingId = (int)$check->fetchColumn();

    if ($existingId > 0) {
        $stmt = $pdo->prepare("
            UPDATE product_initial_batches
            SET quantity = :quantity,
                available_qty = :available_qty,
                expiry_date = :expiry_date,
                purchase_price = :purchase_price,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':quantity' => $quantity,
            ':available_qty' => $quantity,
            ':expiry_date' => $expiryDate,
            ':purchase_price' => $purchasePrice,
            ':id' => $existingId
        ]);
        return;
    }

    if ($quantity <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO product_initial_batches
        (business_id, branch_id, product_id, batch_no, quantity, available_qty, expiry_date, purchase_price, status, created_at)
        VALUES
        (:business_id, :branch_id, :product_id, :batch_no, :quantity, :available_qty, :expiry_date, :purchase_price, 1, NOW())
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId,
        ':batch_no' => 'OPEN-' . $productId,
        ':quantity' => $quantity,
        ':available_qty' => $quantity,
        ':expiry_date' => $expiryDate,
        ':purchase_price' => $purchasePrice
    ]);
}



function tableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[$row['Field']] = true;
    }
    return $columns;
}

function dynamicInsert(PDO $pdo, string $table, array $data): int
{
    $columns = tableColumns($pdo, $table);
    $insert = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (isset($columns[$key])) {
            $insert[$key] = $value;
            $params[':' . $key] = $value;
        }
    }

    if (!$insert) {
        return 0;
    }

    $fields = '`' . implode('`, `', array_keys($insert)) . '`';
    $holders = implode(', ', array_keys($params));

    $stmt = $pdo->prepare("INSERT INTO `$table` ($fields) VALUES ($holders)");
    $stmt->execute($params);

    return (int)$pdo->lastInsertId();
}

function dynamicUpdate(PDO $pdo, string $table, array $data, string $whereSql, array $whereParams): void
{
    $columns = tableColumns($pdo, $table);
    $sets = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (isset($columns[$key])) {
            $sets[] = "`$key` = :set_$key";
            $params[":set_$key"] = $value;
        }
    }

    if (!$sets) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $whereSql");
    $stmt->execute(array_merge($params, $whereParams));
}

function saveInitialStockPurchase(
    PDO $pdo,
    array $scope,
    int $productId,
    float $quantity,
    $expiryDate,
    float $purchasePrice,
    string $productName,
    string $productCode,
    string $baseUnit,
    string $secondaryUnitLabel,
    float $secondaryUnitValue,
    int $hsnId,
    int $gstType,
    float $gstPercentage,
    float $retailPrice,
    float $wholesalePrice
): void {
    $quantity = round($quantity, 4);

    if ($productId <= 0 || $quantity <= 0) {
        return;
    }

    $businessId = (int)$scope['business_id'];
    $branchId = (int)$scope['branch_id'];
    $userId = currentUserId();
    $today = date('Y-m-d');
    $batchNo = 'OPEN-' . date('Ymd') . '-' . $productId;
    $billNo = 'OPENING-' . $productId;

    // Avoid duplicate opening stock purchase for the same product.
    $check = $pdo->prepare("
        SELECT id
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND bill_no = :bill_no
        LIMIT 1
    ");
    $check->execute([
        ':business_id' => $businessId,
        ':branch_id' => $branchId,
        ':bill_no' => $billNo
    ]);
    $existingPurchaseId = (int)$check->fetchColumn();

    $taxableAmount = round($quantity * $purchasePrice, 2);
    $gstAmount = round(($taxableAmount * $gstPercentage) / 100, 2);
    $lineTotal = round($taxableAmount + $gstAmount, 2);

    $purchaseData = [
        'business_id' => $businessId,
        'branch_id' => $branchId,
        'supplier_id' => null,
        'purchase_date' => $today,
        'bill_no' => $billNo,
        'batch_no' => $batchNo,
        'sub_total' => $taxableAmount,
        'discount_type' => 1,
        'discount_value' => 0,
        'discount_amount' => 0,
        'tax_amount' => $gstAmount,
        'round_off' => 0,
        'grand_total' => $lineTotal,
        'paid_amount' => 0,
        'due_amount' => $lineTotal,
        'payment_status' => 1,
        'notes' => 'Opening stock from product master',
        'status' => 1,
        'created_by' => $userId
    ];

    if ($existingPurchaseId > 0) {
        $purchaseId = $existingPurchaseId;
        dynamicUpdate($pdo, 'purchases', $purchaseData, 'id = :id', [':id' => $purchaseId]);

        // Remove old opening stock item and inactive old movement before recreating.
        if (function_exists('markStockMovementsInactive')) {
            markStockMovementsInactive($pdo, $scope, [
                'source_type' => 'OPENING_STOCK',
                'source_no' => $billNo,
                'product_id' => $productId
            ]);
        }

        $delete = $pdo->prepare("
            DELETE FROM purchase_items
            WHERE purchase_id = :purchase_id
            AND product_id = :product_id
        ");
        $delete->execute([
            ':purchase_id' => $purchaseId,
            ':product_id' => $productId
        ]);
    } else {
        $purchaseId = dynamicInsert($pdo, 'purchases', $purchaseData);
    }

    if ($purchaseId <= 0) {
        return;
    }

    $expiryDays = 0;
    if ($expiryDate) {
        $expiryDays = max(0, (int)ceil((strtotime($expiryDate) - strtotime($today)) / 86400));
    }

    $cgst = 0;
    $sgst = 0;
    $igst = 0;
    if ($gstPercentage > 0) {
        $cgst = round($gstPercentage / 2, 2);
        $sgst = round($gstPercentage / 2, 2);
    }

    $itemData = [
        'business_id' => $businessId,
        'branch_id' => $branchId,
        'purchase_id' => $purchaseId,
        'product_id' => $productId,
        'product_code' => $productCode,
        'product_name' => $productName,
        'hsn_id' => $hsnId > 0 ? $hsnId : null,
        'hsn_code' => '',
        'expiry_days' => $expiryDays,
        'expiry_date' => $expiryDate ?: null,
        'unit_label' => $baseUnit,
        'base_unit' => $baseUnit,
        'secondary_unit_label' => $secondaryUnitLabel !== '' ? $secondaryUnitLabel : null,
        'secondary_unit_value' => $secondaryUnitValue,
        'pieces_per_box' => 1,
        'box_qty' => 0,
        'loose_piece_qty' => $quantity,
        'qty' => $quantity,
        'free_qty' => 0,
        'unit_conversion' => 1,
        'stock_qty' => $quantity,
        'sold_qty' => 0,
        'available_qty' => $quantity,
        'purchase_price' => $purchasePrice,
        'gross_amount' => $taxableAmount,
        'discount_type' => 1,
        'discount_value' => 0,
        'discount_amount' => 0,
        'scheme_per_piece' => 0,
        'taxable_amount' => $taxableAmount,
        'taxable_per_piece' => $purchasePrice,
        'gst_type' => 2,
        'gst_percentage' => $gstPercentage,
        'cgst_percentage' => $cgst,
        'sgst_percentage' => $sgst,
        'igst_percentage' => $igst,
        'gst_amount' => $gstAmount,
        'gst_per_piece' => $quantity > 0 ? round($gstAmount / $quantity, 4) : 0,
        'net_per_piece' => $quantity > 0 ? round($lineTotal / $quantity, 4) : 0,
        'net_amount' => $taxableAmount,
        'line_total' => $lineTotal,
        'mrp' => 0,
        'retail_price' => $retailPrice,
        'wholesale_price' => $wholesalePrice,
        'retail_scheme_discount_type' => 1,
        'retail_scheme_discount_value' => 0,
        'retail_scheme_discount_amount' => 0,
        'retail_scheme_price' => $retailPrice,
        'wholesale_scheme_discount_type' => 1,
        'wholesale_scheme_discount_value' => 0,
        'wholesale_scheme_discount_amount' => 0,
        'wholesale_scheme_price' => $wholesalePrice,
        'status' => 1,
        'remarks' => 'Opening stock from product master'
    ];

    $purchaseItemId = dynamicInsert($pdo, 'purchase_items', $itemData);

    if ($purchaseItemId > 0 && function_exists('addStockMovement')) {
        addStockMovement($pdo, $scope, [
            'product_id' => $productId,
            'purchase_id' => $purchaseId,
            'purchase_item_id' => $purchaseItemId,
            'movement_date' => $today,
            'movement_type' => 'IN',
            'source_type' => 'OPENING_STOCK',
            'source_no' => $billNo,
            'batch_no' => $batchNo,
            'product_code' => $productCode,
            'product_name' => $productName,
            'qty' => $quantity,
            'rate' => $purchasePrice,
            'amount' => $lineTotal,
            'before_qty' => 0,
            'after_qty' => function_exists('stockProductBalance') ? stockProductBalance($pdo, $scope, $productId) : $quantity,
            'remarks' => 'Initial stock from product master',
            'created_by' => $userId
        ]);
    }
}

function deleteProduct(PDO $pdo) {
    requireProductPermission(5);
    $scope = getScope();
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) jsonResponse(false, 'Invalid product.');

    foreach (['purchase_items'=>'purchase','sales_document_items'=>'sales'] as $table=>$label) {
        if (!tableExists($pdo,$table)) continue;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE product_id=:product_id AND business_id=:business_id AND branch_id=:branch_id");
        $stmt->execute([':product_id'=>$productId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
        if ((int)$stmt->fetchColumn() > 0) jsonResponse(false, 'Product already used in '.$label.'. You can make it inactive instead.');
    }

    $img = $pdo->prepare("SELECT product_image FROM products WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
    $img->execute([':id'=>$productId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    $image = $img->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM products WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$productId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);

    deleteUploadedFile($image);
    jsonResponse(true, 'Product deleted successfully.');
}

function quickAddCategory(PDO $pdo) {
    requireProductPermission(3);
    $scope=getScope();
    $name=cleanInput($_POST['category_name'] ?? '');
    $desc=cleanInput($_POST['description'] ?? '');
    if ($name==='') jsonResponse(false,'Please enter category name.');
    $stmt=$pdo->prepare("INSERT INTO categories (business_id,branch_id,category_name,description,status,created_by) VALUES (:business_id,:branch_id,:name,:description,1,:created_by)");
    $stmt->execute([':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id'],':name'=>$name,':description'=>$desc,':created_by'=>currentUserId()]);
    jsonResponse(true,'Category added.',['id'=>(int)$pdo->lastInsertId(),'category_name'=>$name]);
}

function quickAddSubCategory(PDO $pdo) {
    requireProductPermission(3);
    $scope=getScope();
    $categoryId=(int)($_POST['category_id'] ?? 0);
    $name=cleanInput($_POST['sub_category_name'] ?? '');
    $desc=cleanInput($_POST['description'] ?? '');
    if ($categoryId<=0) jsonResponse(false,'Please select category.');
    if ($name==='') jsonResponse(false,'Please enter sub category name.');
    validateCategory($pdo,$scope,$categoryId);
    $stmt=$pdo->prepare("INSERT INTO sub_categories (business_id,branch_id,category_id,sub_category_name,description,status,created_by) VALUES (:business_id,:branch_id,:category_id,:name,:description,1,:created_by)");
    $stmt->execute([':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id'],':category_id'=>$categoryId,':name'=>$name,':description'=>$desc,':created_by'=>currentUserId()]);
    jsonResponse(true,'Sub category added.',['id'=>(int)$pdo->lastInsertId(),'category_id'=>$categoryId,'sub_category_name'=>$name]);
}

function quickAddHsn(PDO $pdo) {
    requireProductPermission(3);
    $scope=getScope();
    $code=cleanInput($_POST['hsn_code'] ?? '');
    $desc=cleanInput($_POST['hsn_description'] ?? '');
    $cgst=(float)($_POST['cgst_percentage'] ?? 0);
    $sgst=(float)($_POST['sgst_percentage'] ?? 0);
    $igst=(float)($_POST['igst_percentage'] ?? 0);
    if ($code==='') jsonResponse(false,'Please enter HSN code.');
    if ($cgst<0 || $sgst<0 || $igst<0) jsonResponse(false,'GST percentage cannot be negative.');
    $stmt=$pdo->prepare("INSERT INTO hsn_codes (business_id,branch_id,hsn_code,hsn_description,cgst_percentage,sgst_percentage,igst_percentage,status,created_by) VALUES (:business_id,:branch_id,:code,:description,:cgst,:sgst,:igst,1,:created_by)");
    $stmt->execute([':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id'],':code'=>$code,':description'=>$desc,':cgst'=>$cgst,':sgst'=>$sgst,':igst'=>$igst,':created_by'=>currentUserId()]);
    jsonResponse(true,'HSN added.',['id'=>(int)$pdo->lastInsertId(),'hsn_code'=>$code,'cgst_percentage'=>$cgst,'sgst_percentage'=>$sgst,'igst_percentage'=>$igst]);
}

function validateCategory(PDO $pdo,array $scope,$id) {
    $stmt=$pdo->prepare("SELECT id FROM categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id AND status=1 LIMIT 1");
    $stmt->execute([':id'=>$id,':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id']]);
    if (!$stmt->fetch()) jsonResponse(false,'Invalid category selected.');
}
function validateSubCategory(PDO $pdo,array $scope,$categoryId,$id) {
    $stmt=$pdo->prepare("SELECT id FROM sub_categories WHERE id=:id AND category_id=:category_id AND business_id=:business_id AND branch_id=:branch_id AND status=1 LIMIT 1");
    $stmt->execute([':id'=>$id,':category_id'=>$categoryId,':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id']]);
    if (!$stmt->fetch()) jsonResponse(false,'Invalid sub category selected.');
}
function validateHsn(PDO $pdo,array $scope,$id) {
    $stmt=$pdo->prepare("SELECT id FROM hsn_codes WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id AND status=1 LIMIT 1");
    $stmt->execute([':id'=>$id,':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id']]);
    if (!$stmt->fetch()) jsonResponse(false,'Invalid HSN selected.');
}
function getHsnGstPercentage(PDO $pdo,array $scope,$hsnId) {
    if ((int)$hsnId <= 0) return 0;
    $stmt=$pdo->prepare("SELECT cgst_percentage,sgst_percentage,igst_percentage FROM hsn_codes WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
    $stmt->execute([':id'=>$hsnId,':business_id'=>$scope['business_id'],':branch_id'=>$scope['branch_id']]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 0;
    $igst=(float)($row['igst_percentage'] ?? 0);
    return $igst > 0 ? $igst : ((float)($row['cgst_percentage'] ?? 0) + (float)($row['sgst_percentage'] ?? 0));
}
function generateProductCode(PDO $pdo,$businessId,$branchId) {
    $stmt=$pdo->prepare("SELECT COUNT(*)+1 FROM products WHERE business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':business_id'=>$businessId,':branch_id'=>$branchId]);
    return 'PRD'.str_pad((string)((int)$stmt->fetchColumn()),4,'0',STR_PAD_LEFT);
}
function uploadProductImage($file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) jsonResponse(false,'Product image upload failed.');
    if (($file['size'] ?? 0) > 2*1024*1024) jsonResponse(false,'Product image must be below 2 MB.');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $finfo=finfo_open(FILEINFO_MIME_TYPE);
    $mime=finfo_file($finfo,$file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) jsonResponse(false,'Only JPG, PNG and WEBP product images allowed.');
    $dir=BASE_PATH.'uploads/products/';
    if (!is_dir($dir)) mkdir($dir,0755,true);
    $fileName='product_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'],$dir.$fileName)) jsonResponse(false,'Unable to save product image.');
    return 'uploads/products/'.$fileName;
}
function deleteUploadedFile($relativePath) {
    if (!$relativePath) return;
    $path=BASE_PATH.str_replace(['../','..\\'],'',$relativePath);
    if (is_file($path)) @unlink($path);
}
function tableExists(PDO $pdo,$tableName) {
    try { $stmt=$pdo->prepare("SHOW TABLES LIKE :table"); $stmt->execute([':table'=>$tableName]); return (bool)$stmt->fetch(PDO::FETCH_NUM); }
    catch(Exception $e) { return false; }
}
