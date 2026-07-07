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
    case 'get_page_context':
        getPageContext($pdo);
        break;

    case 'list_suppliers':
        listSuppliers($pdo);
        break;

    case 'get_supplier':
        getSupplier($pdo);
        break;

    case 'get_supplier_view':
        getSupplierView($pdo);
        break;

    case 'save_supplier':
        verifyCsrfToken();
        saveSupplier($pdo);
        break;

    case 'delete_supplier':
        verifyCsrfToken();
        deleteSupplier($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

/*
|--------------------------------------------------------------------------
| Supplier permission action numbers
|--------------------------------------------------------------------------
| 1  = View
| 2  = List
| 3  = Create / Add
| 4  = Edit
| 5  = Delete
| 17 = Supplier Payment
|--------------------------------------------------------------------------
*/

function supplierCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('suppliers', (int)$actionCode);
}

function requireSupplierPermission($actionCode)
{
    if (!supplierCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!supplierCan(1) && !supplierCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => supplierCan(1),
            'can_list' => supplierCan(2),
            'can_add' => supplierCan(3),
            'can_edit' => supplierCan(4),
            'can_delete' => supplierCan(5),
            'can_supplier_payment' => supplierCan(17),
            'page_title' => 'Suppliers',
            'page_note' => 'Manage supplier master data based on your role permission.',
            'add_button_label' => 'Add Supplier',
            'add_modal_title' => 'Add Supplier',
            'edit_modal_title' => 'Edit Supplier',
            'supplier_payment_url' => BASE_URL . 'pages/supplier-payments.php',
            'view_url' => BASE_URL . 'pages/supplier-view.php'
        ]
    ]);
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

function listSuppliers(PDO $pdo)
{
    if (!supplierCan(1) && !supplierCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        /*
        |--------------------------------------------------------------------------
        | Unique PDO placeholders avoid SQLSTATE[HY093].
        |--------------------------------------------------------------------------
        */

        $where .= "
            AND (
                supplier_name LIKE :search_supplier_name
                OR mobile LIKE :search_mobile
                OR email LIKE :search_email
                OR gst_number LIKE :search_gst_number
                OR pan_number LIKE :search_pan_number
                OR dl_number LIKE :search_dl_number
                OR fl_number LIKE :search_fl_number
                OR bank_name LIKE :search_bank_name
                OR bank_account_no LIKE :search_bank_account_no
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_supplier_name'] = $searchValue;
        $params[':search_mobile'] = $searchValue;
        $params[':search_email'] = $searchValue;
        $params[':search_gst_number'] = $searchValue;
        $params[':search_pan_number'] = $searchValue;
        $params[':search_dl_number'] = $searchValue;
        $params[':search_fl_number'] = $searchValue;
        $params[':search_bank_name'] = $searchValue;
        $params[':search_bank_account_no'] = $searchValue;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                supplier_name,
                mobile,
                email,
                gst_number,
                pan_number,
                dl_number,
                fl_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                bank_name,
                bank_account_no,
                bank_branch,
                bank_ifsc,
                upi_id,
                status,
                created_at,
                updated_at
            FROM suppliers
            $where
            ORDER BY id DESC
        ");

        $stmt->execute($params);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = supplierCan(4);
        $canDelete = supplierCan(5);
        $canSupplierPayment = supplierCan(17);

        foreach ($suppliers as &$supplier) {
            $supplier['can_edit'] = $canEdit;
            $supplier['can_delete'] = $canDelete;
            $supplier['can_view'] = supplierCan(1) || supplierCan(2);
        $supplier['can_supplier_payment'] = $canSupplierPayment;
        }
        unset($supplier);

        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_suppliers,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_suppliers,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_suppliers,
                COALESCE(SUM(current_outstanding), 0) AS total_outstanding
            FROM suppliers
            WHERE business_id = :business_id
            AND branch_id = :branch_id
        ");

        $statsStmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Suppliers loaded.', [
            'suppliers' => $suppliers,
            'stats' => $stats ?: []
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load suppliers.');
    }
}

function getSupplierView(PDO $pdo)
{
    if (!supplierCan(1) && !supplierCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();
    $supplierId = (int)($_GET['supplier_id'] ?? $_GET['id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Invalid supplier.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                supplier_name,
                mobile,
                email,
                gst_number,
                pan_number,
                dl_number,
                fl_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                bank_name,
                bank_account_no,
                bank_branch,
                bank_ifsc,
                upi_id,
                status,
                created_at,
                updated_at
            FROM suppliers
            WHERE id = :supplier_id
            AND business_id = :business_id
            AND branch_id = :branch_id
            LIMIT 1
        ");
        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            jsonResponse(false, 'Supplier not found.');
        }

        $summaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_purchases,
                COALESCE(SUM(grand_total), 0) AS purchase_total,
                COALESCE(SUM(paid_amount), 0) AS paid_amount,
                COALESCE(SUM(due_amount), 0) AS due_amount
            FROM purchases
            WHERE supplier_id = :supplier_id
            AND business_id = :business_id
            AND branch_id = :branch_id
            AND status = 1
        ");
        $summaryStmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $openingOutstanding = round((float)($supplier['opening_outstanding'] ?? 0), 2);
        $purchaseTotal = round((float)($summary['purchase_total'] ?? 0), 2);
        $purchasePaid = round((float)($summary['paid_amount'] ?? 0), 2);
        $purchaseDue = round((float)($summary['due_amount'] ?? 0), 2);
        $totalDue = round($openingOutstanding + $purchaseDue, 2);

        $supplier['purchase_total'] = $purchaseTotal;
        $supplier['purchase_paid'] = $purchasePaid;
        $supplier['purchase_due'] = $purchaseDue;
        $supplier['total_due'] = $totalDue;
        $supplier['current_outstanding'] = $totalDue;
        $supplier['total_purchases'] = (int)($summary['total_purchases'] ?? 0);

        $purchaseStmt = $pdo->prepare("
            SELECT
                p.id,
                p.bill_no,
                p.batch_no,
                p.purchase_date,
                p.due_date,
                p.sub_total,
                p.discount_amount,
                p.tax_amount,
                p.round_off,
                p.grand_total,
                p.paid_amount,
                p.due_amount,
                p.status,
                p.notes,
                COUNT(pi.id) AS items_count
            FROM purchases p
            LEFT JOIN purchase_items pi
                ON pi.purchase_id = p.id
                AND pi.business_id = p.business_id
                AND pi.branch_id = p.branch_id
                AND pi.status = 1
            WHERE p.supplier_id = :supplier_id
            AND p.business_id = :business_id
            AND p.branch_id = :branch_id
            GROUP BY p.id
            ORDER BY p.purchase_date DESC, p.id DESC
            LIMIT 500
        ");
        $purchaseStmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $purchases = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);

        $canSupplierPayment = supplierCan(17);
        foreach ($purchases as &$purchase) {
            $purchase['can_view'] = true;
            $purchase['can_print'] = true;
            $purchase['can_supplier_payment'] = $canSupplierPayment
                && (float)($purchase['due_amount'] ?? 0) > 0
                && (int)($purchase['status'] ?? 0) === 1;
        }
        unset($purchase);

        jsonResponse(true, 'Supplier view loaded.', [
            'supplier' => $supplier,
            'purchases' => $purchases,
            'summary' => [
                'opening_outstanding' => $openingOutstanding,
                'purchase_total' => $purchaseTotal,
                'purchase_paid' => $purchasePaid,
                'purchase_due' => $purchaseDue,
                'total_due' => $totalDue,
                'total_purchases' => (int)($summary['total_purchases'] ?? 0)
            ],
            'context' => [
                'can_view' => supplierCan(1),
                'can_list' => supplierCan(2),
                'can_edit' => supplierCan(4),
                'can_delete' => supplierCan(5),
                'can_supplier_payment' => supplierCan(17),
                'supplier_payment_url' => BASE_URL . 'pages/supplier-payments.php',
                'purchase_view_url' => BASE_URL . 'pages/purchase-view.php'
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load supplier view.');
    }
}

function getSupplier(PDO $pdo)
{
    requireSupplierPermission(4);

    $scope = getScope();
    $supplierId = (int)($_GET['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Invalid supplier.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                supplier_name,
                mobile,
                email,
                gst_number,
                pan_number,
                dl_number,
                fl_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                bank_name,
                bank_account_no,
                bank_branch,
                bank_ifsc,
                upi_id,
                status
            FROM suppliers
            WHERE id = :supplier_id
            AND business_id = :business_id
            AND branch_id = :branch_id
            LIMIT 1
        ");

        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            jsonResponse(false, 'Supplier not found.');
        }

        jsonResponse(true, 'Supplier loaded.', [
            'supplier' => $supplier
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load supplier.');
    }
}

function saveSupplier(PDO $pdo)
{
    $scope = getScope();
    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    if ($supplierId > 0) {
        requireSupplierPermission(4);
    } else {
        requireSupplierPermission(3);
    }

    $supplierName = cleanInput($_POST['supplier_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = strtoupper(cleanInput($_POST['gst_number'] ?? ''));
    $panNumber = strtoupper(cleanInput($_POST['pan_number'] ?? ''));
    $dlNumber = strtoupper(cleanInput($_POST['dl_number'] ?? ''));
    $flNumber = strtoupper(cleanInput($_POST['fl_number'] ?? ''));

    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    $openingOutstanding = (float)($_POST['opening_outstanding'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    $bankName = cleanInput($_POST['bank_name'] ?? '');
    $bankAccountNo = cleanInput($_POST['bank_account_no'] ?? '');
    $bankBranch = cleanInput($_POST['bank_branch'] ?? '');
    $bankIfsc = strtoupper(cleanInput($_POST['bank_ifsc'] ?? ''));
    $upiId = cleanInput($_POST['upi_id'] ?? '');

    if ($supplierName === '') {
        jsonResponse(false, 'Please enter supplier name.');
    }

    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        jsonResponse(false, 'Please enter valid 6 digit pincode.');
    }

    if ($bankIfsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bankIfsc)) {
        jsonResponse(false, 'Please enter valid IFSC code.');
    }

    if ($openingOutstanding < 0) {
        jsonResponse(false, 'Opening outstanding cannot be negative.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        checkDuplicateSupplier($pdo, $scope, $mobile, $gstNumber, $panNumber, $supplierId);

        if ($supplierId > 0) {
            $oldStmt = $pdo->prepare("
                SELECT opening_outstanding, current_outstanding
                FROM suppliers
                WHERE id = :supplier_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                LIMIT 1
            ");

            $oldStmt->execute([
                ':supplier_id' => $supplierId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                jsonResponse(false, 'Supplier not found.');
            }

            $oldOpening = (float)($old['opening_outstanding'] ?? 0);
            $oldCurrent = (float)($old['current_outstanding'] ?? 0);

            $difference = $openingOutstanding - $oldOpening;
            $newCurrentOutstanding = $oldCurrent + $difference;

            if ($newCurrentOutstanding < 0) {
                $newCurrentOutstanding = 0;
            }

            $stmt = $pdo->prepare("
                UPDATE suppliers
                SET
                    supplier_name = :supplier_name,
                    mobile = :mobile,
                    email = :email,
                    gst_number = :gst_number,
                    pan_number = :pan_number,
                    dl_number = :dl_number,
                    fl_number = :fl_number,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    opening_outstanding = :opening_outstanding,
                    current_outstanding = :current_outstanding,
                    bank_name = :bank_name,
                    bank_account_no = :bank_account_no,
                    bank_branch = :bank_branch,
                    bank_ifsc = :bank_ifsc,
                    upi_id = :upi_id,
                    status = :status
                WHERE id = :supplier_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':supplier_name' => $supplierName,
                ':mobile' => $mobile,
                ':email' => $email,
                ':gst_number' => $gstNumber,
                ':pan_number' => $panNumber,
                ':dl_number' => $dlNumber,
                ':fl_number' => $flNumber,
                ':address' => $address,
                ':city' => $city,
                ':state' => $state,
                ':pincode' => $pincode,
                ':opening_outstanding' => $openingOutstanding,
                ':current_outstanding' => $newCurrentOutstanding,
                ':bank_name' => $bankName,
                ':bank_account_no' => $bankAccountNo,
                ':bank_branch' => $bankBranch,
                ':bank_ifsc' => $bankIfsc,
                ':upi_id' => $upiId,
                ':status' => $status,
                ':supplier_id' => $supplierId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Supplier updated successfully.', [
                'supplier_id' => $supplierId
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO suppliers
            (
                business_id,
                branch_id,
                supplier_name,
                mobile,
                email,
                gst_number,
                pan_number,
                dl_number,
                fl_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                bank_name,
                bank_account_no,
                bank_branch,
                bank_ifsc,
                upi_id,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :supplier_name,
                :mobile,
                :email,
                :gst_number,
                :pan_number,
                :dl_number,
                :fl_number,
                :address,
                :city,
                :state,
                :pincode,
                :opening_outstanding,
                :current_outstanding,
                :bank_name,
                :bank_account_no,
                :bank_branch,
                :bank_ifsc,
                :upi_id,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':supplier_name' => $supplierName,
            ':mobile' => $mobile,
            ':email' => $email,
            ':gst_number' => $gstNumber,
            ':pan_number' => $panNumber,
            ':dl_number' => $dlNumber,
            ':fl_number' => $flNumber,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':opening_outstanding' => $openingOutstanding,
            ':current_outstanding' => $openingOutstanding,
            ':bank_name' => $bankName,
            ':bank_account_no' => $bankAccountNo,
            ':bank_branch' => $bankBranch,
            ':bank_ifsc' => $bankIfsc,
            ':upi_id' => $upiId,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Supplier added successfully.', [
            'supplier_id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Supplier save failed.');
    }
}

function deleteSupplier(PDO $pdo)
{
    requireSupplierPermission(5);

    $scope = getScope();
    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Invalid supplier.');
    }

    try {
        $usedStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_used
            FROM purchases
            WHERE supplier_id = :supplier_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $usedStmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $used = $usedStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($used['total_used'] ?? 0) > 0) {
            jsonResponse(false, 'Supplier already used in purchase. You can make it inactive instead.');
        }

        $stmt = $pdo->prepare("
            DELETE FROM suppliers
            WHERE id = :supplier_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':supplier_id' => $supplierId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Supplier deleted successfully.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Supplier delete failed.');
    }
}

function checkDuplicateSupplier(PDO $pdo, array $scope, $mobile, $gstNumber, $panNumber, $ignoreSupplierId = 0)
{
    if ($mobile === '' && $gstNumber === '' && $panNumber === '') {
        return;
    }

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $conditions = [];

    if ($mobile !== '') {
        $conditions[] = "mobile = :mobile";
        $params[':mobile'] = $mobile;
    }

    if ($gstNumber !== '') {
        $conditions[] = "gst_number = :gst_number";
        $params[':gst_number'] = $gstNumber;
    }

    if ($panNumber !== '') {
        $conditions[] = "pan_number = :pan_number";
        $params[':pan_number'] = $panNumber;
    }

    if (empty($conditions)) {
        return;
    }

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND (" . implode(' OR ', $conditions) . ")
    ";

    if ((int)$ignoreSupplierId > 0) {
        $where .= " AND id != :ignore_supplier_id ";
        $params[':ignore_supplier_id'] = (int)$ignoreSupplierId;
    }

    $stmt = $pdo->prepare("
        SELECT id, mobile, gst_number, pan_number
        FROM suppliers
        $where
        LIMIT 1
    ");

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($mobile !== '' && (string)$row['mobile'] === (string)$mobile) {
            jsonResponse(false, 'Mobile number already exists.');
        }

        if ($gstNumber !== '' && (string)$row['gst_number'] === (string)$gstNumber) {
            jsonResponse(false, 'GST number already exists.');
        }

        jsonResponse(false, 'PAN number already exists.');
    }
}
