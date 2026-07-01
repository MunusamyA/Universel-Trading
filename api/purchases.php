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
    case 'list_purchases':
        listPurchases($pdo);
        break;
    case 'get_purchase':
        getPurchase($pdo);
        break;
    case 'save_purchase':
        verifyCsrfToken();
        savePurchase($pdo);
        break;
    case 'delete_purchase':
        verifyCsrfToken();
        deletePurchase($pdo);
        break;
    case 'get_suppliers':
        getSuppliers($pdo);
        break;
    case 'get_products':
        getProducts($pdo);
        break;
    case 'get_product':
        getProduct($pdo);
        break;
    case 'get_hsn_codes':
        getHsnCodes($pdo);
        break;
    case 'add_hsn_code':
        verifyCsrfToken();
        addHsnCode($pdo);
        break;
    case 'get_product_masters':
        getProductMasters($pdo);
        break;
    case 'add_quick_product':
        verifyCsrfToken();
        addQuickProduct($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function requirePurchasePermission($action = 'view')
{
    if (isPlatformOwner()) {
        return;
    }

    if (function_exists('hasPermission') && !hasPermission('purchases', $action)) {
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

function listPurchases(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $fromDate = cleanInput($_GET['from_date'] ?? '');
    $toDate = cleanInput($_GET['to_date'] ?? '');

    $where = "WHERE p.business_id = :business_id AND p.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND p.status = :status";
        $params[':status'] = $status;
    }

    if ($fromDate !== '') {
        $where .= " AND p.purchase_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate !== '') {
        $where .= " AND p.purchase_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($search !== '') {
        $where .= " AND (p.bill_no LIKE :search OR p.batch_no LIKE :search OR s.supplier_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.supplier_name,
            COUNT(pi.id) AS items_count
        FROM purchases p
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
        $where
        GROUP BY p.id
        ORDER BY p.purchase_date DESC, p.id DESC
        LIMIT 300
    ");
    $stmt->execute($params);

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_purchases,
            COALESCE(SUM(grand_total), 0) AS total_amount,
            COALESCE(SUM(paid_amount), 0) AS paid_amount,
            COALESCE(SUM(due_amount), 0) AS due_amount
        FROM purchases
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
    ");
    $statsStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Purchases loaded.', [
        'purchases' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
    ]);
}

function getPurchase(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();
    $purchaseId = (int)($_GET['purchase_id'] ?? 0);

    if ($purchaseId <= 0) {
        jsonResponse(false, 'Invalid purchase.');
    }

    $stmt = $pdo->prepare("
        SELECT p.*, s.supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        WHERE p.id = :id
        AND p.business_id = :business_id
        AND p.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) {
        jsonResponse(false, 'Purchase not found.');
    }

    $itemsStmt = $pdo->prepare("
        SELECT
            pi.*,
            pr.product_name,
            pr.product_code,
            h.hsn_code
        FROM purchase_items pi
        LEFT JOIN products pr ON pr.id = pi.product_id
        LEFT JOIN hsn_codes h ON h.id = pi.hsn_id
        WHERE pi.purchase_id = :purchase_id
        AND pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        ORDER BY pi.id ASC
    ");
    $itemsStmt->execute([
        ':purchase_id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Purchase loaded.', [
        'purchase' => $purchase,
        'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function savePurchase(PDO $pdo)
{
    $scope = getScope();
    $purchaseId = (int)($_POST['purchase_id'] ?? 0);

    requirePurchasePermission($purchaseId > 0 ? 'edit' : 'add');

    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $billNo = cleanInput($_POST['bill_no'] ?? '');
    $batchNo = cleanInput($_POST['batch_no'] ?? '');
    $purchaseDate = cleanInput($_POST['purchase_date'] ?? date('Y-m-d'));
    $dueDate = cleanInput($_POST['due_date'] ?? '');
    $discountType = (int)($_POST['discount_type'] ?? 1);
    $discountValue = round((float)($_POST['discount_value'] ?? 0), 2);
    $roundOff = round((float)($_POST['round_off'] ?? 0), 2);
    $paidAmount = round((float)($_POST['paid_amount'] ?? 0), 2);
    $notes = cleanInput($_POST['notes'] ?? '');

    $itemsJson = $_POST['items_json'] ?? '';
    $items = json_decode($itemsJson, true);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Please select supplier.');
    }

    if ($billNo === '') {
        jsonResponse(false, 'Please enter bill number.');
    }

    if ($batchNo === '') {
        $dateText = date('Ymd', strtotime($purchaseDate ?: date('Y-m-d')));
        $cleanBillNo = preg_replace('/[^A-Za-z0-9]/', '', $billNo);
        if ($cleanBillNo === '') {
            $cleanBillNo = 'PUR';
        }
        $batchNo = 'BAT-' . $dateText . '-' . strtoupper($cleanBillNo);
    }

    if (!is_array($items) || count($items) === 0) {
        jsonResponse(false, 'Please add at least one product.');
    }

    if (!in_array($discountType, [1, 2], true)) {
        $discountType = 1;
    }

    validateSupplier($pdo, $scope, $supplierId);

    $subTotal = 0;
    $taxAmount = 0;
    $cleanItems = [];
    $seenProductIds = [];

    foreach ($items as $index => $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $boxQty = round((float)($item['box_qty'] ?? 0), 4);
        $loosePieceQty = round((float)($item['loose_piece_qty'] ?? 0), 4);
        $freeQty = round((float)($item['free_qty'] ?? 0), 4);
        $unitConversion = round((float)($item['unit_conversion'] ?? 1), 4);
        if ($unitConversion <= 0) { $unitConversion = 1; }

        // Correct total pieces:
        // 1) If box/loose/free details exist, calculate from conversion.
        // 2) If row/table qty is entered directly, use item.qty.
        $enteredQty = round((float)($item['qty'] ?? 0), 4);
        $calculatedQty = round(($boxQty * $unitConversion) + $loosePieceQty + $freeQty, 4);

        if ($calculatedQty > 0) {
            $qty = $calculatedQty;
        } else {
            $qty = $enteredQty;
            if ($qty > 0 && $loosePieceQty <= 0 && $boxQty <= 0) {
                $loosePieceQty = $qty;
            }
        }
        $purchasePrice = round((float)($item['purchase_price'] ?? 0), 2);
        $itemDiscountType = (int)($item['discount_type'] ?? 1);
        $itemDiscountValue = round((float)($item['discount_value'] ?? 0), 2);
        $gstType = (int)($item['gst_type'] ?? 2);
        $gstPercentage = round((float)($item['gst_percentage'] ?? 0), 2);
        $selectedHsnId = (int)($item['hsn_id'] ?? 0);
        $selectedHsnCode = cleanInput($item['hsn_code'] ?? '');
        $cgstPercentage = round((float)($item['cgst_percentage'] ?? 0), 2);
        $sgstPercentage = round((float)($item['sgst_percentage'] ?? 0), 2);
        $igstPercentage = round((float)($item['igst_percentage'] ?? 0), 2);
        $mrp = round((float)($item['mrp'] ?? 0), 2);
        $retailPrice = round((float)($item['retail_price'] ?? 0), 2);
        $wholesalePrice = $retailPrice; // removed wholesale price, use single sale price
        $retailSchemeType = 1; // removed separate retail scheme
        $retailSchemeValue = 0;
        $wholesaleSchemeType = 1; // removed separate wholesale scheme
        $wholesaleSchemeValue = 0;
        $expiryDays = (int)($item['expiry_days'] ?? 0);
        $expiryDate = cleanInput($item['expiry_date'] ?? '');
        $unitLabel = cleanInput($item['unit_label'] ?? 'Piece');

        if ($productId <= 0) {
            jsonResponse(false, 'Invalid product in row ' . ($index + 1));
        }

        if (in_array($productId, $seenProductIds, true)) {
            jsonResponse(false, 'Duplicate product not allowed in purchase. Please edit the existing product row.');
        }
        $seenProductIds[] = $productId;

        if ($qty <= 0) {
            jsonResponse(false, 'Total pieces must be greater than zero in row ' . ($index + 1));
        }

        if ($purchasePrice <= 0) {
            jsonResponse(false, 'Purchase price must be greater than zero in row ' . ($index + 1));
        }

        if ($expiryDays < 0) {
            jsonResponse(false, 'Expiry days cannot be negative in row ' . ($index + 1));
        }

        if (!in_array($itemDiscountType, [1, 2], true)) {
            $itemDiscountType = 1;
        }

        if (!in_array($gstType, [1, 2], true)) {
            $gstType = 2;
        }

        if (!in_array($retailSchemeType, [1, 2], true)) {
            $retailSchemeType = 1;
        }

        if (!in_array($wholesaleSchemeType, [1, 2], true)) {
            $wholesaleSchemeType = 1;
        }

        $product = getProductRow($pdo, $scope, $productId);

        $baseUnit = cleanInput($item['base_unit'] ?? ($product['base_unit'] ?? 'Piece'));
        $boxLabel = cleanInput($item['box_label'] ?? ($product['box_label'] ?? 'Box'));
        $piecesPerBox = round((float)($item['pieces_per_box'] ?? ($product['default_pieces_per_box'] ?? 1)), 4);
        if ($piecesPerBox <= 0) { $piecesPerBox = 1; }

        $lineGross = round($qty * $purchasePrice, 2);

        /**
         * Correct purchase calculation:
         * gst_type 1 = Inclusive, gst_type 2 = Exclusive.
         * Fixed scheme is total line discount.
         */
        if ($itemDiscountType === 1) {
            $itemDiscountAmount = round(($lineGross * $itemDiscountValue) / 100, 6);
        } else {
            $itemDiscountAmount = round($itemDiscountValue, 6);
        }

        if ($itemDiscountAmount > $lineGross) {
            $itemDiscountAmount = $lineGross;
        }

        $schemePerPiece = $qty > 0 ? round($itemDiscountAmount / $qty, 6) : 0;
        $afterSchemePerPiece = round($purchasePrice - $schemePerPiece, 6);

        if ($afterSchemePerPiece < 0) {
            $afterSchemePerPiece = 0;
        }

        if ($gstType === 1) {
            $inclusiveAmount = round($afterSchemePerPiece * $qty, 6);
            $itemGstAmount = $gstPercentage > 0 ? round(($inclusiveAmount * $gstPercentage) / (100 + $gstPercentage), 6) : 0;
            $taxableAmount = round($inclusiveAmount - $itemGstAmount, 6);
            $lineTotal = round($inclusiveAmount, 6);
        } else {
            $taxableAmount = round($afterSchemePerPiece * $qty, 6);
            $itemGstAmount = round(($taxableAmount * $gstPercentage) / 100, 6);
            $lineTotal = round($taxableAmount + $itemGstAmount, 6);
        }

        $afterDiscount = $taxableAmount;

        $stockQty = round($qty, 4);

        $schemePerPiece = $qty > 0 ? round($itemDiscountAmount / $qty, 4) : 0;
        $taxablePerPiece = $qty > 0 ? round($taxableAmount / $qty, 4) : 0;
        $gstPerPiece = $qty > 0 ? round($itemGstAmount / $qty, 4) : 0;
        $netPerPiece = $qty > 0 ? round($lineTotal / $qty, 4) : 0;

        $retailSchemeAmount = 0;
        $retailSchemePrice = $retailPrice;
        $wholesaleSchemeAmount = 0;
        $wholesaleSchemePrice = $retailPrice;

        if ($expiryDate === '' && $expiryDays > 0) {
            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' +' . $expiryDays . ' days'));
        }

        if ($expiryDate === '') {
            $expiryDate = null;
        }

        $subTotal += $lineGross;
        $taxAmount += $itemGstAmount;

        $cleanItems[] = [
            'product_id' => $productId,
            'product_code' => cleanInput($item['product_code'] ?? ($product['product_code'] ?? '')),
            'product_name' => cleanInput($item['product_name'] ?? ($product['product_name'] ?? '')),
            'hsn_id' => $selectedHsnId > 0 ? $selectedHsnId : (int)($product['hsn_id'] ?? 0),
            'hsn_code' => $selectedHsnCode !== '' ? $selectedHsnCode : cleanInput($product['hsn_code'] ?? ''),
            'expiry_days' => $expiryDays,
            'expiry_date' => $expiryDate,
            'unit_label' => $unitLabel,
            'base_unit' => $baseUnit,
            'box_label' => $boxLabel,
            'pieces_per_box' => $piecesPerBox,
            'box_qty' => $boxQty,
            'loose_piece_qty' => $loosePieceQty,
            'qty' => $qty,
            'free_qty' => $freeQty,
            'unit_conversion' => $unitConversion,
            'stock_qty' => $stockQty,
            // Store actual cost after scheme discount in purchase_items.purchase_price.
            // Example: 214.55 rate, 13.48 total scheme, 10 qty => 213.20.
            'purchase_price' => round($afterSchemePerPiece, 2),
            'discount_type' => $itemDiscountType,
            'discount_value' => $itemDiscountValue,
            'discount_amount' => $itemDiscountAmount,
            'scheme_per_piece' => $schemePerPiece,
            'gross_amount' => $lineGross,
            'taxable_amount' => $taxableAmount,
            'taxable_per_piece' => $taxablePerPiece,
            'gst_type' => $gstType,
            'gst_percentage' => $gstPercentage,
            'cgst_percentage' => $cgstPercentage,
            'sgst_percentage' => $sgstPercentage,
            'igst_percentage' => $igstPercentage,
            'gst_amount' => $itemGstAmount,
            'gst_per_piece' => $gstPerPiece,
            'net_per_piece' => $netPerPiece,
            'net_amount' => $afterDiscount,
            'line_total' => $lineTotal,
            'mrp' => $mrp,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'retail_scheme_discount_type' => $retailSchemeType,
            'retail_scheme_discount_value' => $retailSchemeValue,
            'retail_scheme_discount_amount' => $retailSchemeAmount,
            'retail_scheme_price' => $retailSchemePrice,
            'wholesale_scheme_discount_type' => $wholesaleSchemeType,
            'wholesale_scheme_discount_value' => $wholesaleSchemeValue,
            'wholesale_scheme_discount_amount' => $wholesaleSchemeAmount,
            'wholesale_scheme_price' => $wholesaleSchemePrice
        ];
    }

    $subTotal = round($subTotal, 2);
    $taxAmount = round($taxAmount, 2);

    $purchaseDiscountAmount = $discountType === 1
        ? round(($subTotal * $discountValue) / 100, 2)
        : $discountValue;

    if ($purchaseDiscountAmount > $subTotal) {
        $purchaseDiscountAmount = $subTotal;
    }

    $itemsTotal = 0;
    foreach ($cleanItems as $item) {
        $itemsTotal += $item['line_total'];
    }

    $grandTotal = round($itemsTotal - $purchaseDiscountAmount + $roundOff, 2);

    if ($grandTotal < 0) {
        $grandTotal = 0;
    }

    if ($paidAmount < 0) {
        $paidAmount = 0;
    }

    if ($paidAmount > $grandTotal) {
        $paidAmount = $grandTotal;
    }

    $dueAmount = round($grandTotal - $paidAmount, 2);

    if ($paidAmount <= 0) {
        $paymentStatus = 1;
    } elseif ($dueAmount > 0) {
        $paymentStatus = 2;
    } else {
        $paymentStatus = 3;
    }

    try {
        $pdo->beginTransaction();

        if ($purchaseId > 0) {
            $checkStmt = $pdo->prepare("
                SELECT id
                FROM purchases
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
                LIMIT 1
            ");
            $checkStmt->execute([
                ':id' => $purchaseId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception('Purchase not found.');
            }

            $stmt = $pdo->prepare("
                UPDATE purchases
                SET supplier_id = :supplier_id,
                    batch_no = :batch_no,
                    bill_no = :bill_no,
                    purchase_date = :purchase_date,
                    due_date = :due_date,
                    sub_total = :sub_total,
                    discount_type = :discount_type,
                    discount_value = :discount_value,
                    discount_amount = :discount_amount,
                    tax_amount = :tax_amount,
                    round_off = :round_off,
                    grand_total = :grand_total,
                    paid_amount = :paid_amount,
                    due_amount = :due_amount,
                    payment_status = :payment_status,
                    notes = :notes,
                    updated_by = :updated_by
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':supplier_id' => $supplierId,
                ':batch_no' => $batchNo,
                ':bill_no' => $billNo,
                ':purchase_date' => $purchaseDate,
                ':due_date' => $dueDate !== '' ? $dueDate : null,
                ':sub_total' => $subTotal,
                ':discount_type' => $discountType,
                ':discount_value' => $discountValue,
                ':discount_amount' => $purchaseDiscountAmount,
                ':tax_amount' => $taxAmount,
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => $paidAmount,
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':notes' => $notes,
                ':updated_by' => currentUserId(),
                ':id' => $purchaseId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            deletePurchaseItems($pdo, $scope, $purchaseId);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO purchases
                (
                    business_id, branch_id, supplier_id, batch_no, bill_no, purchase_date, due_date,
                    sub_total, discount_type, discount_value, discount_amount, tax_amount, round_off,
                    grand_total, paid_amount, due_amount, payment_status, status, notes, created_by
                )
                VALUES
                (
                    :business_id, :branch_id, :supplier_id, :batch_no, :bill_no, :purchase_date, :due_date,
                    :sub_total, :discount_type, :discount_value, :discount_amount, :tax_amount, :round_off,
                    :grand_total, :paid_amount, :due_amount, :payment_status, 1, :notes, :created_by
                )
            ");

            $stmt->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':supplier_id' => $supplierId,
                ':batch_no' => $batchNo,
                ':bill_no' => $billNo,
                ':purchase_date' => $purchaseDate,
                ':due_date' => $dueDate !== '' ? $dueDate : null,
                ':sub_total' => $subTotal,
                ':discount_type' => $discountType,
                ':discount_value' => $discountValue,
                ':discount_amount' => $purchaseDiscountAmount,
                ':tax_amount' => $taxAmount,
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => $paidAmount,
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':notes' => $notes,
                ':created_by' => currentUserId()
            ]);

            $purchaseId = (int)$pdo->lastInsertId();
        }

        insertPurchaseItems($pdo, $scope, $purchaseId, $cleanItems);

        $pdo->commit();

        jsonResponse(true, 'Purchase saved successfully.', [
            'purchase_id' => $purchaseId,
            'redirect' => BASE_URL . 'pages/purchases.php'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Purchase save failed.');
    }
}

function insertPurchaseItems(PDO $pdo, array $scope, $purchaseId, array $items)
{
    $itemSql = "
        INSERT INTO purchase_items
        (
            purchase_id, business_id, branch_id,
            product_id, product_code, product_name,
            hsn_id, hsn_code,
            expiry_date,
            unit_label, base_unit, box_label,
            box_qty, loose_piece_qty, qty, free_qty, unit_conversion,
            stock_qty, sold_qty, available_qty,
            purchase_price, gross_amount,
            discount_type, discount_value, discount_amount, scheme_per_piece,
            taxable_amount, taxable_per_piece,
            gst_type, gst_percentage, cgst_percentage, sgst_percentage, igst_percentage, gst_amount,
            gst_per_piece, net_per_piece,
            net_amount, line_total,
            mrp,
            status
        )
        VALUES
        (
            :purchase_id, :business_id, :branch_id,
            :product_id, :product_code, :product_name,
            :hsn_id, :hsn_code,
            :expiry_date,
            :unit_label, :base_unit, :box_label,
            :box_qty, :loose_piece_qty, :qty, :free_qty, :unit_conversion,
            :stock_qty, 0, :available_qty,
            :purchase_price, :gross_amount,
            :discount_type, :discount_value, :discount_amount, :scheme_per_piece,
            :taxable_amount, :taxable_per_piece,
            :gst_type, :gst_percentage, :cgst_percentage, :sgst_percentage, :igst_percentage, :gst_amount, :gst_per_piece, :net_per_piece,
            :net_amount, :line_total,
            :mrp,
            1
        )
    ";

    $itemStmt = $pdo->prepare($itemSql);

    foreach ($items as $item) {
        $itemStmt->execute([
            ':purchase_id' => $purchaseId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':product_id' => $item['product_id'],
            ':product_code' => $item['product_code'],
            ':product_name' => $item['product_name'],
            ':hsn_id' => $item['hsn_id'] > 0 ? $item['hsn_id'] : null,
            ':hsn_code' => $item['hsn_code'],
            ':expiry_date' => $item['expiry_date'],
            ':unit_label' => $item['unit_label'],
            ':base_unit' => $item['base_unit'],
            ':box_label' => $item['box_label'],
            ':box_qty' => $item['box_qty'],
            ':loose_piece_qty' => $item['loose_piece_qty'],
            ':qty' => $item['qty'],
            ':free_qty' => $item['free_qty'],
            ':unit_conversion' => $item['unit_conversion'],
            ':stock_qty' => $item['stock_qty'],
            ':available_qty' => $item['stock_qty'],
            ':purchase_price' => $item['purchase_price'],
            ':gross_amount' => $item['gross_amount'],
            ':discount_type' => $item['discount_type'],
            ':discount_value' => $item['discount_value'],
            ':discount_amount' => $item['discount_amount'],
            ':scheme_per_piece' => $item['scheme_per_piece'],
            ':taxable_amount' => $item['taxable_amount'],
            ':taxable_per_piece' => $item['taxable_per_piece'],
            ':gst_type' => $item['gst_type'],
            ':gst_percentage' => $item['gst_percentage'],
            ':cgst_percentage' => $item['cgst_percentage'],
            ':sgst_percentage' => $item['sgst_percentage'],
            ':igst_percentage' => $item['igst_percentage'],
            ':gst_amount' => $item['gst_amount'],
            ':gst_per_piece' => $item['gst_per_piece'],
            ':net_per_piece' => $item['net_per_piece'],
            ':net_amount' => $item['net_amount'],
            ':line_total' => $item['line_total'],
            ':mrp' => $item['mrp']
        ]);
    }
}

function deletePurchaseItems(PDO $pdo, array $scope, $purchaseId)
{
    $soldCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM purchase_items
        WHERE purchase_id = :purchase_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND sold_qty > 0
    ");
    $soldCheck->execute([
        ':purchase_id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if ((int)$soldCheck->fetchColumn() > 0) {
        throw new Exception('This purchase stock is already sold. Cannot edit or delete this purchase.');
    }

    $itemStmt = $pdo->prepare("
        DELETE FROM purchase_items
        WHERE purchase_id = :purchase_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $itemStmt->execute([
        ':purchase_id' => $purchaseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function getFifoPurchaseItems(PDO $pdo, array $scope, $productId)
{
    $stmt = $pdo->prepare("
        SELECT
            pi.*,
            p.purchase_date,
            p.batch_no,
            p.bill_no,
            p.supplier_id
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        WHERE pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.product_id = :product_id
        AND pi.available_qty > 0
        AND pi.status = 1
        AND p.status = 1
        ORDER BY p.purchase_date ASC, pi.id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deletePurchase(PDO $pdo)
{
    requirePurchasePermission('delete');

    $scope = getScope();
    $purchaseId = (int)($_POST['purchase_id'] ?? 0);

    if ($purchaseId <= 0) {
        jsonResponse(false, 'Invalid purchase.');
    }

    try {
        $pdo->beginTransaction();

        deletePurchaseItems($pdo, $scope, $purchaseId);

        $stmt = $pdo->prepare("
            DELETE FROM purchases
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':id' => $purchaseId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $pdo->commit();

        jsonResponse(true, 'Purchase deleted successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Purchase delete failed.');
    }
}

function getSuppliers(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();

    $stmt = $pdo->prepare("
        SELECT id, supplier_name
        FROM suppliers
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY supplier_name ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Suppliers loaded.', [
        'suppliers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getProducts(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.product_code,
            p.product_name,
            p.hsn_id,
            p.base_unit,
            p.box_label,
            p.default_pieces_per_box,
            p.secondary_unit_label,
            p.secondary_unit_value,
            p.expire_days,
            p.gst_type,
            p.enter_mrp,
            p.final_mrp,
            p.cost_price,
            p.retail_price,
            p.wholesale_price,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage
        FROM products p
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        WHERE p.business_id = :business_id
        AND p.branch_id = :branch_id
        AND p.status = 1
        ORDER BY p.product_name ASC
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Products loaded.', [
        'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getProduct(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($productId <= 0) {
        jsonResponse(false, 'Invalid product.');
    }

    $product = getProductRow($pdo, $scope, $productId);

    jsonResponse(true, 'Product loaded.', [
        'product' => $product
    ]);
}

function getProductRow(PDO $pdo, array $scope, $productId)
{
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage
        FROM products p
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        WHERE p.id = :id
        AND p.business_id = :business_id
        AND p.branch_id = :branch_id
        AND p.status = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $productId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        jsonResponse(false, 'Invalid product selected.');
    }

    return $product;
}



function getTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[$row['Field']] = $row;
    }
    return $cols;
}

function insertDynamic(PDO $pdo, string $table, array $data): int
{
    $columns = getTableColumns($pdo, $table);
    $insert = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (isset($columns[$key])) {
            $insert[$key] = $value;
            $params[':' . $key] = $value;
        }
    }

    if (!$insert) {
        jsonResponse(false, 'No valid columns to insert.');
    }

    $fieldSql = '`' . implode('`, `', array_keys($insert)) . '`';
    $paramSql = implode(', ', array_keys($params));

    $stmt = $pdo->prepare("INSERT INTO `$table` ($fieldSql) VALUES ($paramSql)");
    $stmt->execute($params);

    return (int)$pdo->lastInsertId();
}


function getHsnCodes(PDO $pdo)
{
    requirePurchasePermission('view');

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


function getProductMasters(PDO $pdo)
{
    requirePurchasePermission('view');

    $scope = getScope();

    $categories = [];
    $subCategories = [];
    $hsnCodes = [];

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
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT id, category_id, sub_category_name
        FROM sub_categories
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY sub_category_name ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $subCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT id, hsn_code, hsn_description, cgst_percentage, sgst_percentage, igst_percentage
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
    $hsnCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'Product masters loaded.', [
        'categories' => $categories,
        'sub_categories' => $subCategories,
        'hsn_codes' => $hsnCodes
    ]);
}

function addQuickProduct(PDO $pdo)
{
    requirePurchasePermission('add');

    $scope = getScope();

    $productCode = cleanInput($_POST['product_code'] ?? '');
    $productName = cleanInput($_POST['product_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $subCategoryId = (int)($_POST['sub_category_id'] ?? 0);
    $hsnId = (int)($_POST['hsn_id'] ?? 0);

    $baseUnit = cleanInput($_POST['base_unit'] ?? 'Piece');
    $boxLabel = cleanInput($_POST['box_label'] ?? 'Box');
    $defaultPiecesPerBox = round((float)($_POST['default_pieces_per_box'] ?? 1), 4);
    $secondaryUnitLabel = cleanInput($_POST['secondary_unit_label'] ?? '');
    $secondaryUnitValue = round((float)($_POST['secondary_unit_value'] ?? 1), 4);
    $expireDays = (int)($_POST['expire_days'] ?? 0);
    $minimumStock = round((float)($_POST['minimum_stock'] ?? 0), 2);
    $status = (int)($_POST['status'] ?? 1);

    $gstType = 2;
    $purchasePrice = round((float)($_POST['cost_price'] ?? ($_POST['purchase_price'] ?? 0)), 2);

    $retailMarkupType = (int)($_POST['retail_markup_type'] ?? 1);
    $retailMarkupValue = round((float)($_POST['retail_markup_value'] ?? 0), 2);
    $mrp = round((float)($_POST['enter_mrp'] ?? ($_POST['mrp'] ?? 0)), 2);
    $discountType = (int)($_POST['discount_type'] ?? 1);
    $discountValue = round((float)($_POST['discount_value'] ?? 0), 2);
    $finalMrp = round((float)($_POST['final_mrp'] ?? $mrp), 2);
    $retailPrice = round((float)($_POST['retail_price'] ?? 0), 2);
    $wholesaleMarkupType = 1;
    $wholesaleMarkupValue = 0;
    $wholesalePrice = $retailPrice;

    if ($productName === '') {
        jsonResponse(false, 'Please enter product name.');
    }

    if ($productCode === '') {
        $productCode = 'PRD' . time();
    }

    if ($categoryId <= 0) {
        jsonResponse(false, 'Please select category.');
    }

    if ($subCategoryId <= 0) {
        jsonResponse(false, 'Please select sub category.');
    }

    if ($hsnId <= 0) {
        jsonResponse(false, 'Please select HSN.');
    }

    if ($defaultPiecesPerBox <= 0) {
        $defaultPiecesPerBox = 1;
    }

    if ($secondaryUnitValue <= 0) {
        $secondaryUnitValue = 1;
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    if (!in_array($gstType, [1, 2], true)) {
        $gstType = 2;
    }

    if (!in_array($retailMarkupType, [1, 2], true)) {
        $retailMarkupType = 1;
    }

    if (!in_array($wholesaleMarkupType, [1, 2], true)) {
        $wholesaleMarkupType = 1;
    }

    if (!in_array($discountType, [1, 2], true)) {
        $discountType = 1;
    }

    if ($finalMrp <= 0) {
        $discountAmount = $discountType === 1 ? (($mrp * $discountValue) / 100) : $discountValue;
        if ($discountAmount > $mrp) { $discountAmount = $mrp; }
        $finalMrp = round($mrp - $discountAmount, 2);
    }

    if ($retailPrice <= 0 && $purchasePrice > 0) {
        $retailPrice = $retailMarkupType === 1
            ? round($purchasePrice + (($purchasePrice * $retailMarkupValue) / 100), 2)
            : round($purchasePrice + $retailMarkupValue, 2);
    }

    $check = $pdo->prepare("
        SELECT id
        FROM products
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND product_code = :product_code
        LIMIT 1
    ");
    $check->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_code' => $productCode
    ]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Product code already exists.');
    }

    $data = [
        'business_id' => $scope['business_id'],
        'branch_id' => $scope['branch_id'],
        'category_id' => $categoryId,
        'sub_category_id' => $subCategoryId,
        'hsn_id' => $hsnId,
        'product_code' => $productCode,
        'product_name' => $productName,
        'cost_price' => $purchasePrice,
        'purchase_price' => $purchasePrice,
        'retail_markup_type' => $retailMarkupType,
        'retail_markup_value' => $retailMarkupValue,
        'enter_mrp' => $mrp,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'final_mrp' => $finalMrp,
        'mrp' => $mrp,
        'retail_price' => $retailPrice,
        'wholesale_markup_type' => $wholesaleMarkupType,
        'wholesale_markup_value' => $wholesaleMarkupValue,
        'wholesale_price' => $wholesalePrice,
        'base_unit' => $baseUnit,
        'box_label' => $boxLabel,
        'default_pieces_per_box' => $defaultPiecesPerBox,
        'pieces_per_box' => $defaultPiecesPerBox,
        'secondary_unit_label' => $secondaryUnitLabel,
        'secondary_unit_value' => $secondaryUnitValue,
        'expire_days' => $expireDays,
        'minimum_stock' => $minimumStock,
        'gst_type' => $gstType,
        'status' => $status,
        'created_by' => currentUserId()
    ];

    $pdo->beginTransaction();

    try {
        $productId = insertDynamic($pdo, 'products', $data);
        $pdo->commit();

        $product = getProductRow($pdo, $scope, $productId);

        jsonResponse(true, 'Product added to master successfully. Now you can edit purchase values before Add.', [
            'product_id' => $productId,
            'product' => $product
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Product save failed.');
    }
}


function addHsnCode(PDO $pdo)
{
    requirePurchasePermission('add');

    $scope = getScope();

    $hsnCode = cleanInput($_POST['hsn_code'] ?? '');
    $description = cleanInput($_POST['hsn_description'] ?? '');
    $cgst = round((float)($_POST['cgst_percentage'] ?? 0), 2);
    $sgst = round((float)($_POST['sgst_percentage'] ?? 0), 2);
    $igst = round((float)($_POST['igst_percentage'] ?? 0), 2);

    if ($hsnCode === '') {
        jsonResponse(false, 'Please enter HSN code.');
    }

    if ($cgst < 0 || $sgst < 0 || $igst < 0) {
        jsonResponse(false, 'GST percentage cannot be negative.');
    }

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM hsn_codes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND hsn_code = :hsn_code
        LIMIT 1
    ");
    $checkStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':hsn_code' => $hsnCode
    ]);

    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        jsonResponse(true, 'HSN already exists.', [
            'hsn_id' => (int)$existing['id']
        ]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO hsn_codes
        (
            business_id,
            branch_id,
            hsn_code,
            hsn_description,
            cgst_percentage,
            sgst_percentage,
            igst_percentage,
            status,
            created_by
        )
        VALUES
        (
            :business_id,
            :branch_id,
            :hsn_code,
            :hsn_description,
            :cgst_percentage,
            :sgst_percentage,
            :igst_percentage,
            1,
            :created_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':hsn_code' => $hsnCode,
        ':hsn_description' => $description,
        ':cgst_percentage' => $cgst,
        ':sgst_percentage' => $sgst,
        ':igst_percentage' => $igst,
        ':created_by' => currentUserId()
    ]);

    jsonResponse(true, 'HSN code added successfully.', [
        'hsn_id' => (int)$pdo->lastInsertId()
    ]);
}


function validateSupplier(PDO $pdo, array $scope, $supplierId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM suppliers
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid supplier selected.');
    }
}
